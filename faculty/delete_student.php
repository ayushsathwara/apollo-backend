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

$id = (int)($data["id"] ?? 0);


if ($id <= 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid student ID."
    ]);

    exit;
}


/* Start transaction */

$conn->begin_transaction();


try {

    /* Delete attendance records */

    $attendance_sql = "
        DELETE FROM attendance
        WHERE student_id = ?
    ";

    $attendance_stmt =
        $conn->prepare(
            $attendance_sql
        );

    $attendance_stmt->bind_param(
        "i",
        $id
    );

    $attendance_stmt->execute();

    $attendance_stmt->close();


    /* Delete student */

    $student_sql = "
        DELETE FROM students
        WHERE id = ?
    ";

    $student_stmt =
        $conn->prepare(
            $student_sql
        );

    $student_stmt->bind_param(
        "i",
        $id
    );

    $student_stmt->execute();


    if ($student_stmt->affected_rows === 0) {

        throw new Exception(
            "Student not found."
        );

    }


    $student_stmt->close();


    /* Save changes */

    $conn->commit();


    echo json_encode([
        "status" => "success",
        "message" => "Student and attendance records deleted successfully!"
    ]);

}

catch (Exception $e) {

    /* Undo everything if something fails */

    $conn->rollback();


    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);

}


$conn->close();

?>