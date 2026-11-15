
<?php

$role = strtolower(trim($_POST["role"] ?? ""));
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$course = strtoupper(trim($_POST["course"] ?? ""));

$sessionNames = [
    "student" => "APOLLO_STUDENT_SESSION",
    "faculty" => "APOLLO_FACULTY_SESSION",
    "parent"  => "APOLLO_PARENT_SESSION"
];

if (!isset($sessionNames[$role])) {
    http_response_code(400);
    exit("Invalid role.");
}

if ($email === "" || $password === "" || $course === "") {
    http_response_code(400);
    exit("Email, password and course are required.");
}

session_name($sessionNames[$role]);
session_start();

// Check OTP verification proof
$verifiedEmail = $_SESSION["otp_verified_email"] ?? "";
$verifiedRole = $_SESSION["otp_verified_role"] ?? "";
$verifiedCourse = $_SESSION["otp_verified_course"] ?? "";
$verifiedAt = $_SESSION["otp_verified_at"] ?? 0;
$accountId = (int)($_SESSION["otp_account_id"] ?? 0);

if (
    $verifiedEmail !== $email ||
    $verifiedRole !== $role ||
    $verifiedCourse !== $course ||
    $accountId <= 0 ||
    !$verifiedAt ||
    (time() - $verifiedAt) > 300
) {
    http_response_code(401);
    exit("OTP verification required. Please request a new OTP.");
}

// Consume OTP proof
unset(
    $_SESSION["otp_verified_email"],
    $_SESSION["otp_verified_role"],
    $_SESSION["otp_verified_course"],
    $_SESSION["otp_verified_at"],
    $_SESSION["otp_course"],
    $_SESSION["otp_account_id"],
    $_SESSION["otp_email"],
    $_SESSION["otp_role"]
);

require_once "../db.php";

$account = null;
$stmt = null;

// Find the exact account verified during OTP request
if ($role === "student") {

    $stmt = $conn->prepare(
        "SELECT id FROM students
         WHERE id = ? AND email = ? AND course = ? LIMIT 1"
    );

    $stmt->bind_param("iss", $accountId, $email, $course);
} elseif ($role === "faculty") {

    $stmt = $conn->prepare(
        "SELECT id FROM faculty
         WHERE id = ? AND email = ? AND department = ? LIMIT 1"
    );

    $stmt->bind_param("iss", $accountId, $email, $course);
} elseif ($role === "parent") {

    $stmt = $conn->prepare(
        "SELECT p.id
         FROM parents p
         INNER JOIN students s ON p.student_id = s.id
         WHERE p.id = ? AND p.email = ? AND s.course = ?
         LIMIT 1"
    );

    $stmt->bind_param("iss", $accountId, $email, $course);
}

if (!$stmt || !$stmt->execute()) {
    http_response_code(500);
    exit("Database error. Please try again.");
}

$result = $stmt->get_result();
$account = $result->fetch_assoc();
$stmt->close();

if (!$account) {
    http_response_code(401);
    exit("Account or selected course does not match.");
}

// Successful login
session_regenerate_id(true);

if ($role === "student") {
    $_SESSION["student_id"] = $account["id"];
} elseif ($role === "faculty") {
    $_SESSION["faculty_id"] = $account["id"];
} elseif ($role === "parent") {
    $_SESSION["parent_id"] = $account["id"];
}

$conn->close();

echo "Login successful!";

?>
