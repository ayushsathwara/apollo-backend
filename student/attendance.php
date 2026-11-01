
<?php

session_name('APOLLO_STUDENT_SESSION');
session_start();

include "../db.php";

header("Content-Type: application/json");


/* Check student login */

if (!isset($_SESSION["student_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Not logged in"
    ]);

    exit;
}


$student_id = (int) $_SESSION["student_id"];


/* Fetch attendance with lecture session ID */

$sql = "
    SELECT
        id,
        session_id,
        subject,
        attendance_date,
        status
    FROM attendance
    WHERE student_id = ?
    ORDER BY attendance_date DESC, session_id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to prepare attendance query."
    ]);

    exit;
}


$stmt->bind_param("i", $student_id);

$stmt->execute();

$result = $stmt->get_result();


$attendance = [];

while ($row = $result->fetch_assoc()) {

    $attendance[] = $row;

}


/* Send response */

echo json_encode([
    "status" => "success",
    "attendance" => $attendance
]);


$stmt->close();
$conn->close();

?>
