
<?php
session_name('APOLLO_STUDENT_SESSION');
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

$student_id = $_SESSION["student_id"];


/* ================================
   FETCH ASSIGNMENTS
================================ */

$sql = "
    SELECT
        a.id,
        a.subject,
        a.title,
        a.description,
        a.due_date,
        a.created_at,

        a.file_name AS assignment_file_name,
        a.file_path AS assignment_file_path,

        f.name AS faculty_name,

        s.id AS submission_id,
        s.file_name AS submission_file_name,
        s.file_path AS submission_file_path,
        s.submitted_at,

        CASE
            WHEN s.id IS NOT NULL
                THEN 'Submitted'

            WHEN a.due_date < CURDATE()
                THEN 'Overdue'

            ELSE 'Pending'
        END AS status

    FROM assignments a

    INNER JOIN faculty f
        ON a.faculty_id = f.id

    LEFT JOIN assignment_submissions s
        ON a.id = s.assignment_id
        AND s.student_id = ?

    ORDER BY a.due_date ASC, a.id DESC
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database query failed."
    ]);

    $conn->close();
    exit;
}


$stmt->bind_param("i", $student_id);

$stmt->execute();

$result = $stmt->get_result();


/* ================================
   STORE ASSIGNMENTS
================================ */

$assignments = [];

while ($row = $result->fetch_assoc()) {

    $assignments[] = $row;

}


/* ================================
   RESPONSE
================================ */

echo json_encode([
    "status" => "success",
    "assignments" => $assignments
]);


$stmt->close();
$conn->close();

?>
