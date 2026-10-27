
<?php
require_once __DIR__ . "/db-production.php";

$result = $conn->query("SELECT DATABASE() AS db_name");

if ($result) {
    $row = $result->fetch_assoc();
    echo "Database connection successful!";
    echo "<br>Connected database: " . htmlspecialchars($row["db_name"]);
} else {
    http_response_code(500);
    echo "Database query failed.";
}
?>
