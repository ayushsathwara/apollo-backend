
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

require_once "../db.php";

// =========================================
// OPTIONAL SEMESTER FILTER
// =========================================

$semester = isset($_GET["semester"])
    ? (int) $_GET["semester"]
    : 0;

// =========================================
// BUILD QUERY
// =========================================

$sql = "SELECT
            er.id,
            er.student_id,
            s.name AS student_name,
            s.roll_no,
            s.course,
            er.semester,
            er.academic_year,
            er.original_filename,
            er.published,
            er.uploaded_at,
            er.uploaded_by_role,
            er.uploaded_by_id
        FROM external_results er
        INNER JOIN students s ON s.id = er.student_id";

$conditions = [];
$params = [];
$types = "";

// Faculty sees only their own uploaded results
if ($role === "faculty") {

    $conditions[] = "
        er.uploaded_by_role = 'faculty'
        AND er.uploaded_by_id = ?
    ";

    $types .= "i";
    $params[] = (int) $_SESSION["faculty_id"];
}

// Semester filter
if ($semester >= 1 && $semester <= 8) {

    $conditions[] = "er.semester = ?";

    $types .= "i";
    $params[] = $semester;
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY er.uploaded_at DESC";

// =========================================
// EXECUTE QUERY
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

if (!empty($params)) {

    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to fetch external results."
    ]);

    exit;
}

// =========================================
// FETCH RESULTS
// =========================================

$queryResult = $stmt->get_result();

$results = [];

while ($row = $queryResult->fetch_assoc()) {

    $results[] = [
        "id" => (int) $row["id"],
        "student_id" => (int) $row["student_id"],
        "student_name" => $row["student_name"],
        "roll_no" => $row["roll_no"],
        "course" => $row["course"],
        "semester" => (int) $row["semester"],
        "academic_year" => $row["academic_year"],
        "original_filename" => $row["original_filename"],
        "published" => (int) $row["published"],
        "uploaded_at" => $row["uploaded_at"],
        "uploaded_by_role" => $row["uploaded_by_role"],
        "uploaded_by_id" => (int) $row["uploaded_by_id"]
    ];
}

// =========================================
// RESPONSE
// =========================================

echo json_encode([
    "success" => true,
    "total" => count($results),
    "results" => $results
]);

$stmt->close();
$conn->close();

?>
