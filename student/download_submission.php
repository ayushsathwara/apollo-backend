<?php
session_name('APOLLO_STUDENT_SESSION');
session_start();

include "../db.php";

if (!isset($_SESSION["student_id"])) {
    http_response_code(401);
    exit("Student not logged in.");
}

$student_id = (int) $_SESSION["student_id"];
$submission_id = (int) ($_GET["id"] ?? 0);

if ($submission_id <= 0) {
    http_response_code(400);
    exit("Invalid submission.");
}

// Verify that this submission belongs to the logged-in student
$sql = "
    SELECT file_name, file_path
    FROM assignment_submissions
    WHERE id = ? AND student_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $submission_id, $student_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404);
    exit("Submission not found.");
}

$submission = $result->fetch_assoc();

$stmt->close();
$conn->close();

$baseFolder = realpath(dirname(__DIR__) . "/uploads/submissions");

if (!$baseFolder || empty($submission["file_path"])) {
    http_response_code(404);
    exit("Submission file not found.");
}

$filePath = realpath(dirname(__DIR__) . "/" . $submission["file_path"]);

if (
    !$filePath ||
    !is_file($filePath) ||
    strpos($filePath, $baseFolder . DIRECTORY_SEPARATOR) !== 0
) {
    http_response_code(404);
    exit("Submission file not found.");
}

$fileName = basename($submission["file_name"]);

header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"" . str_replace('"', '', $fileName) . "\"");
header("Content-Length: " . filesize($filePath));
header("X-Content-Type-Options: nosniff");

readfile($filePath);
exit;
?>