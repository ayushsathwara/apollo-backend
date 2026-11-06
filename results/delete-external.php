
<?php

// =========================================
// ROLE & SESSION
// =========================================

$role = $_POST["role"] ?? "admin";

if ($role === "faculty") {
    session_name("APOLLO_FACULTY_SESSION");
} elseif ($role === "admin") {
    session_name("PHPSESSID");
} else {
    http_response_code(400);
    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "Invalid role."
    ]);
    exit;
}

session_start();

header("Content-Type: application/json");

require_once "../db.php";

// =========================================
// AUTHENTICATION
// =========================================

if ($role === "admin") {

    if (!isset($_SESSION["admin_id"])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Admin login required."
        ]);
        exit;
    }

    $user_id = (int) $_SESSION["admin_id"];
} else {

    if (!isset($_SESSION["faculty_id"])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Faculty login required."
        ]);
        exit;
    }

    $user_id = (int) $_SESSION["faculty_id"];
}

// =========================================
// ACCEPT POST ONLY
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);
    exit;
}

// =========================================
// RECEIVE RESULT ID
// =========================================

$result_id = (int) ($_POST["result_id"] ?? 0);

if ($result_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid result ID."
    ]);
    exit;
}

// =========================================
// FIND EXISTING RESULT
// =========================================

if ($role === "faculty") {

    // Faculty can access only their own uploaded results.

    $stmt = $conn->prepare(
        "SELECT result_file
         FROM external_results
         WHERE id = ?
         AND uploaded_by_role = 'faculty'
         AND uploaded_by_id = ?"
    );

    $stmt->bind_param("ii", $result_id, $user_id);
} else {

    // Admin can access any external result.

    $stmt = $conn->prepare(
        "SELECT result_file
         FROM external_results
         WHERE id = ?"
    );

    $stmt->bind_param("i", $result_id);
}

$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$result) {
    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => $role === "faculty"
            ? "Result not found or you cannot delete this result."
            : "External result not found."
    ]);

    exit;
}

// =========================================
// DELETE DATABASE RECORD
// =========================================

$stmt = $conn->prepare(
    "DELETE FROM external_results WHERE id = ?"
);

$stmt->bind_param("i", $result_id);

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete external result."
    ]);

    exit;
}

$stmt->close();

// =========================================
// DELETE ASSOCIATED PDF SAFELY
// =========================================

$file_path = __DIR__ . "/../" . ltrim(
    $result["result_file"],
    "/\\"
);

$real_file = realpath($file_path);
$upload_dir = realpath(__DIR__ . "/../uploads/results/");

if (
    $real_file !== false &&
    $upload_dir !== false &&
    strpos(
        $real_file,
        $upload_dir . DIRECTORY_SEPARATOR
    ) === 0 &&
    is_file($real_file)
) {
    unlink($real_file);
}

// =========================================
// SUCCESS RESPONSE
// =========================================

$conn->close();

echo json_encode([
    "success" => true,
    "message" => "External result deleted successfully."
]);

?>
