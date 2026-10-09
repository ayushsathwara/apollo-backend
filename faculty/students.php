<?php

session_start();

include "../db.php";

header("Content-Type: application/json");


/* Check faculty login */

if (!isset($_SESSION["faculty_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Faculty not logged in"
    ]);

    exit;
}


/* Get all students */

$sql = "SELECT id, name, roll_no, email, course, semester
        FROM students
        ORDER BY roll_no ASC";

$result = $conn->query($sql);

$students = [];


while ($row = $result->fetch_assoc()) {

    $students[] = $row;

}


echo json_encode([
    "status" => "success",
    "students" => $students
]);


$conn->close();

?>