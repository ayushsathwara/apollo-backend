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
   GET ASSIGNMENT ID
================================ */

$assignment_id =
    intval($_GET["assignment_id"] ?? 0);


if ($assignment_id <= 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid assignment."
    ]);

    exit;
}


/* ================================
   CHECK FACULTY ASSIGNMENT
================================ */

$sql = "
    SELECT
        id,
        subject,
        title,
        due_date
    FROM assignments
    WHERE id = ?
    AND faculty_id = ?
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Assignment query failed."
    ]);

    exit;
}


$stmt->bind_param(
    "ii",
    $assignment_id,
    $faculty_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Assignment not found or access denied."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$assignment =
    $result->fetch_assoc();


$stmt->close();


/* ================================
   GET STUDENTS
================================ */

$sql = "
    SELECT
        s.id,
        s.name,
        s.roll_no,

        sub.id AS submission_id,
        sub.file_name,
        sub.file_path,
        sub.submitted_at

    FROM students s

    LEFT JOIN assignment_submissions sub
        ON s.id = sub.student_id
        AND sub.assignment_id = ?

    ORDER BY s.roll_no ASC
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Student query failed."
    ]);

    $conn->close();

    exit;
}


$stmt->bind_param(
    "i",
    $assignment_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$students = [];

$submitted = 0;
$pending = 0;


while ($row = $result->fetch_assoc()) {

    if ($row["submission_id"] !== null) {

        $status = "Submitted";

        $submitted++;

    } else {

        $status = "Pending";

        $pending++;

    }


    $students[] = [

        "id" =>
            $row["id"],

        "name" =>
            $row["name"],

        "roll_no" =>
            $row["roll_no"],

        "status" =>
            $status,

        "file_name" =>
            $row["file_name"],

        "file_path" =>
            $row["file_path"],

        "submitted_at" =>
            $row["submitted_at"]

    ];

}


$stmt->close();

$conn->close();


/* ================================
   RESPONSE
================================ */

echo json_encode([

    "status" => "success",

    "assignment" =>
        $assignment,

    "total_students" =>
        count($students),

    "submitted" =>
        $submitted,

    "pending" =>
        $pending,

    "students" =>
        $students

]);

?>