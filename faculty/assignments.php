<?php

session_start();

include "../db.php";

header("Content-Type: application/json");


/* ================================
   CHECK FACULTY LOGIN
================================ */

if (!isset($_SESSION["faculty_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Faculty not logged in"
    ]);

    exit;
}


$faculty_id = $_SESSION["faculty_id"];


/* ================================
   GET FACULTY ASSIGNMENTS
================================ */

$sql = "
    SELECT
        id,
        subject,
        title,
        description,
        due_date,
        file_name,
        file_path
    FROM assignments
    WHERE faculty_id = ?
    ORDER BY due_date ASC, id DESC
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database query failed."
    ]);

    $conn->close();

    exit;
}


$stmt->bind_param(
    "i",
    $faculty_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$assignments = [];


while ($row = $result->fetch_assoc()) {

    $assignments[] = $row;

}


$stmt->close();

$conn->close();


/* ================================
   RESPONSE
================================ */

echo json_encode([

    "status" => "success",

    "assignments" =>
        $assignments

]);

?>