<?php

session_start();

include "../db.php";

header("Content-Type: application/json");


/* ================================
   CHECK STUDENT LOGIN
================================ */

if (!isset($_SESSION["student_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Student not logged in"
    ]);

    exit;
}


/* ================================
   GET TODAY'S CLASSES
================================ */

$class_date = date("Y-m-d");


$sql = "
    SELECT
        tc.id,
        tc.subject,
        tc.start_time,
        tc.end_time,
        tc.room,
        f.name AS faculty_name

    FROM today_classes tc

    INNER JOIN faculty f
        ON tc.faculty_id = f.id

    WHERE tc.class_date = ?

    ORDER BY tc.start_time ASC
";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $class_date
);

$stmt->execute();

$result = $stmt->get_result();


$classes = [];


while ($row = $result->fetch_assoc()) {

    $classes[] = $row;

}


/* ================================
   RESPONSE
================================ */

echo json_encode([
    "status" => "success",
    "classes" => $classes
]);


$stmt->close();
$conn->close();

?>