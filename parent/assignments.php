
<?php
session_start();

require_once "../db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["parent_id"])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Please log in as a parent."
    ]);
    exit;
}

// Find the student linked to this parent
$sql = "SELECT student_id FROM parents WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION["parent_id"]);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Linked student not found."
    ]);
    exit;
}

$student_id = (int) $result->fetch_assoc()["student_id"];
$stmt->close();

// Get assignments for the student's course subjects
$sql = "
    SELECT
        a.id AS assignment_id,
        a.subject,
        a.title,
        a.description,
        a.due_date,
        a.file_name AS assignment_file_name,
        a.file_path AS assignment_file_path,
        s.submitted_at,
        s.file_name AS submission_file_name,
        s.file_path AS submission_file_path
    FROM students st
    INNER JOIN assignments a
        ON a.subject IN (
            SELECT DISTINCT subject
            FROM attendance
            WHERE student_id = st.id
        )
    LEFT JOIN assignment_submissions s
        ON s.assignment_id = a.id
        AND s.student_id = st.id
    WHERE st.id = ?
    ORDER BY a.due_date ASC, a.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];

while ($row = $result->fetch_assoc()) {
    $submitted = !empty($row["submitted_at"]);
    $due_date = $row["due_date"];
    $today = date("Y-m-d");

    if ($submitted) {
        $status = "Submitted";
    } elseif ($due_date < $today) {
        $status = "Overdue";
    } else {
        $status = "Pending";
    }

    $row["status"] = $status;
    $assignments[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "status" => "success",
    "assignments" => $assignments
]);
?>