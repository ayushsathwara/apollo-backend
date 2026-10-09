<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$name = "System Admin";
$email = "your-email@example.com";
$password = "ChooseYourOwnStrongPassword123!";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO admins (name, email, password) VALUES (?, ?, ?)"
);

$stmt->bind_param("sss", $name, $email, $hashedPassword);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Admin account created successfully."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Account creation failed. Check the email or database."
    ]);
}

$stmt->close();
$conn->close();
?>