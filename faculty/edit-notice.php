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
$title = trim($input["title"] ?? "");
$message = trim($input["message"] ?? "");
$target_role = $input["target_role"] ?? "";
$category = $input["category"] ?? "";

if (!$id || $title === "" || $message === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid notice ID, title and message are required."
    ]);
    exit;
}

$allowed_roles = ["all", "student", "parent", "faculty"];
$allowed_categories = ["academic", "exam", "event"];

if (
    !in_array($target_role, $allowed_roles, true) ||
    !in_array($category, $allowed_categories, true)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid audience or category."
    ]);
    exit;
}

$faculty_id = (int) $_SESSION["faculty_id"];

// Verify that this faculty member published the notice.
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

// Match the logged-in faculty's name to the notice publisher.
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
        "message" => "You can only edit notices you published."
    ]);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE notices
     SET title = ?, message = ?, target_role = ?, category = ?
     WHERE id = ?"
);

$stmt->bind_param(
    "ssssi",
    $title,
    $message,
    $target_role,
    $category,
    $id
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Notice updated successfully."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to update notice."
    ]);
}

$stmt->close();
$conn->close();
?>
