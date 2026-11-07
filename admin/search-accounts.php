
<?php

session_start();

header("Content-Type: application/json");

include "../db.php";

// Admin authentication
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Please log in as admin."
    ]);

    exit;
}

// Get search parameters
$accountType = $_GET["type"] ?? "";
$search = trim($_GET["q"] ?? "");

$tables = [
    "student" => "students",
    "parent" => "parents",
    "faculty" => "faculty"
];

if (!isset($tables[$accountType])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid account type."
    ]);

    exit;
}

$table = $tables[$accountType];

// Search term
$searchTerm = "%" . $search . "%";

// Select columns
$columns = $accountType === "parent"
    ? "id, name, email, phone, profile_photo"
    : "id, name, email, profile_photo";

// Search by name, email or account ID
$stmt = $conn->prepare(
    "SELECT $columns
     FROM `$table`
     WHERE name LIKE ?
        OR email LIKE ?
        OR CAST(id AS CHAR) LIKE ?
     ORDER BY name ASC
     LIMIT 20"
);

$stmt->bind_param(
    "sss",
    $searchTerm,
    $searchTerm,
    $searchTerm
);

$stmt->execute();

$result = $stmt->get_result();

$accounts = [];

while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}

echo json_encode([
    "success" => true,
    "accounts" => $accounts
]);

$stmt->close();
$conn->close();

?>
