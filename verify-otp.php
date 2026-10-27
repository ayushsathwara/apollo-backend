
<?php

$role = trim($_POST['role'] ?? '');

$sessionNames = [
    'student' => 'APOLLO_STUDENT_SESSION',
    'faculty' => 'APOLLO_FACULTY_SESSION',
    'parent'  => 'APOLLO_PARENT_SESSION'
];

if (!isset($sessionNames[$role])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role.'
    ]);
    exit;
}

session_name($sessionNames[$role]);
session_start();

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

function respond($success, $message) {
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$role  = trim($_POST['role'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !in_array($role, ['student', 'faculty', 'parent'], true) ||
    !preg_match('/^\d{6}$/', $otp)) {
    respond(false, 'Invalid verification details.');
}

// Find the latest OTP for this email and role.
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
        'UPDATE login_otps SET attempts = attempts + 1 WHERE id = ?'
    );
    $stmt->bind_param('i', $otpRecord['id']);
    $stmt->execute();
    $stmt->close();

    respond(false, 'Incorrect verification code.');
}

// OTP is valid: consume it so it cannot be reused.
$stmt = $conn->prepare('DELETE FROM login_otps WHERE id = ?');
$stmt->bind_param('i', $otpRecord['id']);
$stmt->execute();
$stmt->close();

// Store a short-lived proof that the email OTP was verified.
// The existing login endpoint must validate and consume this proof
// before granting dashboard access.
session_regenerate_id(true);
$_SESSION['otp_verified_email'] = $email;
$_SESSION['otp_verified_role'] = $role;
$_SESSION['otp_verified_at'] = time();

respond(true, 'Email verified successfully.');
