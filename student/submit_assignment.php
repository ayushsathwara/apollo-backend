<?php

session_start();

include "../db.php";

header("Content-Type: application/json");


/* ================================
   CHECK STUDENT LOGIN
================================ */

if (!isset($_SESSION["student_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Student not logged in"
    ]);

    exit;
}

$student_id =
    $_SESSION["student_id"];


/* ================================
   GET ASSIGNMENT ID
================================ */

$assignment_id =
    intval($_POST["assignment_id"] ?? 0);


if ($assignment_id <= 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid assignment."
    ]);

    exit;
}


/* ================================
   CHECK ASSIGNMENT
================================ */

$sql = "
    SELECT id, due_date
    FROM assignments
    WHERE id = ?
";

$stmt =
    $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $assignment_id
);

$stmt->execute();

$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Assignment not found."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$assignment =
    $result->fetch_assoc();

$stmt->close();


/* ================================
   CHECK DUE DATE
================================ */

if (
    $assignment["due_date"] <
    date("Y-m-d")
) {

    echo json_encode([
        "status" => "error",
        "message" => "Assignment due date has passed."
    ]);

    $conn->close();

    exit;
}


/* ================================
   CHECK ALREADY SUBMITTED
================================ */

$sql = "
    SELECT id
    FROM assignment_submissions
    WHERE assignment_id = ?
    AND student_id = ?
";

$stmt =
    $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $assignment_id,
    $student_id
);

$stmt->execute();

$result =
    $stmt->get_result();


if ($result->num_rows > 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Assignment already submitted."
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
    !isset($_FILES["submission_file"]) ||
    $_FILES["submission_file"]["error"] !== UPLOAD_ERR_OK
) {

    echo json_encode([
        "status" => "error",
        "message" => "Please select a file to upload."
    ]);

    $conn->close();

    exit;
}


$file =
    $_FILES["submission_file"];


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
    "/uploads/submissions/";


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
    "student_" .
    $student_id .
    "_assignment_" .
    $assignment_id .
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
   DATABASE FILE PATH
================================ */

$databaseFilePath =
    "uploads/submissions/" .
    $newFileName;


/* ================================
   SAVE SUBMISSION
================================ */

$sql = "
    INSERT INTO assignment_submissions
    (
        assignment_id,
        student_id,
        file_name,
        file_path
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?
    )
";


$stmt =
    $conn->prepare($sql);


$stmt->bind_param(
    "iiss",
    $assignment_id,
    $student_id,
    $originalFileName,
    $databaseFilePath
);


/* ================================
   RESULT
================================ */

if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Assignment submitted successfully."
    ]);

}
else {

    // Delete uploaded file if database insert fails

    if (file_exists($filePath)) {

        unlink($filePath);

    }

    echo json_encode([
        "status" => "error",
        "message" => "Unable to save submission."
    ]);

}


$stmt->close();
$conn->close();

?>