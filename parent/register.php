<?php

session_start();

include "../db.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "status" => "error",
        "message" => "POST method required."
    ]);

    exit;
}

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$phone = trim($_POST["phone"] ?? "");
$roll_no = trim($_POST["roll_no"] ?? "");

if (
    $name === "" ||
    $email === "" ||
    $password === "" ||
    $roll_no === ""
) {
    echo json_encode([
        "status" => "error",
        "message" => "Name, email, password and student roll number are required."
    ]);

    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Enter a valid email address."
    ]);

    exit;
}

if (strlen($password) < 8) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must be at least 8 characters."
    ]);

    exit;
}

/*
   Find the student using their roll number.
*/

$sql = "SELECT id FROM students WHERE roll_no = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $roll_no);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "Student roll number not found."
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

$student = $result->fetch_assoc();
$student_id = (int) $student["id"];

$stmt->close();

/*
   Check if a parent account already exists
   for this student.
*/

$sql = "SELECT id FROM parents WHERE student_id = ? OR email = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $student_id, $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "A parent account or email already exists."
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();

/*
   Hash the password before saving it.
*/

$hashed_password = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$sql = "
    INSERT INTO parents
        (student_id, name, email, password, phone)
    VALUES
        (?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "issss",
    $student_id,
    $name,
    $email,
    $hashed_password,
    $phone
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Parent account created successfully."
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Unable to create parent account."
    ]);
}

$stmt->close();
$conn->close();

?>