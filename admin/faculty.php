
<?php
session_start();
header("Content-Type: application/json");

include "../db.php";

// Check admin login
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Please log in as admin."
    ]);
    exit;
}

// Fetch all faculty members
$sql = "SELECT id, name, email, employee_id, department, created_at
        FROM faculty
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not load faculty members."
    ]);
    exit;
}

$facultyList = [];

while ($row = $result->fetch_assoc()) {
    $facultyList[] = $row;
}

echo json_encode([
    "success" => true,
    "faculty" => $facultyList
]);

$conn->close();
?>