
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

$parent_id = (int) $_SESSION["parent_id"];

// Get the student linked to this parent
$sql = "SELECT student_id FROM parents WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parent_id);
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

// Overall attendance summary
$sql = "
    SELECT
        COUNT(*) AS total_classes,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent
    FROM attendance
    WHERE student_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total = (int) $summary["total_classes"];
$present = (int) $summary["present"];
$absent = (int) $summary["absent"];

$percentage = $total > 0
    ? round(($present / $total) * 100, 2)
    : 0;

// Subject-wise attendance
$sql = "
    SELECT
        subject,
        COUNT(*) AS total_classes,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent
    FROM attendance
    WHERE student_id = ?
    GROUP BY subject
    ORDER BY subject
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];

while ($row = $result->fetch_assoc()) {
    $subject_total = (int) $row["total_classes"];
    $subject_present = (int) $row["present"];

    $row["total_classes"] = $subject_total;
    $row["present"] = $subject_present;
    $row["absent"] = (int) $row["absent"];
    $row["percentage"] = $subject_total > 0
        ? round(($subject_present / $subject_total) * 100, 2)
        : 0;

    $subjects[] = $row;
}

$stmt->close();

// Recent attendance history
$sql = "
    SELECT subject, attendance_date, status
    FROM attendance
    WHERE student_id = ?
    ORDER BY attendance_date DESC, id DESC
    LIMIT 50
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];

while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "status" => "success",
    "summary" => [
        "total_classes" => $total,
        "present" => $present,
        "absent" => $absent,
        "percentage" => $percentage,
        "warning" => $total > 0 && $percentage < 75
    ],
    "subjects" => $subjects,
    "history" => $history
]);
?>