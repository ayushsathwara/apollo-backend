
<?php

header("Content-Type: application/json");

// Get the role from POST request
$role = $_POST["role"] ?? "";

// Start the correct login session
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

include "../db.php";

// Check login
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

// Get entered-by ID
$entered_by_id = ($role === "faculty")
    ? (int) $_SESSION["faculty_id"]
    : (int) $_SESSION["admin_id"];

// Read form data
$student_id = (int) ($_POST["student_id"] ?? 0);
$semester = (int) ($_POST["semester"] ?? 0);
$subject_name = trim($_POST["subject_name"] ?? "");
$exam_type = trim($_POST["exam_type"] ?? "");
$marks_obtained = $_POST["marks_obtained"] ?? "";
$total_marks = $_POST["total_marks"] ?? "";
$academic_year = trim($_POST["academic_year"] ?? "");

// Validate required fields
if (
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
        "message" => "Please provide valid result details"
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
        "message" => "Invalid marks"
    ]);
    exit;
}

// Check student exists and semester is not in the future
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
        "message" => "Invalid student or semester"
    ]);
    exit;
}

// Faculty can enter marks only for assigned subjects
if ($role === "faculty") {

    $stmt = $conn->prepare(
        "SELECT id FROM faculty_subjects
         WHERE faculty_id = ? AND subject = ?"
    );

    $stmt->bind_param(
        "is",
        $entered_by_id,
        $subject_name
    );

    $stmt->execute();
    $assigned = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$assigned) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "You are not assigned to this subject"
        ]);
        exit;
    }
}

// Save internal result
$sql = "INSERT INTO internal_results
(
    student_id,
    semester,
    subject_name,
    exam_type,
    marks_obtained,
    total_marks,
    academic_year,
    entered_by_role,
    entered_by_id
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iissddssi",
    $student_id,
    $semester,
    $subject_name,
    $exam_type,
    $marks_obtained,
    $total_marks,
    $academic_year,
    $role,
    $entered_by_id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Internal result saved successfully",
        "result_id" => $stmt->insert_id
    ]);
} else {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to save result"
    ]);
}

$stmt->close();
$conn->close();

?>
