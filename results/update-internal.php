
<?php

header("Content-Type: application/json");

// =========================================
// ROLE AND SESSION
// =========================================

$role = $_POST["role"] ?? "";

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

require_once "../db.php";

// =========================================
// AUTHENTICATION
// =========================================

if ($role === "faculty" && !isset($_SESSION["faculty_id"])) {
    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "message" => "Faculty login required."
    ]);

    exit;
}

if ($role === "admin" && !isset($_SESSION["admin_id"])) {
    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "message" => "Admin login required."
    ]);

    exit;
}

$user_id = ($role === "faculty")
    ? (int) $_SESSION["faculty_id"]
    : (int) $_SESSION["admin_id"];

// =========================================
// RECEIVE FORM DATA
// =========================================

$result_id = (int) ($_POST["result_id"] ?? 0);
$student_id = (int) ($_POST["student_id"] ?? 0);
$semester = (int) ($_POST["semester"] ?? 0);

$subject_name = trim($_POST["subject_name"] ?? "");
$exam_type = trim($_POST["exam_type"] ?? "");

$marks_obtained = $_POST["marks_obtained"] ?? "";
$total_marks = $_POST["total_marks"] ?? "";

$academic_year = trim($_POST["academic_year"] ?? "");

// =========================================
// VALIDATION
// =========================================

if (
    $result_id <= 0 ||
    $student_id <= 0 ||
    $semester < 1 ||
    $semester > 8 ||
    $subject_name === "" ||
    $exam_type === "" ||
    !is_numeric($marks_obtained) ||
    !is_numeric($total_marks) ||
    $academic_year === ""
) {
    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "message" => "Please provide valid result details."
    ]);

    exit;
}

$marks_obtained = (float) $marks_obtained;
$total_marks = (float) $total_marks;

if (
    $total_marks <= 0 ||
    $marks_obtained < 0 ||
    $marks_obtained > $total_marks
) {
    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "message" => "Invalid marks."
    ]);

    exit;
}

// =========================================
// CHECK STUDENT
// =========================================

$stmt = $conn->prepare(
    "SELECT semester FROM students WHERE id = ?"
);

$stmt->bind_param("i", $student_id);
$stmt->execute();

$student = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$student || $semester > (int) $student["semester"]) {
    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "message" => "Invalid student or semester."
    ]);

    exit;
}

// =========================================
// CHECK EXISTING RESULT OWNERSHIP
// =========================================

if ($role === "admin") {

    $stmt = $conn->prepare(
        "SELECT id FROM internal_results WHERE id = ?"
    );

    $stmt->bind_param("i", $result_id);
} else {

    $stmt = $conn->prepare(
        "SELECT id
         FROM internal_results
         WHERE id = ?
         AND entered_by_role = 'faculty'
         AND entered_by_id = ?"
    );

    $stmt->bind_param("ii", $result_id, $user_id);
}

$stmt->execute();

$existingResult = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$existingResult) {
    http_response_code(404);

    echo json_encode([
        "status" => "error",
        "message" => "Result not found or you are not authorized to edit it."
    ]);

    exit;
}

// =========================================
// VERIFY FACULTY SUBJECT ASSIGNMENT
// =========================================

if ($role === "faculty") {

    $stmt = $conn->prepare(
        "SELECT id
         FROM faculty_subjects
         WHERE faculty_id = ?
         AND subject = ?"
    );

    $stmt->bind_param(
        "is",
        $user_id,
        $subject_name
    );

    $stmt->execute();

    $assigned = $stmt->get_result()->num_rows > 0;

    $stmt->close();

    if (!$assigned) {
        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "message" => "You are not assigned to this subject."
        ]);

        exit;
    }
}

// =========================================
// UPDATE RESULT
// =========================================

// Preserve published status and original creator.

$sql = "
    UPDATE internal_results
    SET
        student_id = ?,
        semester = ?,
        subject_name = ?,
        exam_type = ?,
        marks_obtained = ?,
        total_marks = ?,
        academic_year = ?
    WHERE id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iissddsi",
    $student_id,
    $semester,
    $subject_name,
    $exam_type,
    $marks_obtained,
    $total_marks,
    $academic_year,
    $result_id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Internal marks updated successfully."
    ]);
} else {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to update internal marks."
    ]);
}

$stmt->close();
$conn->close();

?>
