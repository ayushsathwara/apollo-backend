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
   GET DATA
================================ */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


$subject = trim($data["subject"] ?? "");
$start_time = trim($data["start_time"] ?? "");
$end_time = trim($data["end_time"] ?? "");
$room = trim($data["room"] ?? "");


/* ================================
   VALIDATION
================================ */

if (
    $subject === "" ||
    $start_time === "" ||
    $end_time === "" ||
    $room === ""
) {

    echo json_encode([
        "status" => "error",
        "message" => "All fields are required."
    ]);

    exit;
}


/* ================================
   CHECK TIME
================================ */

if ($start_time >= $end_time) {

    echo json_encode([
        "status" => "error",
        "message" => "End time must be after start time."
    ]);

    exit;
}


/* ================================
   CHECK FACULTY SUBJECT
================================ */

$sql = "
    SELECT id
    FROM faculty_subjects
    WHERE faculty_id = ?
    AND subject = ?
";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $faculty_id,
    $subject
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "message" => "You are not assigned to this subject."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}

$stmt->close();


/* ================================
   TODAY'S DATE
================================ */

$class_date = date("Y-m-d");


/* ================================
   INSERT CLASS
================================ */

$sql = "
    INSERT INTO today_classes
    (
        faculty_id,
        subject,
        class_date,
        start_time,
        end_time,
        room
    )
    VALUES (?, ?, ?, ?, ?, ?)
";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "isssss",
    $faculty_id,
    $subject,
    $class_date,
    $start_time,
    $end_time,
    $room
);


if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Today's class added successfully!"
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to add class."
    ]);

}


$stmt->close();
$conn->close();

?>