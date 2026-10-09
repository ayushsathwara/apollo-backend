<?php

session_start();

include "../db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["student_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Student not logged in"
    ]);

    exit;
}

$student_id = $_SESSION["student_id"];

$sql = "
    SELECT
        a.id,
        a.subject,
        a.title,
        a.description,
        a.due_date,
        f.name AS faculty_name,

        CASE

            WHEN s.id IS NOT NULL
                THEN 'Submitted'

            WHEN a.due_date < CURDATE()
                THEN 'Overdue'

            ELSE 'Pending'

        END AS status,

        s.submitted_at

    FROM assignments a

    INNER JOIN faculty f
        ON a.faculty_id = f.id

    LEFT JOIN assignment_submissions s
        ON a.id = s.assignment_id
        AND s.student_id = ?

    ORDER BY a.due_date ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database query failed."
    ]);

    exit;
}

$stmt->bind_param("i", $student_id);

$stmt->execute();

$result = $stmt->get_result();

$assignments = [];

while ($row = $result->fetch_assoc()) {

    $assignments[] = $row;

}

echo json_encode([
    "status" => "success",
    "assignments" => $assignments
]);

$stmt->close();
$conn->close();

?>