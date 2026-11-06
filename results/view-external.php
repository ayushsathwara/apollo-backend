
<?php
session_name('APOLLO_PARENT_SESSION');
session_start();

include "../db.php";

// Check parent login
if (!isset($_SESSION["parent_id"])) {
    http_response_code(401);
    exit("Parent login required.");
}

$parent_id = (int) $_SESSION["parent_id"];
$result_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($result_id <= 0) {
    http_response_code(400);
    exit("Invalid result ID.");
}

// Verify that this published result belongs to the parent's child
$sql = "SELECT er.result_file, er.original_filename
        FROM external_results er
        INNER JOIN parents p ON p.student_id = er.student_id
        WHERE p.id = ?
        AND er.id = ?
        AND er.published = 1
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $parent_id, $result_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404);
    exit("Result not found or not published.");
}

$data = $result->fetch_assoc();

// Resolve the file path safely
$baseDirectory = realpath(__DIR__ . "/../uploads/results/");
$filePath = realpath(__DIR__ . "/../" . $data["result_file"]);

if (
    !$baseDirectory ||
    !$filePath ||
    strpos($filePath, $baseDirectory . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit("Result PDF not found.");
}

// Send PDF securely
header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=\"result.pdf\"");
header("Content-Length: " . filesize($filePath));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store, no-cache, must-revalidate");

readfile($filePath);
exit;
?>
