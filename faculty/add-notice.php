<?php
session_start();

header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";

// Allow only logged-in faculty
if (!isset($_SESSION["faculty_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please log in as faculty first."
    ]);

    exit;
}

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);

    exit;
}

// Read JSON sent by JavaScript
$input = json_decode(file_get_contents("php://input"), true);

$title = trim($input["title"] ?? "");
$message = trim($input["message"] ?? "");
$target_role = $input["target_role"] ?? "all";
$category = $input["category"] ?? "academic";

// Validate fields
if ($title === "" || $message === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Title and message are required."
    ]);

    exit;
}

// Validate audience
$allowed_roles = ["all", "student", "parent", "faculty"];

if (!in_array($target_role, $allowed_roles, true)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid notice audience."
    ]);

    exit;
}

$allowed_categories = ["academic", "exam", "event"];

if (!in_array($category, $allowed_categories, true)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid notice category."
    ]);
    exit;
}

// Fetch logged-in faculty name
$faculty_id = (int) $_SESSION["faculty_id"];

$stmt = $conn->prepare(
    "SELECT name FROM faculty WHERE id = ?"
);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to verify faculty."
    ]);

    exit;
}

$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$stmt->bind_result($faculty_name);

$faculty_found = $stmt->fetch();
$stmt->close();

if (!$faculty_found) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Faculty account not found."
    ]);

    exit;
}

// Save notice to database
$stmt = $conn->prepare(
    "INSERT INTO notices (title, message, posted_by, target_role, category)
     VALUES (?, ?, ?, ?, ?)"
);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare notice."
    ]);

    exit;
}

$stmt->bind_param(
    "sssss",
    $title,
    $message,
    $faculty_name,
    $target_role,
    $category
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Notice published successfully.",
        "notice_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to publish notice."
    ]);
}

$stmt->close();
$conn->close();
