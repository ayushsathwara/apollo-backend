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

// Fetch student feedback
$sql = "SELECT
            f.id,
            f.student_id,
            s.name AS student_name,
            s.roll_no,
            f.rating,
            f.feedback,
            f.created_at
        FROM app_feedback f
        LEFT JOIN students s ON f.student_id = s.id
        ORDER BY f.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not load student feedback."
    ]);
    exit;
}

$feedbackList = [];

while ($row = $result->fetch_assoc()) {
    $feedbackList[] = $row;
}

echo json_encode([
    "success" => true,
    "feedback" => $feedbackList
]);

$conn->close();
?>
