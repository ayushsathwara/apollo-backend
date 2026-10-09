<?php
session_start();
header("Content-Type: application/json");

include "../db.php";

// Verify admin login
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Please log in as admin."
    ]);
    exit;
}

// Read JSON request
$data = json_decode(file_get_contents("php://input"), true);
$studentId = filter_var(
    $data["student_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$studentId || $studentId < 1) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid student ID is required."
    ]);
    exit;
}

// Check whether the student exists
$check = $conn->prepare("SELECT id FROM students WHERE id = ?");
$check->bind_param("i", $studentId);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    $check->close();
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Student not found."
    ]);
    exit;
}

$check->close();

// Delete the student
$stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
$stmt->bind_param("i", $studentId);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    echo json_encode([
        "success" => true,
        "message" => "Student deleted successfully."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not delete student."
    ]);
}

$stmt->close();
$conn->close();
?>