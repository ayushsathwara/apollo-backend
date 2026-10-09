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


/* Get data */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


$subject = $data["subject"] ?? "";
$attendance_date = $data["attendance_date"] ?? "";
$attendance = $data["attendance"] ?? [];


/* Validate */

if ($subject === "" || $attendance_date === "") {

    echo json_encode([
        "status" => "error",
        "message" => "Subject and date are required."
    ]);

    exit;
}


if (empty($attendance)) {

    echo json_encode([
        "status" => "error",
        "message" => "Please mark attendance."
    ]);

    exit;
}
/* Check faculty subject permission */

$faculty_id = $_SESSION["faculty_id"];

$permission_sql = "
    SELECT id
    FROM faculty_subjects
    WHERE faculty_id = ?
    AND subject = ?
";

$permission_stmt =
    $conn->prepare($permission_sql);

$permission_stmt->bind_param(
    "is",
    $faculty_id,
    $subject
);

$permission_stmt->execute();

$permission_result =
    $permission_stmt->get_result();


if ($permission_result->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "message" => "You are not authorized to mark attendance for this subject."
    ]);

    $permission_stmt->close();
    $conn->close();

    exit;
}


$permission_stmt->close();


/* Prepare insert */

$sql = "INSERT INTO attendance
        (student_id, subject, attendance_date, status)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        status = VALUES(status)";

$stmt = $conn->prepare($sql);


/* Save attendance */

foreach ($attendance as $record) {

    $student_id = $record["student_id"];
    $status = $record["status"];

    $stmt->bind_param(
        "isss",
        $student_id,
        $subject,
        $attendance_date,
        $status
    );

    $stmt->execute();

}


$stmt->close();

$conn->close();


echo json_encode([
    "status" => "success",
    "message" => "Attendance saved successfully!"
]);

?>