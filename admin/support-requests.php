
<?php
session_start();

header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

// Only authenticated admins can access support requests.
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);
    exit;
}

// Allow only GET requests.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

// Fetch support requests and the related student name.
$sql = "
    SELECT
        sr.id,
        sr.student_id,
        s.name AS student_name,
        sr.subject,
        sr.category,
        sr.message,
        sr.status,
        sr.created_at
    FROM support_requests AS sr
    INNER JOIN students AS s
        ON s.id = sr.student_id
    ORDER BY sr.created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not load support requests."
    ]);
    exit;
}

$requests = [];

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

echo json_encode([
    "success" => true,
    "requests" => $requests
]);

$conn->close();
?>