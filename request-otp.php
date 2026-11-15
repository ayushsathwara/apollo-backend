
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
require_once __DIR__ . '/mail-config.php';

function respond($success, $message)
{
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$course = strtoupper(trim($_POST['course'] ?? ''));

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $password === '' ||
    !in_array($course, ['BCA', 'MCA', 'BBA'], true)
) {
    respond(false, 'Enter valid login details and select a course.');
}

// Check account according to role and selected course
if ($role === 'student') {

    $stmt = $conn->prepare(
        "SELECT id, password FROM students
         WHERE email = ? AND course = ? LIMIT 1"
    );

    $stmt->bind_param('ss', $email, $course);
} elseif ($role === 'faculty') {

    $stmt = $conn->prepare(
        "SELECT id, password FROM faculty
         WHERE email = ? AND department = ? LIMIT 1"
    );

    $stmt->bind_param('ss', $email, $course);
} else {

    $stmt = $conn->prepare(
        "SELECT p.id, p.password
         FROM parents p
         INNER JOIN students s ON p.student_id = s.id
         WHERE p.email = ? AND s.course = ?
         LIMIT 1"
    );

    $stmt->bind_param('ss', $email, $course);
}

if (!$stmt) {
    respond(false, 'Database error. Please try again later.');
}

$stmt->execute();

$result = $stmt->get_result();
$account = $result->fetch_assoc();

$stmt->close();

if (!$account || !password_verify($password, $account['password'])) {
    respond(false, 'Invalid email, password or selected course.');
}

// Store verified account details in session
$_SESSION['otp_course'] = $course;
$_SESSION['otp_account_id'] = (int)$account['id'];
$_SESSION['otp_email'] = $email;
$_SESSION['otp_role'] = $role;

// Generate secure six-digit OTP
$otp = (string) random_int(100000, 999999);
$otpHash = password_hash($otp, PASSWORD_DEFAULT);
$expiresAt = date('Y-m-d H:i:s', time() + 300);

// Remove previous OTP for this email and role
$stmt = $conn->prepare(
    'DELETE FROM login_otps WHERE email = ? AND role = ?'
);

$stmt->bind_param('ss', $email, $role);
$stmt->execute();
$stmt->close();

// Save new OTP hash
$stmt = $conn->prepare(
    'INSERT INTO login_otps (email, role, otp_hash, expires_at)
     VALUES (?, ?, ?, ?)'
);

$stmt->bind_param('ssss', $email, $role, $otpHash, $expiresAt);

if (!$stmt->execute()) {
    $stmt->close();
    respond(false, 'Could not generate verification code.');
}

$stmt->close();

try {

    $mail = createMailer();
    $mail->addAddress($email);
    $mail->Subject = 'Your Apollo Attendance verification code';

    $mail->Body = '
        <h2>Apollo Attendance</h2>
        <p>Your verification code is:</p>
        <h1 style="letter-spacing:5px">' .
        htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') .
        '</h1>
        <p>This code expires in 5 minutes. Do not share it.</p>
    ';

    $mail->AltBody =
        "Your Apollo Attendance verification code is $otp. It expires in 5 minutes.";

    $mail->send();

    respond(true, 'OTP sent. Enter the code from your email, then click LOGIN.');
} catch (Throwable $e) {

    error_log("OTP email error: " . $e->getMessage());

    $stmt = $conn->prepare(
        'DELETE FROM login_otps WHERE email = ? AND role = ?'
    );

    $stmt->bind_param('ss', $email, $role);
    $stmt->execute();
    $stmt->close();

    respond(false, 'Could not send the code. Please try again later.');
}

?>
