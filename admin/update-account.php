
<?php
session_start();
header("Content-Type: application/json");

include "../db.php";

// =========================================
// ADMIN AUTHENTICATION
// =========================================

if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Please log in as admin."
    ]);
    exit;
}

// =========================================
// REQUEST METHOD
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
    exit;
}

// =========================================
// RECEIVE JSON DATA
// =========================================

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request data."
    ]);
    exit;
}

$accountType = $input["account_type"] ?? "";

$accountId = filter_var(
    $input["account_id"] ?? null,
    FILTER_VALIDATE_INT
);

$email = trim($input["email"] ?? "");
$password = $input["password"] ?? "";
$phone = trim($input["phone"] ?? "");

// =========================================
// ACCOUNT TYPE VALIDATION
// =========================================

$tables = [
    "student" => "students",
    "parent" => "parents",
    "faculty" => "faculty"
];

if (!isset($tables[$accountType]) || !$accountId || $accountId < 1) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Select a valid account and ID."
    ]);
    exit;
}

// =========================================
// EMAIL VALIDATION
// =========================================

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter a valid email address."
    ]);
    exit;
}

// =========================================
// PASSWORD VALIDATION
// =========================================

if ($password !== "" && strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 8 characters."
    ]);
    exit;
}

// =========================================
// PARENT PHONE VALIDATION
// =========================================

if ($accountType === "parent" && strlen($phone) > 20) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Contact number cannot exceed 20 characters."
    ]);
    exit;
}

$table = $tables[$accountType];

// =========================================
// CHECK ACCOUNT EXISTS
// =========================================

$check = $conn->prepare(
    "SELECT id FROM `$table` WHERE id = ?"
);

$check->bind_param("i", $accountId);
$check->execute();

$result = $check->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Account not found."
    ]);

    $check->close();
    $conn->close();
    exit;
}

$check->close();

// =========================================
// UPDATE PARENT ACCOUNT
// =========================================

if ($accountType === "parent") {

    if ($password !== "") {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare(
            "UPDATE parents
             SET email = ?, phone = ?, password = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "sssi",
            $email,
            $phone,
            $hashedPassword,
            $accountId
        );
    } else {

        $stmt = $conn->prepare(
            "UPDATE parents
             SET email = ?, phone = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssi",
            $email,
            $phone,
            $accountId
        );
    }
} else {

    // =========================================
    // UPDATE STUDENT / FACULTY ACCOUNT
    // =========================================

    if ($password !== "") {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare(
            "UPDATE `$table`
             SET email = ?, password = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssi",
            $email,
            $hashedPassword,
            $accountId
        );
    } else {

        $stmt = $conn->prepare(
            "UPDATE `$table`
             SET email = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "si",
            $email,
            $accountId
        );
    }
}

// =========================================
// EXECUTE UPDATE
// =========================================

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Account updated successfully."
    ]);
} else {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Could not update account. The email may already be in use."
    ]);
}

$stmt->close();
$conn->close();

?>
