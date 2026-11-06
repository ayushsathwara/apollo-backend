
<?php

header("Content-Type: application/json");

// =========================================
// ROLE AND SESSION
// =========================================

$role = $_POST["role"] ?? "";

if ($role === "faculty") {
    session_name("APOLLO_FACULTY_SESSION");
} elseif ($role === "admin") {
    session_name("PHPSESSID");
} else {
    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "message" => "Invalid role."
    ]);

    exit;
}

session_start();

require_once "../db.php";

// =========================================
// AUTHENTICATION
// =========================================

if ($role === "faculty" && !isset($_SESSION["faculty_id"])) {
    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "message" => "Faculty login required."
    ]);

    exit;
}

if ($role === "admin" && !isset($_SESSION["admin_id"])) {
    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "message" => "Admin login required."
    ]);

    exit;
}

$user_id = ($role === "faculty")
    ? (int) $_SESSION["faculty_id"]
    : (int) $_SESSION["admin_id"];

// =========================================
// RECEIVE DATA
// =========================================

$result_id = (int) ($_POST["result_id"] ?? 0);

$published = isset($_POST["published"])
    ? (int) $_POST["published"]
    : -1;

if (
    $result_id <= 0 ||
    !in_array($published, [0, 1], true)
) {
    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "message" => "Invalid result details."
    ]);

    exit;
}

// =========================================
// CHECK RESULT OWNERSHIP
// =========================================

if ($role === "admin") {

    $stmt = $conn->prepare(
        "SELECT id FROM internal_results WHERE id = ?"
    );

    $stmt->bind_param("i", $result_id);
} else {

    $stmt = $conn->prepare(
        "SELECT id, subject_name
         FROM internal_results
         WHERE id = ?
         AND entered_by_role = 'faculty'
         AND entered_by_id = ?"
    );

    $stmt->bind_param("ii", $result_id, $user_id);
}

$stmt->execute();

$internalResult = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$internalResult) {
    http_response_code(404);

    echo json_encode([
        "status" => "error",
        "message" => "Result not found or you are not authorized."
    ]);

    exit;
}

// =========================================
// VERIFY FACULTY SUBJECT ASSIGNMENT
// =========================================

if ($role === "faculty") {

    $subject_name = $internalResult["subject_name"];

    $stmt = $conn->prepare(
        "SELECT id
         FROM faculty_subjects
         WHERE faculty_id = ?
         AND subject = ?"
    );

    $stmt->bind_param("is", $user_id, $subject_name);
    $stmt->execute();

    $assigned = $stmt->get_result()->num_rows > 0;

    $stmt->close();

    if (!$assigned) {
        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "message" => "You are not assigned to this subject."
        ]);

        exit;
    }
}

// =========================================
// UPDATE PUBLISH STATUS
// =========================================

if ($role === "faculty") {

    $stmt = $conn->prepare(
        "UPDATE internal_results
         SET published = ?
         WHERE id = ?
         AND entered_by_role = 'faculty'
         AND entered_by_id = ?"
    );

    $stmt->bind_param(
        "iii",
        $published,
        $result_id,
        $user_id
    );
} else {

    $stmt = $conn->prepare(
        "UPDATE internal_results
         SET published = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        "ii",
        $published,
        $result_id
    );
}

if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => $published
            ? "Internal result published successfully."
            : "Internal result unpublished successfully."
    ]);
} else {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to update result."
    ]);
}

$stmt->close();
$conn->close();

?>
