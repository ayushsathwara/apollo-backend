<?php

session_start();

include "../db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["student_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Not logged in"
    ]);

    exit;
}

$student_id = $_SESSION["student_id"];

$sql = "SELECT id, subject, attendance_date, status
        FROM attendance
        WHERE student_id = ?
        ORDER BY attendance_date DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $student_id);

$stmt->execute();

$result = $stmt->get_result();

$attendance = [];

while ($row = $result->fetch_assoc()) {
    $attendance[] = $row;
}

echo json_encode([
    "status" => "success",
    "attendance" => $attendance
]);

$stmt->close();
$conn->close();

?>