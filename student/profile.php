
<?php

header("Content-Type: application/json");

session_name('APOLLO_STUDENT_SESSION');
session_start();

include "../db.php";

// Check student login
if (!isset($_SESSION["student_id"])) {
    echo json_encode([
        "status" => "error",
        "message" => "Not logged in"
    ]);
    exit;
}

$student_id = $_SESSION["student_id"];

// Fetch student details
$sql = "SELECT id, name, roll_no, email, course, semester, profile_photo
        FROM students
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database query failed"
    ]);
    exit;
}

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $student = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "student" => $student
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Student not found"
    ]);
}

$stmt->close();
$conn->close();

?>
