
<?php

session_start();

header("Content-Type: application/json");

include "../db.php";

// =====================================
// ADMIN AUTHENTICATION
// =====================================

if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);

    exit;
}

// =====================================
// REQUEST VALIDATION
// =====================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);

    exit;
}

$accountType = $_POST["type"] ?? "";
$accountId = filter_var(
    $_POST["account_id"] ?? null,
    FILTER_VALIDATE_INT
);

$action = $_POST["action"] ?? "upload";

$tables = [
    "student" => "students",
    "faculty" => "faculty",
    "parent" => "parents"
];

if (!isset($tables[$accountType]) || !$accountId || $accountId < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid account information."
    ]);

    exit;
}

if (!in_array($action, ["upload", "remove"], true)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid action."
    ]);

    exit;
}

$table = $tables[$accountType];

// =====================================
// CHECK ACCOUNT
// =====================================

$stmt = $conn->prepare(
    "SELECT profile_photo FROM `$table` WHERE id = ?"
);

$stmt->bind_param("i", $accountId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Account not found."
    ]);

    exit;
}

$account = $result->fetch_assoc();
$oldPhoto = $account["profile_photo"];

$stmt->close();

// =====================================
// PHOTO STORAGE
// =====================================

$uploadDirectory = dirname(__DIR__) . "/uploads/profile-photos/";

$relativeDirectory = "uploads/profile-photos/";

if (!is_dir($uploadDirectory)) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Photo upload directory not found."
    ]);

    exit;
}

// =====================================
// REMOVE PHOTO
// =====================================

if ($action === "remove") {

    $stmt = $conn->prepare(
        "UPDATE `$table` SET profile_photo = NULL WHERE id = ?"
    );

    $stmt->bind_param("i", $accountId);

    if (!$stmt->execute()) {
        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Could not remove profile photo."
        ]);

        exit;
    }

    $stmt->close();

    // Delete only files belonging to our upload directory
    if (
        $oldPhoto &&
        str_starts_with($oldPhoto, $relativeDirectory) &&
        basename($oldPhoto) === substr($oldPhoto, strlen($relativeDirectory))
    ) {
        $oldFile = dirname(__DIR__) . "/" . $oldPhoto;

        if (is_file($oldFile)) {
            unlink($oldFile);
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Profile photo removed successfully.",
        "profile_photo" => null
    ]);

    $conn->close();
    exit;
}

// =====================================
// UPLOAD PHOTO
// =====================================

if (!isset($_FILES["profile_photo"])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Please select a photo."
    ]);

    exit;
}

$file = $_FILES["profile_photo"];

if ($file["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Photo upload failed."
    ]);

    exit;
}

// Maximum file size: 5 MB
if ($file["size"] > 5 * 1024 * 1024) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Photo size must not exceed 5 MB."
    ]);

    exit;
}

// Validate actual image content
$imageInfo = getimagesize($file["tmp_name"]);

if ($imageInfo === false) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Please upload a valid image."
    ]);

    exit;
}

$allowedTypes = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp"
];

$mimeType = $imageInfo["mime"];

if (!isset($allowedTypes[$mimeType])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Only JPG, PNG and WEBP images are allowed."
    ]);

    exit;
}

// Generate a unique filename
$extension = $allowedTypes[$mimeType];

$fileName = $accountType . "_" . $accountId . "_" .
    bin2hex(random_bytes(16)) . "." . $extension;

$destination = $uploadDirectory . $fileName;

$newPhotoPath = $relativeDirectory . $fileName;

// Move uploaded file
if (!move_uploaded_file($file["tmp_name"], $destination)) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Could not save uploaded photo."
    ]);

    exit;
}

// =====================================
// UPDATE DATABASE
// =====================================

$stmt = $conn->prepare(
    "UPDATE `$table` SET profile_photo = ? WHERE id = ?"
);

$stmt->bind_param("si", $newPhotoPath, $accountId);

if (!$stmt->execute()) {

    unlink($destination);

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Could not update profile photo."
    ]);

    exit;
}

$stmt->close();

// Delete previous uploaded photo after successful update
if (
    $oldPhoto &&
    str_starts_with($oldPhoto, $relativeDirectory) &&
    basename($oldPhoto) === substr($oldPhoto, strlen($relativeDirectory))
) {
    $oldFile = dirname(__DIR__) . "/" . $oldPhoto;

    if (is_file($oldFile)) {
        unlink($oldFile);
    }
}

// =====================================
// SUCCESS
// =====================================

echo json_encode([
    "success" => true,
    "message" => "Profile photo updated successfully.",
    "profile_photo" => $newPhotoPath
]);

$conn->close();

?>
