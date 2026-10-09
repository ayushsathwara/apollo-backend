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


$faculty_id = $_SESSION["faculty_id"];


/* Get subjects assigned to this faculty */

$sql = "SELECT subject
        FROM faculty_subjects
        WHERE faculty_id = ?
        ORDER BY subject ASC";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $faculty_id
);

$stmt->execute();

$result = $stmt->get_result();


$subjects = [];


while ($row = $result->fetch_assoc()) {

    $subjects[] = $row["subject"];

}


$stmt->close();

$conn->close();


echo json_encode([

    "status" => "success",

    "subjects" => $subjects

]);

?>