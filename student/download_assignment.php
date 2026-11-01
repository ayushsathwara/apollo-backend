
<?php
session_name('APOLLO_STUDENT_SESSION');
session_start();

include "../db.php";

if (!isset($_SESSION["student_id"])) {
    http_response_code(401);
    exit("Student not logged in.");
}

$student_id = (int) $_SESSION["student_id"];
$assignment_id = (int) ($_GET["id"] ?? 0);

if ($assignment_id <= 0) {
    http_response_code(400);
    exit("Invalid assignment.");
}

/* Check assignment exists and retrieve its file */

$sql = "
    SELECT file_name, file_path
    FROM assignments
    WHERE id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404);
    exit("Assignment not found.");
}

$assignment = $result->fetch_assoc();
$stmt->close();
$conn->close();

/* Validate stored file path */

$baseFolder = realpath(dirname(__DIR__) . "/uploads/assignments");

if (!$baseFolder || empty($assignment["file_path"])) {
    http_response_code(404);
    exit("Assignment file not found.");
}

$filePath = realpath(
    dirname(__DIR__) . "/" . $assignment["file_path"]
);

if (
    !$filePath ||
    !is_file($filePath) ||
    !str_starts_with($filePath, $baseFolder . DIRECTORY_SEPARATOR)
) {
    http_response_code(404);
    exit("Assignment file not found.");
}

/* Download */

$fileName = basename($assignment["file_name"]);

header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"" .
    str_replace('"', '', $fileName) . "\"");
header("Content-Length: " . filesize($filePath));
header("X-Content-Type-Options: nosniff");

readfile($filePath);
exit;

?>
