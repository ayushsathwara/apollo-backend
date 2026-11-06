
<?php

session_name("APOLLO_PARENT_SESSION");
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION["parent_id"])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Parent login required"
    ]);
    exit;
}

include "../db.php";

$parent_id = (int) $_SESSION["parent_id"];

// Find linked student
$sql = "SELECT s.id, s.semester
        FROM parents p
        INNER JOIN students s ON s.id = p.student_id
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();

$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Student not found"
    ]);
    exit;
}

$student_id = (int) $student["id"];
$current_semester = (int) $student["semester"];

$semester = isset($_GET["semester"])
    ? (int) $_GET["semester"]
    : 0;

if (
    $semester !== 0 &&
    ($semester < 1 || $semester > $current_semester)
) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid semester"
    ]);
    exit;
}

$sql = "SELECT
            id,
            semester,
            academic_year,
            original_filename,
            uploaded_at
        FROM external_results
        WHERE student_id = ?
        AND published = 1";

if ($semester > 0) {
    $sql .= " AND semester = ?";
}

$sql .= " ORDER BY semester DESC";

$stmt = $conn->prepare($sql);

if ($semester > 0) {
    $stmt->bind_param("ii", $student_id, $semester);
} else {
    $stmt->bind_param("i", $student_id);
}

$stmt->execute();

$result = $stmt->get_result();

$results = [];

while ($row = $result->fetch_assoc()) {
    $row["view_url"] = "view-external.php?id=" . $row["id"];
    $results[] = $row;
}

echo json_encode([
    "status" => "success",
    "current_semester" => $current_semester,
    "results" => $results
]);

$stmt->close();
$conn->close();

?>
