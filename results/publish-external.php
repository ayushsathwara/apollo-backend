
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
// RECEIVE DATA
// =========================================

$result_id = isset($_POST["result_id"])
    ? (int) $_POST["result_id"]
    : 0;

$published = isset($_POST["published"])
    ? (int) $_POST["published"]
    : -1;

// =========================================
// VALIDATE INPUT
// =========================================

if ($result_id <= 0 || !in_array($published, [0, 1], true)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid result ID and publish status are required."
    ]);
    exit;
}

// =========================================
// UPDATE PUBLISH STATUS
// =========================================

if ($role === "faculty") {

    // Faculty can update only their own uploaded results.

    $sql = "UPDATE external_results
            SET published = ?
            WHERE id = ?
            AND uploaded_by_role = 'faculty'
            AND uploaded_by_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $published, $result_id, $user_id);
} else {

    // Admin can update any external result.

    $sql = "UPDATE external_results
            SET published = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $published, $result_id);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to update result status."
    ]);
    exit;
}

// =========================================
// CHECK IF RECORD EXISTS / OWNERSHIP
// =========================================

if ($stmt->affected_rows === 0) {

    if ($role === "faculty") {

        $check = $conn->prepare(
            "SELECT id FROM external_results
             WHERE id = ?
             AND uploaded_by_role = 'faculty'
             AND uploaded_by_id = ?"
        );

        $check->bind_param("ii", $result_id, $user_id);
    } else {

        $check = $conn->prepare(
            "SELECT id FROM external_results WHERE id = ?"
        );

        $check->bind_param("i", $result_id);
    }

    $check->execute();

    if ($check->get_result()->num_rows === 0) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => $role === "faculty"
                ? "Result not found or you cannot modify this result."
                : "External result not found."
        ]);

        exit;
    }
}

// =========================================
// SUCCESS RESPONSE
// =========================================

echo json_encode([
    "success" => true,
    "message" => $published === 1
        ? "External result published successfully."
        : "External result unpublished successfully.",
    "published" => $published
]);

?>
