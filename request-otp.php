
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
require_once __DIR__ . '/mail-config.php';


header('Content-Type: application/json');

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
$role = trim($_POST['role'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    respond(false, 'Enter a valid email and password.');
}

$tables = [
    'student' => 'students',
    'faculty' => 'faculty',
    'parent' => 'parents'
];

if (!isset($tables[$role])) {
    respond(false, 'Invalid role.');
}

$table = $tables[$role];

// Check account and password before sending OTP
$stmt = $conn->prepare(
    "SELECT id, password FROM `$table` WHERE email = ? LIMIT 1"
);

$stmt->bind_param('s', $email);
$stmt->execute();

$result = $stmt->get_result();
$account = $result->fetch_assoc();
$stmt->close();

if (!$account || !password_verify($password, $account['password'])) {
    respond(false, 'Invalid email or password.');
}

// Generate a secure six-digit OTP
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

// Save the new OTP hash
$stmt = $conn->prepare(
    'INSERT INTO login_otps (email, role, otp_hash, expires_at)
     VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('ssss', $email, $role, $otpHash, $expiresAt);
$stmt->execute();
$stmt->close();

error_log("OTP CHECK 2: Account verified, OTP saved. Starting email.");

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

    error_log("OTP CHECK 3: " . $e->getMessage());

    $stmt = $conn->prepare(
        'DELETE FROM login_otps WHERE email = ? AND role = ?'
    );
    $stmt->bind_param('ss', $email, $role);
    $stmt->execute();
    $stmt->close();

    respond(false, 'Could not send the code. Please try again later.');
}

?>