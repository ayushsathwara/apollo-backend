
<?php

session_name("APOLLO_FACULTY_SESSION");
session_start();

require_once "../db.php";

// =========================================
// FACULTY LOGIN CHECK
// =========================================

if (!isset($_SESSION["faculty_id"])) {
    http_response_code(401);
    exit("Faculty login required.");
}

$faculty_id = (int) $_SESSION["faculty_id"];

$result_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($result_id <= 0) {
    http_response_code(400);
    exit("Invalid result ID.");
}

// =========================================
// VERIFY FACULTY OWNERSHIP
// =========================================

$sql = "SELECT result_file
        FROM external_results
        WHERE id = ?
        AND uploaded_by_role = 'faculty'
        AND uploaded_by_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $result_id, $faculty_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404);
    exit("Result not found or access denied.");
}

$data = $result->fetch_assoc();

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
header('Content-Disposition: inline; filename="result.pdf"');
header("Content-Length: " . filesize($filePath));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store, no-cache, must-revalidate");

readfile($filePath);
exit;

?>
