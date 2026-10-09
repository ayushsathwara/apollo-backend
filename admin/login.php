
<?php
session_start();

header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

// Allow only POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

// Read login data
$input = json_decode(file_get_contents("php://input"), true);

$email = trim($input["email"] ?? "");
$password = $input["password"] ?? "";

if ($email === "" || $password === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit;
}

// Find admin account
$stmt = $conn->prepare(
    "SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database query failed."
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin || !password_verify($password, $admin["password"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password."
    ]);
    exit;
}

// Regenerate session ID after login
session_regenerate_id(true);

// Store admin session separately from student/faculty sessions
$_SESSION["admin_id"] = (int) $admin["id"];
$_SESSION["admin_name"] = $admin["name"];
$_SESSION["admin_email"] = $admin["email"];

echo json_encode([
    "success" => true,
    "message" => "Admin login successful.",
    "admin" => [
        "id" => (int) $admin["id"],
        "name" => $admin["name"],
        "email" => $admin["email"]
    ]
]);

$stmt->close();
$conn->close();
?>