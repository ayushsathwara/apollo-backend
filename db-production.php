
<?php

$servername = "sql200.infinityfree.com";
$username = "if0_43246760";
$password = "diQtfx4UXArn";
$database = "if0_43246760_apollo";

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    error_log("Production database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed.");
}

$conn->set_charset("utf8mb4");

?>
