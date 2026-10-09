
<?php
session_start();
header("Content-Type: application/json");

include "../db.php";

// Check admin session
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Please log in as admin."
    ]);
    exit;
}

// Read JSON data
$data = json_decode(file_get_contents("php://input"), true);

$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");
$employeeId = trim($data["employee_id"] ?? "");
$department = trim($data["department"] ?? "");
$password = $data["password"] ?? "";

// Validate fields
if (
    $name === "" ||
    $email === "" ||
    $employeeId === "" ||
    $department === "" ||
    $password === ""
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "All fields are required."
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter a valid email address."
    ]);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 8 characters."
    ]);
    exit;
}

// Check whether email or employee ID already exists
$check = $conn->prepare(
    "SELECT id FROM faculty WHERE email = ? OR employee_id = ?"
);
$check->bind_param("ss", $email, $employeeId);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $check->close();
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "Email or employee ID already exists."
    ]);
    exit;
}
$check->close();

// Hash password before storing it
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert faculty
$stmt = $conn->prepare(
    "INSERT INTO faculty (name, email, password, employee_id, department)
     VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "sssss",
    $name,
    $email,
    $hashedPassword,
    $employeeId,
    $department
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Faculty added successfully.",
        "faculty_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not add faculty."
    ]);
}

$stmt->close();
$conn->close();
?>