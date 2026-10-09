<?php

session_start();

include "../db.php";

header("Content-Type: application/json");


/* ================================
   CHECK FACULTY LOGIN
================================ */

if (!isset($_SESSION["faculty_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Faculty not logged in"
    ]);

    exit;
}

$faculty_id = $_SESSION["faculty_id"];


/* ================================
   GET FORM DATA
================================ */

$subject =
    trim($_POST["subject"] ?? "");

$title =
    trim($_POST["title"] ?? "");

$description =
    trim($_POST["description"] ?? "");

$due_date =
    trim($_POST["due_date"] ?? "");


/* ================================
   VALIDATION
================================ */

if (
    $subject === "" ||
    $title === "" ||
    $due_date === ""
) {

    echo json_encode([
        "status" => "error",
        "message" => "Please fill all required fields."
    ]);

    exit;
}


/* ================================
   CHECK SUBJECT
================================ */

$sql = "
    SELECT id
    FROM faculty_subjects
    WHERE faculty_id = ?
    AND subject = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $faculty_id,
    $subject
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "message" => "You are not assigned to this subject."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}

$stmt->close();


/* ================================
   CHECK FILE
================================ */

if (
    !isset($_FILES["assignment_file"]) ||
    $_FILES["assignment_file"]["error"] !== UPLOAD_ERR_OK
) {

    echo json_encode([
        "status" => "error",
        "message" => "Please upload an assignment file."
    ]);

    $conn->close();

    exit;
}


$file =
    $_FILES["assignment_file"];


/* ================================
   FILE SIZE
================================ */

$maxSize =
    10 * 1024 * 1024; // 10 MB

if ($file["size"] > $maxSize) {

    echo json_encode([
        "status" => "error",
        "message" => "File size must be less than 10 MB."
    ]);

    $conn->close();

    exit;
}


/* ================================
   FILE EXTENSION
================================ */

$originalFileName =
    $file["name"];

$extension =
    strtolower(
        pathinfo(
            $originalFileName,
            PATHINFO_EXTENSION
        )
    );


$allowedExtensions = [
    "pdf",
    "doc",
    "docx",
    "zip"
];


if (
    !in_array(
        $extension,
        $allowedExtensions
    )
) {

    echo json_encode([
        "status" => "error",
        "message" => "Only PDF, DOC, DOCX and ZIP files are allowed."
    ]);

    $conn->close();

    exit;
}


/* ================================
   UPLOAD FOLDER
================================ */

$uploadFolder =
    dirname(__DIR__) .
    "/uploads/assignments/";


if (!is_dir($uploadFolder)) {

    mkdir(
        $uploadFolder,
        0777,
        true
    );
}


/* ================================
   CREATE UNIQUE FILE NAME
================================ */

$newFileName =
    "assignment_" .
    $faculty_id .
    "_" .
    time() .
    "_" .
    bin2hex(random_bytes(5)) .
    "." .
    $extension;


$filePath =
    $uploadFolder .
    $newFileName;


/* ================================
   MOVE FILE
================================ */

if (
    !move_uploaded_file(
        $file["tmp_name"],
        $filePath
    )
) {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to save uploaded file."
    ]);

    $conn->close();

    exit;
}


/* ================================
   DATABASE PATH
================================ */

$databaseFilePath =
    "uploads/assignments/" .
    $newFileName;


/* ================================
   INSERT ASSIGNMENT
================================ */

$sql = "
    INSERT INTO assignments
    (
        faculty_id,
        subject,
        title,
        description,
        due_date,
        file_name,
        file_path
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
";


$stmt =
    $conn->prepare($sql);


$stmt->bind_param(
    "issssss",
    $faculty_id,
    $subject,
    $title,
    $description,
    $due_date,
    $originalFileName,
    $databaseFilePath
);


/* ================================
   SAVE
================================ */

if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Assignment added successfully."
    ]);

}
else {

    // Delete uploaded file if database insert fails

    if (file_exists($filePath)) {

        unlink($filePath);

    }

    echo json_encode([
        "status" => "error",
        "message" => "Failed to save assignment."
    ]);

}


$stmt->close();
$conn->close();

?>