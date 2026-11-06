
<?php

session_name("APOLLO_STUDENT_SESSION");
session_start();

header("Content-Type: application/json");

require_once "../db.php";

// =========================================
// STUDENT AUTHENTICATION
// =========================================

if (!isset($_SESSION["student_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Student login required."
    ]);

    exit;
}

$student_id = (int) $_SESSION["student_id"];

// =========================================
// SEMESTER FILTER
// =========================================

$semester = isset($_GET["semester"])
    ? (int) $_GET["semester"]
    : 0;

if ($semester < 0 || $semester > 8) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid semester."
    ]);

    exit;
}

// =========================================
// FETCH PUBLISHED INTERNAL RESULTS
// =========================================

$sql = "
    SELECT
        id,
        semester,
        subject_name,
        exam_type,
        marks_obtained,
        total_marks,
        academic_year,
        created_at
    FROM internal_results
    WHERE student_id = ?
    AND published = 1
";

if ($semester >= 1 && $semester <= 8) {
    $sql .= " AND semester = ?";
}

$sql .= " ORDER BY semester ASC, subject_name ASC, created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare internal results query."
    ]);

    exit;
}

// =========================================
// BIND PARAMETERS
// =========================================

if ($semester >= 1 && $semester <= 8) {

    $stmt->bind_param("ii", $student_id, $semester);
} else {

    $stmt->bind_param("i", $student_id);
}

// =========================================
// EXECUTE QUERY
// =========================================

if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load internal results."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}

// =========================================
// FETCH RESULTS
// =========================================

$result = $stmt->get_result();

$results = [];

while ($row = $result->fetch_assoc()) {
    $results[] = $row;
}

// =========================================
// RESPONSE
// =========================================

echo json_encode([
    "success" => true,
    "results" => $results
]);

$stmt->close();
$conn->close();

?>
