
<?php
session_start();

header("Content-Type: application/json");

require_once "../db.php";

if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);
    exit;
}

$sql = "SELECT id, name, email, roll_no, course, semester
        FROM students
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve students."
    ]);
    exit;
}

$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode([
    "success" => true,
    "students" => $students
]);

$conn->close();
?>