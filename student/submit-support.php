
<?php
session_start();

header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (!isset($_SESSION["student_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Please log in as a student."
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

$category = trim($input["category"] ?? "");
$subject = trim($input["subject"] ?? "");
$message = trim($input["message"] ?? "");

$allowedCategories = [
    "login",
    "attendance",
    "assignments",
    "technical",
    "other"
];

if (
    !in_array($category, $allowedCategories, true) ||
    $subject === "" ||
    $message === ""
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please provide a valid category, subject, and message."
    ]);
    exit;
}

$studentId = (int) $_SESSION["student_id"];

$stmt = $conn->prepare(
    "INSERT INTO support_requests
        (student_id, subject, category, message)
     VALUES (?, ?, ?, ?)"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare support request."
    ]);
    exit;
}

$stmt->bind_param(
    "isss",
    $studentId,
    $subject,
    $category,
    $message
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Your support request has been submitted successfully.",
        "request_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to submit your support request."
    ]);
}

$stmt->close();
$conn->close();
?>