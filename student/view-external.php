
<?php

session_name("APOLLO_STUDENT_SESSION");
session_start();

require_once "../db.php";

// =========================================
// STUDENT AUTHENTICATION
// =========================================

if (!isset($_SESSION["student_id"])) {
    http_response_code(401);
    exit("Student login required.");
}

$student_id = (int) $_SESSION["student_id"];

$result_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($result_id <= 0) {
    http_response_code(400);
    exit("Invalid result ID.");
}

// =========================================
// VERIFY OWNERSHIP AND PUBLISHED STATUS
// =========================================

$sql = "
    SELECT result_file
    FROM external_results
    WHERE id = ?
    AND student_id = ?
    AND published = 1
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to prepare result query.");
}

$stmt->bind_param("ii", $result_id, $student_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404);
    exit("Result not found or not published.");
}

$data = $result->fetch_assoc();

$stmt->close();

// =========================================
// SAFE FILE PATH CHECK
// =========================================

$baseDirectory = realpath(__DIR__ . "/../uploads/results/");

$filePath = realpath(
    __DIR__ . "/../" . $data["result_file"]
);

if (
    !$baseDirectory ||
    !$filePath ||
    strpos(
        $filePath,
        $baseDirectory . DIRECTORY_SEPARATOR
    ) !== 0 ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit("Result PDF not found.");
}

// =========================================
// DISPLAY PDF
// =========================================

header("Content-Type: application/pdf");
header('Content-Disposition: inline; filename="external-result.pdf"');
header("Content-Length: " . filesize($filePath));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store, no-cache, must-revalidate");

readfile($filePath);
exit;

?>
