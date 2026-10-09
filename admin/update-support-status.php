
<?php
session_start();

header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

// Only logged-in admins can update requests.
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);
    exit;
}

// Only POST requests are accepted.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$requestId = filter_var(
    $input["request_id"] ?? null,
    FILTER_VALIDATE_INT
);

$status = $input["status"] ?? "";

$allowedStatuses = ["Open", "In Progress", "Resolved"];

if (
    $requestId === false ||
    $requestId === null ||
    $requestId < 1 ||
    !in_array($status, $allowedStatuses, true)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request ID or status."
    ]);
    exit;
}

// Update the selected support request.
$stmt = $conn->prepare(
    "UPDATE support_requests SET status = ? WHERE id = ?"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not prepare status update."
    ]);
    exit;
}

$stmt->bind_param("si", $status, $requestId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not update request status."
    ]);
    exit;
}

if ($stmt->affected_rows === 0) {
    // Distinguish an unchanged status from a nonexistent request.
    $check = $conn->prepare(
        "SELECT id FROM support_requests WHERE id = ?"
    );
    $check->bind_param("i", $requestId);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Support request not found."
        ]);
        exit;
    }
}

echo json_encode([
    "success" => true,
    "message" => "Support request status updated."
]);

$stmt->close();
$conn->close();
?>