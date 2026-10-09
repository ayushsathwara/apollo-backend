
<?php
session_start();

header("Content-Type: application/json");

// Remove only the admin's session data
unset(
    $_SESSION["admin_id"],
    $_SESSION["admin_name"],
    $_SESSION["admin_email"]
);

// Regenerate the session ID after logout
session_regenerate_id(true);

echo json_encode([
    "success" => true,
    "message" => "Admin logged out successfully."
]);
?>