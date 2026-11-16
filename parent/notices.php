
<?php
session_name('APOLLO_PARENT_SESSION');
session_start();

header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";

// Check parent login
if (!isset($_SESSION["parent_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Parent login required"
    ]);

    exit;
}

$parent_id = (int) $_SESSION["parent_id"];

// Fetch notices according to linked student's course
$sql = "
    SELECT
        n.id,
        n.title,
        n.message,
        n.posted_by,
        n.created_at

    FROM notices n

    INNER JOIN parents p
        ON p.id = ?

    INNER JOIN students st
        ON st.id = p.student_id

    WHERE n.target_role IN ('all', 'parent')
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
        "message" => "Unable to prepare notices query"
    ]);

    exit;
}

$stmt->bind_param("i", $parent_id);
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
