
<?php
session_name('APOLLO_FACULTY_SESSION');
session_start();

include "../db.php";

header("Content-Type: application/json");

/* ================================
   CHECK FACULTY LOGIN
================================ */

if (!isset($_SESSION["faculty_id"])) {
    echo json_encode([
        "status" => "error",
        "message" => "Faculty not logged in"
    ]);
    exit;
}

$faculty_id = $_SESSION["faculty_id"];

/* ================================
   GET ASSIGNMENT ID
================================ */

$assignment_id = intval($_GET["assignment_id"] ?? 0);

if ($assignment_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid assignment."
    ]);
    exit;
}

/* ================================
   VERIFY FACULTY ASSIGNMENT
================================ */

$sql = "
    SELECT id, subject, title, due_date
    FROM assignments
    WHERE id = ?
    AND faculty_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Assignment query failed."
    ]);
    exit;
}

$stmt->bind_param("ii", $assignment_id, $faculty_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Assignment not found or access denied."
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

$assignment = $result->fetch_assoc();
$stmt->close();

/* ================================
   GET STUDENTS AND SUBMISSIONS
================================ */

$sql = "
    SELECT
        s.id,
        s.name,
        s.roll_no,
        sub.id AS submission_id,
        sub.file_name,
        sub.file_path,
        sub.submitted_at,
        CASE
            WHEN sub.id IS NOT NULL
                 AND DATE(sub.submitted_at) > a.due_date
                THEN 'Submitted Late'

            WHEN sub.id IS NOT NULL
                THEN 'Submitted'

            WHEN a.due_date < CURDATE()
                THEN 'Overdue'

            ELSE 'Pending'
        END AS submission_status

    FROM students s

    CROSS JOIN assignments a

    LEFT JOIN assignment_submissions sub
        ON s.id = sub.student_id
        AND sub.assignment_id = a.id

    WHERE a.id = ?

    ORDER BY s.roll_no ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Student query failed."
    ]);

    $conn->close();
    exit;
}

$stmt->bind_param("i", $assignment_id);
$stmt->execute();

$result = $stmt->get_result();

$students = [];

$submitted = 0;
$submitted_late = 0;
$pending = 0;
$overdue = 0;

while ($row = $result->fetch_assoc()) {

    $status = $row["submission_status"];

    if ($status === "Submitted") {
        $submitted++;
    }
    elseif ($status === "Submitted Late") {
        $submitted++;
        $submitted_late++;
    }
    elseif ($status === "Overdue") {
        $overdue++;
    }
    else {
        $pending++;
    }

    $students[] = [
        "id" => $row["id"],
        "name" => $row["name"],
        "roll_no" => $row["roll_no"],
        "status" => $status,
        "file_name" => $row["file_name"],
        "file_path" => $row["file_path"],
        "submitted_at" => $row["submitted_at"]
    ];
}

$stmt->close();
$conn->close();

/* ================================
   FINAL RESPONSE
================================ */

echo json_encode([
    "status" => "success",

    "assignment" => $assignment,

    "total_students" => count($students),

    "submitted" => $submitted,

    "submitted_late" => $submitted_late,

    "pending" => $pending,

    "overdue" => $overdue,

    "students" => $students
]);

?>
