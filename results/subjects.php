
<?php

header("Content-Type: application/json");

$role = $_GET["role"] ?? "";

if ($role === "faculty") {
    session_name("APOLLO_FACULTY_SESSION");
} elseif ($role === "admin") {
    session_name("PHPSESSID");
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid role"
    ]);
    exit;
}

session_start();

if ($role === "faculty" && !isset($_SESSION["faculty_id"])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Faculty login required"
    ]);
    exit;
}

if ($role === "admin" && !isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Admin login required"
    ]);
    exit;
}

include "../db.php";

$course = trim($_GET["course"] ?? "BCA");
$semester = (int) ($_GET["semester"] ?? 0);

if ($semester < 1 || $semester > 8) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid semester"
    ]);
    exit;
}

if ($role === "faculty") {

    $faculty_id = (int) $_SESSION["faculty_id"];

    $sql = "SELECT rs.id, rs.subject_name
            FROM result_subjects rs
            INNER JOIN faculty_subjects fs
                ON fs.subject = rs.subject_name
            WHERE fs.faculty_id = ?
            AND rs.course = ?
            AND rs.semester = ?
            ORDER BY rs.subject_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "isi",
        $faculty_id,
        $course,
        $semester
    );
} else {

    $sql = "SELECT id, subject_name
            FROM result_subjects
            WHERE course = ?
            AND semester = ?
            ORDER BY subject_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "si",
        $course,
        $semester
    );
}

$stmt->execute();

$result = $stmt->get_result();

$subjects = [];

while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
    
echo json_encode([
    "status" => "success",
    "subjects" => $subjects
]);

$stmt->close();
$conn->close();

?>
