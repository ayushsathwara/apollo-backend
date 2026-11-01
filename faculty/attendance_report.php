
<?php

session_name('APOLLO_FACULTY_SESSION');
session_start();

include "../db.php";

header("Content-Type: application/json");


/* CHECK FACULTY LOGIN */

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

$from_date = $_GET["from_date"] ?? "";
$to_date = $_GET["to_date"] ?? "";


/* VALIDATE DATE RANGE */

if (($from_date === "") !== ($to_date === "")) {

    echo json_encode([
        "status" => "error",
        "message" => "Please select both From Date and To Date."
    ]);

    exit;
}

function validDate($date) {

    $d = DateTime::createFromFormat("!Y-m-d", $date);

    return $d && $d->format("Y-m-d") === $date;
}

if ($date !== "" && !validDate($date)) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid attendance date."
    ]);

    exit;
}

if ($from_date !== "" && $to_date !== "") {

    if (!validDate($from_date) || !validDate($to_date)) {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid date range."
        ]);

        exit;
    }

    if ($from_date > $to_date) {

        echo json_encode([
            "status" => "error",
            "message" => "From Date cannot be after To Date."
        ]);

        exit;
    }

    if ($date !== "") {

        echo json_encode([
            "status" => "error",
            "message" => "Use either a single date or a date range."
        ]);

        exit;
    }
}


/* GET FACULTY ASSIGNED SUBJECTS */

$subject_sql = "
    SELECT subject
    FROM faculty_subjects
    WHERE faculty_id = ?
";

$subject_stmt = $conn->prepare($subject_sql);

$subject_stmt->bind_param("i", $faculty_id);

$subject_stmt->execute();

$subject_result = $subject_stmt->get_result();

$allowed_subjects = [];

while ($row = $subject_result->fetch_assoc()) {

    $allowed_subjects[] = $row["subject"];

}

$subject_stmt->close();


/* NO SUBJECTS ASSIGNED */

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


/* CHECK SUBJECT PERMISSION */

if ($subject !== "" && !in_array($subject, $allowed_subjects, true)) {

    echo json_encode([
        "status" => "error",
        "message" => "You are not authorized to view this subject."
    ]);

    $conn->close();

    exit;
}


/* BUILD SUBJECT PLACEHOLDERS */

$placeholders = implode(
    ",",
    array_fill(0, count($allowed_subjects), "?")
);


/* BASE QUERY */

$sql = "
    SELECT

        s.name,
        s.roll_no,
        a.subject,

        SUM(a.status = 'Present') AS present,

        SUM(a.status = 'Absent') AS absent,

        COUNT(*) AS total

    FROM attendance a

    INNER JOIN students s
    ON a.student_id = s.id

    WHERE a.subject IN ($placeholders)
";


/* SPECIFIC SUBJECT */

if ($subject !== "") {

    $sql .= " AND a.subject = ? ";

}


/* SINGLE DATE FILTER */

if ($date !== "") {

    $sql .= " AND a.attendance_date = ? ";

}


/* DATE RANGE FILTER */

if ($from_date !== "" && $to_date !== "") {

    $sql .= "
        AND a.attendance_date BETWEEN ? AND ?
    ";

}


/* GROUP REPORT */

$sql .= "

    GROUP BY

        a.student_id,
        a.subject

    ORDER BY

        s.roll_no ASC

";


$stmt = $conn->prepare($sql);


/* BIND PARAMETERS */

$types = str_repeat("s", count($allowed_subjects));

$params = $allowed_subjects;


if ($subject !== "") {

    $types .= "s";
    $params[] = $subject;

}


if ($date !== "") {

    $types .= "s";
    $params[] = $date;

}


if ($from_date !== "" && $to_date !== "") {

    $types .= "ss";

    $params[] = $from_date;
    $params[] = $to_date;

}


/* DYNAMIC BINDING */

$bind_params = [];

$bind_params[] = $types;

foreach ($params as $key => $value) {

    $bind_params[] = &$params[$key];

}

call_user_func_array(
    [$stmt, "bind_param"],
    $bind_params
);


/* EXECUTE QUERY */

$stmt->execute();

$result = $stmt->get_result();


$report = [];

$totalPresent = 0;
$totalAbsent = 0;
$totalRecords = 0;

$students = [];


/* PROCESS REPORT */

while ($row = $result->fetch_assoc()) {

    $present = (int)$row["present"];
    $absent = (int)$row["absent"];
    $total = (int)$row["total"];

    $percentage = 0;

    if ($total > 0) {

        $percentage = round(
            ($present / $total) * 100,
            2
        );

    }

    $row["present"] = $present;
    $row["absent"] = $absent;
    $row["total"] = $total;
    $row["percentage"] = $percentage;

    $report[] = $row;

    $totalPresent += $present;
    $totalAbsent += $absent;
    $totalRecords += $total;

    $students[$row["roll_no"]] = true;

}


$stmt->close();

$conn->close();


/* SEND RESPONSE */

echo json_encode([

    "status" => "success",

    "report" => $report,

    "summary" => [

        "total_students" => count($students),

        "total_records" => $totalRecords,

        "total_present" => $totalPresent,

        "total_absent" => $totalAbsent

    ]

]);

?>
