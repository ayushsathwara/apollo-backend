
<?php
session_start();

header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

// Check student login
if (!isset($_SESSION["student_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Please log in as a student."
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

// Read submitted JSON
$input = json_decode(file_get_contents("php://input"), true);

$rating = filter_var(
    $input["rating"] ?? null,
    FILTER_VALIDATE_INT
);

$feedback = trim($input["feedback"] ?? "");

if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please select a rating from 1 to 5."
    ]);
    exit;
}

if (strlen($feedback) > 2000) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Feedback must be 2000 characters or less."
    ]);
    exit;
}

// Save feedback for the logged-in student
$studentId = (int) $_SESSION["student_id"];

$stmt = $conn->prepare(
    "INSERT INTO app_feedback (student_id, rating, feedback)
     VALUES (?, ?, ?)"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not prepare feedback."
    ]);
    exit;
}

$stmt->bind_param("iis", $studentId, $rating, $feedback);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Thanks! Your feedback has been submitted."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not save feedback. Please try again."
    ]);
}

$stmt->close();
$conn->close();
?>