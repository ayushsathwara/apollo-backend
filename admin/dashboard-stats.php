
<?php
session_start();
header("Content-Type: application/json");

include "../db.php";

// Only logged-in admins can access these statistics
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Please log in as admin."
    ]);
    exit;
}

// Count total students
$studentQuery = $conn->query("SELECT COUNT(*) AS total FROM students");

if (!$studentQuery) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not count students."
    ]);
    exit;
}

$totalStudents = (int) $studentQuery->fetch_assoc()["total"];

// Count total faculty members
$facultyQuery = $conn->query("SELECT COUNT(*) AS total FROM faculty");

if (!$facultyQuery) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not count faculty members."
    ]);
    exit;
}

$totalFaculty = (int) $facultyQuery->fetch_assoc()["total"];

// Return both counts
echo json_encode([
    "success" => true,
    "total_students" => $totalStudents,
    "total_faculty" => $totalFaculty
]);

$conn->close();
?>