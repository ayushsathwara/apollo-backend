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


/* Get JSON data */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


$id =
    (int)($data["id"] ?? 0);

$name =
    trim($data["name"] ?? "");

$roll_no =
    trim($data["roll_no"] ?? "");

$email =
    trim($data["email"] ?? "");

$course =
    trim($data["course"] ?? "");

$semester =
    (int)($data["semester"] ?? 0);


/* Validate */

if (
    $id === 0 ||
    $name === "" ||
    $roll_no === "" ||
    $email === "" ||
    $course === "" ||
    $semester === 0
) {

    echo json_encode([
        "status" => "error",
        "message" => "All fields are required."
    ]);

    exit;
}


/* Update student */

$sql = "
    UPDATE students
    SET
        name = ?,
        roll_no = ?,
        email = ?,
        course = ?,
        semester = ?
    WHERE id = ?
";


$stmt =
    $conn->prepare($sql);


$stmt->bind_param(
    "ssssii",
    $name,
    $roll_no,
    $email,
    $course,
    $semester,
    $id
);


if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Student updated successfully!"
    ]);

}

else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to update student."
    ]);

}


$stmt->close();

$conn->close();

?>