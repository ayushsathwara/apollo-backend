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

$subject = $_GET["subject"] ?? "";

$date = $_GET["date"] ?? "";


/*
    Get only subjects assigned
    to the logged-in faculty
*/

$subject_sql = "
    SELECT subject
    FROM faculty_subjects
    WHERE faculty_id = ?
";

$subject_stmt =
    $conn->prepare($subject_sql);

$subject_stmt->bind_param(
    "i",
    $faculty_id
);

$subject_stmt->execute();

$subject_result =
    $subject_stmt->get_result();


$allowed_subjects = [];

while ($row =
    $subject_result->fetch_assoc()) {

    $allowed_subjects[] =
        $row["subject"];

}

$subject_stmt->close();


/* No subjects assigned */

if (empty($allowed_subjects)) {

    echo json_encode([
        "status" => "success",

        "report" => [],

        "summary" => [
            "total_students" => 0,
            "total_records" => 0,
            "total_present" => 0,
            "total_absent" => 0
        ]
    ]);

    $conn->close();

    exit;
}


/*
    Check subject permission
*/

if ($subject !== "") {

    if (!in_array(
        $subject,
        $allowed_subjects
    )) {

        echo json_encode([
            "status" => "error",

            "message" =>
                "You are not authorized to view this subject."
        ]);

        $conn->close();

        exit;
    }

}


/*
    Build placeholders
*/

$placeholders =
    implode(
        ",",
        array_fill(
            0,
            count($allowed_subjects),
            "?"
        )
    );


/*
    Base query
*/

$sql = "
    SELECT

        s.name,

        s.roll_no,

        a.subject,

        SUM(
            a.status = 'Present'
        ) AS present,

        SUM(
            a.status = 'Absent'
        ) AS absent,

        COUNT(*) AS total

    FROM attendance a

    INNER JOIN students s
    ON a.student_id = s.id

    WHERE a.subject IN ($placeholders)
";


/*
    Specific subject
*/

if ($subject !== "") {

    $sql .= "
        AND a.subject = ?
    ";

}


/*
    Specific date
*/

if ($date !== "") {

    $sql .= "
        AND a.attendance_date = ?
    ";

}


/*
    Group report
*/

$sql .= "

    GROUP BY

        a.student_id,
        a.subject

    ORDER BY

        s.roll_no ASC

";


$stmt =
    $conn->prepare($sql);


/*
    Bind parameters
*/

$types =
    str_repeat(
        "s",
        count($allowed_subjects)
    );

$params =
    $allowed_subjects;


/* Add subject */

if ($subject !== "") {

    $types .= "s";

    $params[] =
        $subject;

}


/* Add date */

if ($date !== "") {

    $types .= "s";

    $params[] =
        $date;

}


/*
    Bind dynamically
*/

$bind_params = [];

$bind_params[] =
    $types;


foreach (
    $params as $key => $value
) {

    $bind_params[] =
        &$params[$key];

}


call_user_func_array(
    [$stmt, "bind_param"],
    $bind_params
);


/* Execute */

$stmt->execute();

$result =
    $stmt->get_result();


$report = [];

$totalPresent = 0;

$totalAbsent = 0;

$totalRecords = 0;

$students = [];


/*
    Process report
*/

while (
    $row =
    $result->fetch_assoc()
) {

    $present =
        (int)$row["present"];

    $absent =
        (int)$row["absent"];

    $total =
        (int)$row["total"];


    $percentage = 0;


    if ($total > 0) {

        $percentage =
            round(
                ($present / $total) * 100,
                2
            );

    }


    $row["present"] =
        $present;

    $row["absent"] =
        $absent;

    $row["total"] =
        $total;

    $row["percentage"] =
        $percentage;


    $report[] =
        $row;


    $totalPresent +=
        $present;

    $totalAbsent +=
        $absent;

    $totalRecords +=
        $total;


    $students[
        $row["roll_no"]
    ] = true;

}


$stmt->close();

$conn->close();


/*
    Send response
*/

echo json_encode([

    "status" =>
        "success",

    "report" =>
        $report,

    "summary" => [

        "total_students" =>
            count($students),

        "total_records" =>
            $totalRecords,

        "total_present" =>
            $totalPresent,

        "total_absent" =>
            $totalAbsent

    ]

]);

?>