
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

// Read faculty ID
$data = json_decode(file_get_contents("php://input"), true);
$facultyId = filter_var(
    $data["faculty_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$facultyId || $facultyId < 1) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid faculty ID is required."
    ]);
    exit;
}

// Check faculty exists
$check = $conn->prepare("SELECT id FROM faculty WHERE id = ?");
$check->bind_param("i", $facultyId);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    $check->close();
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Faculty member not found."
    ]);
    exit;
}
$check->close();

// Delete faculty
$stmt = $conn->prepare("DELETE FROM faculty WHERE id = ?");
$stmt->bind_param("i", $facultyId);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    echo json_encode([
        "success" => true,
        "message" => "Faculty deleted successfully."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not delete faculty."
    ]);
}

$stmt->close();
$conn->close();
?>