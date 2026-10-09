<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";

// Check parent login
if (!isset($_SESSION["parent_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Parent login required"
    ]);
    exit;
}

// Fetch notices for parents and everyone
$sql = "SELECT id, title, message, posted_by, created_at
        FROM notices
        WHERE target_role IN ('all', 'parent')
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to fetch notices"
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