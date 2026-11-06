
<?php

header("Content-Type: application/json");

// =========================================
// ROLE AND SESSION
// =========================================

$role = $_POST["role"] ?? "admin";

if ($role === "faculty") {
    session_name("APOLLO_FACULTY_SESSION");
} elseif ($role === "admin") {
    session_name("PHPSESSID");
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Invalid role."
    ]);
    exit;
}

session_start();

if ($role === "admin" && !isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Admin login required."
    ]);
    exit;
}

if ($role === "faculty" && !isset($_SESSION["faculty_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Faculty login required."
    ]);
    exit;
}

require_once "../db.php";

// =========================================
// GET FORM DATA
// =========================================

$student_id = (int) ($_POST["student_id"] ?? 0);
$semester = (int) ($_POST["semester"] ?? 0);
$academic_year = trim($_POST["academic_year"] ?? "");

if (
    $student_id <= 0 ||
    $semester < 1 ||
    $semester > 8 ||
    $academic_year === ""
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Invalid student, semester or academic year."
    ]);
    exit;
}

// =========================================
// VERIFY STUDENT
// =========================================

$stmt = $conn->prepare(
    "SELECT semester FROM students WHERE id = ?"
);

$stmt->bind_param("i", $student_id);
$stmt->execute();

$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student || $semester > (int) $student["semester"]) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Invalid student or semester."
    ]);
    exit;
}

// =========================================
// GET EXISTING RESULT
// =========================================

$stmt = $conn->prepare(
    "SELECT id, result_file, uploaded_by_role, uploaded_by_id
     FROM external_results
     WHERE student_id = ?
     AND semester = ?
     AND academic_year = ?"
);

$stmt->bind_param(
    "iis",
    $student_id,
    $semester,
    $academic_year
);

$stmt->execute();

$existing_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$old_result_file = $existing_result["result_file"] ?? null;

// Faculty cannot replace another user's result
if (
    $role === "faculty" &&
    $existing_result &&
    (
        $existing_result["uploaded_by_role"] !== "faculty" ||
        (int) $existing_result["uploaded_by_id"] !== (int) $_SESSION["faculty_id"]
    )
) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "This result was uploaded by another user. You cannot replace it."
    ]);
    exit;
}

// =========================================
// VERIFY PDF
// =========================================

if (
    !isset($_FILES["result_file"]) ||
    $_FILES["result_file"]["error"] !== UPLOAD_ERR_OK
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Please select a PDF marksheet."
    ]);
    exit;
}

$file = $_FILES["result_file"];

if ($file["size"] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "PDF size must be 5 MB or less."
    ]);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file["tmp_name"]);

if ($mime !== "application/pdf") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Only PDF files are allowed."
    ]);
    exit;
}

// =========================================
// CREATE UPLOAD DIRECTORY
// =========================================

$upload_dir = __DIR__ . "/../uploads/results/";

if (
    !is_dir($upload_dir) &&
    !mkdir($upload_dir, 0755, true)
) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Unable to create upload directory."
    ]);
    exit;
}

// =========================================
// GENERATE UNIQUE FILENAME
// =========================================

$filename = "result_" . $student_id .
    "_sem" . $semester . "_" .
    bin2hex(random_bytes(8)) . ".pdf";

$destination = $upload_dir . $filename;

if (!move_uploaded_file($file["tmp_name"], $destination)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Unable to upload PDF."
    ]);
    exit;
}

$result_file = "uploads/results/" . $filename;
$original_filename = basename($file["name"]);

$uploader_id = $role === "admin"
    ? (int) $_SESSION["admin_id"]
    : (int) $_SESSION["faculty_id"];

// =========================================
// INSERT OR REPLACE RESULT
// =========================================

$sql = "INSERT INTO external_results
(
    student_id,
    semester,
    academic_year,
    original_filename,
    result_file,
    uploaded_by_role,
    uploaded_by_id,
    published
)
VALUES (?, ?, ?, ?, ?, ?, ?, 0)

ON DUPLICATE KEY UPDATE
    original_filename = VALUES(original_filename),
    result_file = VALUES(result_file),
    uploaded_by_role = VALUES(uploaded_by_role),
    uploaded_by_id = VALUES(uploaded_by_id),
    published = 0,
    uploaded_at = CURRENT_TIMESTAMP";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    if (file_exists($destination)) {
        unlink($destination);
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Unable to prepare database query."
    ]);
    exit;
}

$stmt->bind_param(
    "iissssi",
    $student_id,
    $semester,
    $academic_year,
    $original_filename,
    $result_file,
    $role,
    $uploader_id
);

if ($stmt->execute()) {

    // =========================================
    // DELETE OLD PDF AFTER SUCCESSFUL REPLACEMENT
    // =========================================

    if (!empty($old_result_file)) {

        $old_path = __DIR__ . "/../" .
            ltrim($old_result_file, "/\\");

        $real_old_path = realpath($old_path);
        $real_upload_dir = realpath($upload_dir);

        if (
            $real_old_path !== false &&
            $real_upload_dir !== false &&
            strpos(
                $real_old_path,
                $real_upload_dir . DIRECTORY_SEPARATOR
            ) === 0 &&
            $real_old_path !== realpath($destination) &&
            is_file($real_old_path)
        ) {
            unlink($real_old_path);
        }
    }

    echo json_encode([
        "success" => true,
        "status" => "success",
        "message" => "External result uploaded or replaced successfully. Publish it to make it visible to parents."
    ]);
} else {

    if (file_exists($destination)) {
        unlink($destination);
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Database error while saving marksheet."
    ]);
}

$stmt->close();
$conn->close();

?>
