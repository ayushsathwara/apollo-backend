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


/* Get faculty information */

$sql = "SELECT id, name, email, employee_id, department
        FROM faculty
        WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $faculty_id);

$stmt->execute();

$result = $stmt->get_result();


/* Faculty found */

if ($result->num_rows === 1) {

    $faculty = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "faculty" => $faculty
    ]);

}


/* Faculty not found */

else {

    echo json_encode([
        "status" => "error",
        "message" => "Faculty not found"
    ]);

}


$stmt->close();

$conn->close();

?>