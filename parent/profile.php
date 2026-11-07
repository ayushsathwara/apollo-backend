
<?php

session_name('APOLLO_PARENT_SESSION');
session_start();

include "../db.php";

header("Content-Type: application/json");

// Check parent login
if (!isset($_SESSION["parent_id"])) {
    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "message" => "Please log in as a parent."
    ]);

    exit;
}

$parent_id = (int) $_SESSION["parent_id"];

// Fetch parent and linked student details
$sql = "
    SELECT
        parents.name AS parent_name,
        parents.email AS parent_email,
        parents.phone AS parent_phone,
        parents.profile_photo AS profile_photo,
        students.name AS student_name,
        students.roll_no,
        students.course,
        students.semester
    FROM parents
    INNER JOIN students
        ON parents.student_id = students.id
    WHERE parents.id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Database query failed."
    ]);

    exit;
}

$stmt->bind_param("i", $parent_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $profile = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "profile" => $profile
    ]);
} else {

    http_response_code(404);

    echo json_encode([
        "status" => "error",
        "message" => "Parent or linked student not found."
    ]);
}

$stmt->close();
$conn->close();

?>
