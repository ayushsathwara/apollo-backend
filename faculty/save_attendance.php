
<?php

session_name('APOLLO_FACULTY_SESSION');
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


/* Get request data */

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$subject = trim($data["subject"] ?? "");
$attendance_date = $data["attendance_date"] ?? "";
$attendance = $data["attendance"] ?? [];

$faculty_id = (int) $_SESSION["faculty_id"];


/* Validate subject and date */

$dateObject = DateTime::createFromFormat(
    "Y-m-d",
    $attendance_date
);

if (
    $subject === "" ||
    !$dateObject ||
    $dateObject->format("Y-m-d") !== $attendance_date
) {

    echo json_encode([
        "status" => "error",
        "message" => "Please provide a valid subject and date."
    ]);

    exit;
}


/* Validate attendance */

if (!is_array($attendance) || empty($attendance)) {

    echo json_encode([
        "status" => "error",
        "message" => "Please mark attendance."
    ]);

    exit;
}


/* Check faculty subject permission */

$permission_sql = "
    SELECT id
    FROM faculty_subjects
    WHERE faculty_id = ?
    AND subject = ?
";

$permission_stmt = $conn->prepare($permission_sql);

$permission_stmt->bind_param(
    "is",
    $faculty_id,
    $subject
);

$permission_stmt->execute();

$permission_result = $permission_stmt->get_result();

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


/* Validate all attendance entries before saving */

foreach ($attendance as $record) {

    $student_id = filter_var(
        $record["student_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $status = $record["status"] ?? "";

    if (
        !$student_id ||
        !in_array($status, ["Present", "Absent"], true)
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid student attendance data."
        ]);

        $conn->close();

        exit;
    }
}


/* Start transaction */

$conn->begin_transaction();

try {

    /* Create a new lecture session */

    $session_sql = "
        INSERT INTO attendance_sessions
        (subject, attendance_date)
        VALUES (?, ?)
    ";

    $session_stmt = $conn->prepare($session_sql);

    $session_stmt->bind_param(
        "ss",
        $subject,
        $attendance_date
    );

    $session_stmt->execute();

    $session_id = $conn->insert_id;

    $session_stmt->close();


    /* Prepare attendance insert */

    $sql = "
        INSERT INTO attendance
        (session_id, student_id, subject, attendance_date, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        status = VALUES(status)
    ";

    $stmt = $conn->prepare($sql);


    /* Save attendance for this session */

    foreach ($attendance as $record) {

        $student_id = (int) $record["student_id"];
        $status = $record["status"];

        $stmt->bind_param(
            "iisss",
            $session_id,
            $student_id,
            $subject,
            $attendance_date,
            $status
        );

        $stmt->execute();

    }


    $stmt->close();

    $conn->commit();


    echo json_encode([
        "status" => "success",
        "message" => "Attendance saved successfully! Lecture Session ID: " . $session_id,
        "session_id" => $session_id
    ]);

} catch (Throwable $error) {

    $conn->rollback();

    error_log("Attendance Save Error: " . $error->getMessage());

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to save attendance. Please try again."
    ]);

}

$conn->close();

?>
