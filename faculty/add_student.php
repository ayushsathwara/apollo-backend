<?php

session_start();

include "../db.php";

header("Content-Type: application/json");


/* Check faculty login */

if (!isset($_SESSION["faculty_id"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Faculty not logged in"
    ]);

    exit;
}


/* Get JSON data */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


$name =
    trim($data["name"] ?? "");

$roll_no =
    trim($data["roll_no"] ?? "");

$email =
    trim($data["email"] ?? "");

$password =
    $data["password"] ?? "";

$course =
    trim($data["course"] ?? "");

$semester =
    (int)($data["semester"] ?? 0);


/* Validate */

if (
    $name === "" ||
    $roll_no === "" ||
    $email === "" ||
    $password === "" ||
    $course === "" ||
    $semester === 0
) {

    echo json_encode([
        "status" => "error",
        "message" => "All fields are required."
    ]);

    exit;
}


/* Check duplicate email */

$email_sql =
    "SELECT id
     FROM students
     WHERE email = ?";

$email_stmt =
    $conn->prepare(
        $email_sql
    );

$email_stmt->bind_param(
    "s",
    $email
);

$email_stmt->execute();

$email_result =
    $email_stmt->get_result();


if ($email_result->num_rows > 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Email already exists."
    ]);

    $email_stmt->close();
    $conn->close();

    exit;
}

$email_stmt->close();


/* Check duplicate roll number */

$roll_sql =
    "SELECT id
     FROM students
     WHERE roll_no = ?";

$roll_stmt =
    $conn->prepare(
        $roll_sql
    );

$roll_stmt->bind_param(
    "s",
    $roll_no
);

$roll_stmt->execute();

$roll_result =
    $roll_stmt->get_result();


if ($roll_result->num_rows > 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Roll number already exists."
    ]);

    $roll_stmt->close();
    $conn->close();

    exit;
}

$roll_stmt->close();


/* Secure password */

$hashed_password =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );


/* Insert student */

$sql =
    "INSERT INTO students
    (
        name,
        roll_no,
        email,
        password,
        course,
        semester
    )
    VALUES (?, ?, ?, ?, ?, ?)";


$stmt =
    $conn->prepare($sql);


$stmt->bind_param(
    "sssssi",
    $name,
    $roll_no,
    $email,
    $hashed_password,
    $course,
    $semester
);


if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Student added successfully!"
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to add student."
    ]);

}


$stmt->close();

$conn->close();

?>