
<?php
session_name('APOLLO_FACULTY_SESSION');
session_start();

header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";

// Check faculty login
if (!isset($_SESSION["faculty_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please log in as faculty."
    ]);

    exit;
}

// Accept GET requests only
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);

    exit;
}

$faculty_id = (int) $_SESSION["faculty_id"];

// Fetch notices for faculty's own course and all courses
$sql = "
    SELECT
        n.id,
        n.title,
        n.message,
        n.target_role,
        n.category,
        n.created_at,
        n.course

    FROM notices n

    INNER JOIN faculty f
        ON f.id = ?

    WHERE n.course IS NULL
       OR n.course = f.department

    ORDER BY n.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare notices query."
    ]);

    exit;
}

$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$result = $stmt->get_result();

$notices = [];

while ($row = $result->fetch_assoc()) {
    $notices[] = $row;
}

echo json_encode([
    "success" => true,
    "notices" => $notices
]);

$stmt->close();
$conn->close();
?>
