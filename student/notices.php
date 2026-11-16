
<?php
session_name('APOLLO_STUDENT_SESSION');
session_start();

header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";

// Check student login
if (!isset($_SESSION["student_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please log in as a student."
    ]);

    exit;
}

$student_id = (int) $_SESSION["student_id"];

// Fetch notices for the logged-in student's course
$sql = "
    SELECT
        n.id,
        n.title,
        n.message,
        n.posted_by,
        n.category,
        n.created_at

    FROM notices n

    INNER JOIN students st
        ON st.id = ?

    WHERE n.target_role IN ('all', 'student')
      AND (
          n.course IS NULL
          OR n.course = st.course
      )

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

$stmt->bind_param("i", $student_id);
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
