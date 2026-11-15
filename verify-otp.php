
<?php

$role = strtolower(trim($_POST['role'] ?? ''));

$sessionNames = [
    'student' => 'APOLLO_STUDENT_SESSION',
    'faculty' => 'APOLLO_FACULTY_SESSION',
    'parent'  => 'APOLLO_PARENT_SESSION'
];

header('Content-Type: application/json');

if (!isset($sessionNames[$role])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role.'
    ]);
    exit;
}

session_name($sessionNames[$role]);
session_start();

require_once __DIR__ . '/db.php';

function respond($success, $message)
{
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');
$course = strtoupper(trim($_POST['course'] ?? ''));

// Validate submitted details
if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !in_array($role, ['student', 'faculty', 'parent'], true) ||
    !preg_match('/^\d{6}$/', $otp) ||
    !in_array($course, ['BCA', 'MCA', 'BBA'], true)
) {
    respond(false, 'Invalid verification details.');
}

// Verify that the course matches the course used when requesting OTP
if (
    empty($_SESSION['otp_course']) ||
    $_SESSION['otp_course'] !== $course
) {
    respond(false, 'Selected course changed. Please request a new OTP.');
}

// Find latest OTP
$stmt = $conn->prepare(
    'SELECT id, otp_hash, expires_at, attempts
     FROM login_otps
     WHERE email = ? AND role = ?
     ORDER BY id DESC
     LIMIT 1'
);

$stmt->bind_param('ss', $email, $role);
$stmt->execute();

$result = $stmt->get_result();
$otpRecord = $result->fetch_assoc();

$stmt->close();

if (!$otpRecord) {
    respond(false, 'Request a new verification code.');
}

if ((int)$otpRecord['attempts'] >= 5) {
    respond(false, 'Too many attempts. Request a new code.');
}

if (strtotime($otpRecord['expires_at']) < time()) {
    respond(false, 'Code expired. Request a new one.');
}

if (!password_verify($otp, $otpRecord['otp_hash'])) {

    $stmt = $conn->prepare(
        'UPDATE login_otps
         SET attempts = attempts + 1
         WHERE id = ?'
    );

    $stmt->bind_param('i', $otpRecord['id']);
    $stmt->execute();
    $stmt->close();

    respond(false, 'Incorrect verification code.');
}

// Consume OTP so it cannot be reused
$stmt = $conn->prepare(
    'DELETE FROM login_otps WHERE id = ?'
);

$stmt->bind_param('i', $otpRecord['id']);
$stmt->execute();
$stmt->close();

// Regenerate session ID
session_regenerate_id(true);

// Store verified login details
$_SESSION['otp_verified_email'] = $email;
$_SESSION['otp_verified_role'] = $role;
$_SESSION['otp_verified_course'] = $course;
$_SESSION['otp_verified_at'] = time();

respond(true, 'Email verified successfully.');

?>
