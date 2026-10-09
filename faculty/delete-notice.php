<?php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (!isset($_SESSION["faculty_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Please log in as faculty."
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$id = filter_var($input["id"] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid notice ID is required."
    ]);
    exit;
}

$faculty_id = (int) $_SESSION["faculty_id"];

// Get the publisher of this notice.
$stmt = $conn->prepare(
    "SELECT posted_by FROM notices WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($posted_by);
$notice_found = $stmt->fetch();
$stmt->close();

if (!$notice_found) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Notice not found."
    ]);
    exit;
}

// Verify the logged-in faculty member.
$stmt = $conn->prepare(
    "SELECT name FROM faculty WHERE id = ?"
);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$stmt->bind_result($faculty_name);
$faculty_found = $stmt->fetch();
$stmt->close();

if (!$faculty_found || $posted_by !== $faculty_name) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "You can only delete notices you published."
    ]);
    exit;
}

// Delete the notice.
$stmt = $conn->prepare(
    "DELETE FROM notices WHERE id = ?"
);
$stmt->bind_param("i", $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode([
        "success" => true,
        "message" => "Notice deleted successfully."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to delete notice."
    ]);
}

$stmt->close();
$conn->close();
?>
