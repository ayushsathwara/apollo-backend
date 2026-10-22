
<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST request required."
    ]);
    exit;
}

require_once "../db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request data."
    ]);
    exit;
}

$adminId = (int) $_SESSION["admin_id"];
$currentPassword = $data["currentPassword"] ?? "";
$newEmail = trim($data["newEmail"] ?? "");
$newPassword = $data["newPassword"] ?? "";

if ($currentPassword === "" || $newEmail === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Current password and new email are required."
    ]);
    exit;
}

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter a valid email address."
    ]);
    exit;
}

if ($newPassword !== "" && strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "New password must be at least 8 characters."
    ]);
    exit;
}

// Fetch this admin's current credentials from the database.
$stmt = $conn->prepare(
    "SELECT email, password FROM admins WHERE id = ?"
);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin || !password_verify($currentPassword, $admin["password"])) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Current password is incorrect."
    ]);
    exit;
}

// Update only the logged-in admin's account.
if ($newPassword !== "") {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "UPDATE admins SET email = ?, password = ? WHERE id = ?"
    );
    $stmt->bind_param("ssi", $newEmail, $hashedPassword, $adminId);
} else {
    $stmt = $conn->prepare(
        "UPDATE admins SET email = ? WHERE id = ?"
    );
    $stmt->bind_param("si", $newEmail, $adminId);
}

if ($stmt->execute()) {
    $_SESSION["admin_email"] = $newEmail;

    echo json_encode([
        "success" => true,
        "message" => "Admin account updated successfully."
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Could not update account. Check whether the email is already in use."
    ]);
}

$stmt->close();
$conn->close();
?>
