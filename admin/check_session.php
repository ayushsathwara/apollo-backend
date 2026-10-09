
<?php
session_start();

header("Content-Type: application/json");

// Check whether an admin is logged in
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);

    exit;
}

// Return the logged-in admin's details
echo json_encode([
    "success" => true,
    "admin" => [
        "id" => (int) $_SESSION["admin_id"],
        "name" => $_SESSION["admin_name"] ?? "",
        "email" => $_SESSION["admin_email"] ?? ""
    ]
]);
?>