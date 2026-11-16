
<?php
session_start();

header("Content-Type: application/json");

require_once "../db.php";

// Check admin session
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);
    exit;
}

// Allow POST only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
    exit;
}

// Read JSON data
$data = json_decode(file_get_contents("php://input"), true);

$student_id = filter_var(
    $data["student_id"] ?? null,
    FILTER_VALIDATE_INT
);

$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");
$phone = trim($data["phone"] ?? "");
$password = $data["password"] ?? "";

// Validate input
if (
    !$student_id ||
    $name === "" ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($password) < 8
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter valid details. Password must be at least 8 characters."
    ]);
    exit;
}

// Validate phone if provided
if ($phone !== "" && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter a valid phone number."
    ]);
    exit;
}

// Check whether student exists
$studentCheck = $conn->prepare(
    "SELECT id FROM students WHERE id = ? LIMIT 1"
);

$studentCheck->bind_param("i", $student_id);
$studentCheck->execute();

$studentResult = $studentCheck->get_result();

if ($studentResult->num_rows === 0) {
    $studentCheck->close();

    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Selected student does not exist."
    ]);
    exit;
}

$studentCheck->close();

// Check whether student already has a parent account
$parentCheck = $conn->prepare(
    "SELECT id FROM parents WHERE student_id = ? LIMIT 1"
);

$parentCheck->bind_param("i", $student_id);
$parentCheck->execute();

$parentResult = $parentCheck->get_result();

if ($parentResult->num_rows > 0) {
    $parentCheck->close();

    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "This student already has a parent account."
    ]);
    exit;
}

$parentCheck->close();

// Check whether parent email already exists
$emailCheck = $conn->prepare(
    "SELECT id FROM parents WHERE email = ? LIMIT 1"
);

$emailCheck->bind_param("s", $email);
$emailCheck->execute();

$emailResult = $emailCheck->get_result();

if ($emailResult->num_rows > 0) {
    $emailCheck->close();

    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "This parent email is already registered."
    ]);
    exit;
}

$emailCheck->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert parent account
$stmt = $conn->prepare(
    "INSERT INTO parents
    (student_id, name, email, password, phone)
    VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "issss",
    $student_id,
    $name,
    $email,
    $hashedPassword,
    $phone
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Parent account created successfully.",
        "parent_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not create parent account."
    ]);
}

$stmt->close();
$conn->close();
?>
