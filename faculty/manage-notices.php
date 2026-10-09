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

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

$sql = "SELECT id, title, message, target_role, category, created_at
        FROM notices
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to fetch notices."
    ]);
    exit;
}

$notices = [];

while ($row = $result->fetch_assoc()) {
    $notices[] = $row;
}

echo json_encode([
    "success" => true,
    "notices" => $notices
]);

$conn->close();
?>
