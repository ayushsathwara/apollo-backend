<?php

session_start();

include "../db.php";

if (!isset($_SESSION["faculty_id"])) {

    die("Faculty not logged in.");

}

$faculty_id = $_SESSION["faculty_id"];

$assignment_id =
    intval($_GET["assignment_id"] ?? 0);

$student_id =
    intval($_GET["student_id"] ?? 0);


if ($assignment_id <= 0 || $student_id <= 0) {

    die("Invalid request.");

}


/* ================================
   GET SUBMISSION
================================ */

$sql = "
    SELECT
        sub.file_name,
        sub.file_path

    FROM assignment_submissions sub

    INNER JOIN assignments a
        ON sub.assignment_id = a.id

    WHERE sub.assignment_id = ?
    AND sub.student_id = ?
    AND a.faculty_id = ?
";


$stmt =
    $conn->prepare($sql);


$stmt->bind_param(
    "iii",
    $assignment_id,
    $student_id,
    $faculty_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    die("Submission not found.");

}


$row =
    $result->fetch_assoc();


$stmt->close();

$conn->close();


/* ================================
   FILE PATH
================================ */

$filePath =
    dirname(__DIR__) .
    "/" .
    $row["file_path"];


if (!file_exists($filePath)) {

    die("Uploaded file not found on server.");

}


/* ================================
   DOWNLOAD
================================ */

$fileName =
    basename($row["file_name"]);


header(
    "Content-Description: File Transfer"
);

header(
    "Content-Type: application/octet-stream"
);

header(
    "Content-Disposition: attachment; filename=\"" .
    $fileName .
    "\""
);

header(
    "Content-Length: " .
    filesize($filePath)
);

readfile($filePath);

exit;

?>