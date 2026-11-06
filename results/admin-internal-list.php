
<?php

header("Content-Type: application/json");

// =========================================
// ROLE AND SESSION
// =========================================

$role = $_GET["role"] ?? "admin";

if ($role === "faculty") {
    session_name("APOLLO_FACULTY_SESSION");
} elseif ($role === "admin") {
    session_name("PHPSESSID");
} else {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid role."
    ]);

    exit;
}

session_start();

require_once "../db.php";

// =========================================
// AUTHENTICATION
// =========================================

if ($role === "admin" && !isset($_SESSION["admin_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);

    exit;
}

if ($role === "faculty" && !isset($_SESSION["faculty_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Faculty login required."
    ]);

    exit;
}

// =========================================
// SEMESTER FILTER
// =========================================

$semester = isset($_GET["semester"])
    ? (int) $_GET["semester"]
    : 0;

// =========================================
// BASE QUERY
// =========================================

$sql = "
    SELECT
        ir.id,
        ir.student_id,
        s.name AS student_name,
        s.roll_no,
        ir.semester,
        ir.subject_name,
        ir.exam_type,
        ir.marks_obtained,
        ir.total_marks,
        ir.academic_year,
        ir.published,
        ir.created_at
    FROM internal_results ir
    INNER JOIN students s
        ON ir.student_id = s.id
";

// =========================================
// ROLE-BASED FILTER
// =========================================

if ($role === "faculty") {

    $faculty_id = (int) $_SESSION["faculty_id"];

    $sql .= "
        WHERE ir.entered_by_role = 'faculty'
        AND ir.entered_by_id = ?
        AND EXISTS (
            SELECT 1
            FROM faculty_subjects fs
            WHERE fs.faculty_id = ?
            AND fs.subject = ir.subject_name
        )
    ";

    if ($semester >= 1 && $semester <= 8) {
        $sql .= " AND ir.semester = ?";
    }
} else {

    if ($semester >= 1 && $semester <= 8) {
        $sql .= " WHERE ir.semester = ?";
    }
}

$sql .= " ORDER BY ir.created_at DESC, ir.id DESC";

// =========================================
// PREPARE QUERY
// =========================================

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare results query."
    ]);

    exit;
}

// =========================================
// BIND PARAMETERS
// =========================================

if ($role === "faculty") {

    if ($semester >= 1 && $semester <= 8) {

        $stmt->bind_param(
            "iii",
            $faculty_id,
            $faculty_id,
            $semester
        );
    } else {

        $stmt->bind_param(
            "ii",
            $faculty_id,
            $faculty_id
        );
    }
} else {

    if ($semester >= 1 && $semester <= 8) {
        $stmt->bind_param("i", $semester);
    }
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
