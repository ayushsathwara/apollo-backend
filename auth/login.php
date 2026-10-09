<?php

session_start();

include "../db.php";

$email = $_POST["email"] ?? "";
$password = $_POST["password"] ?? "";
$role = $_POST["role"] ?? "";

if ($email === "" || $password === "") {
    echo "Email and password are required.";
    exit;
}

if ($role === "") {
    echo "Please select a role.";
    exit;
}


/* ================================
   STUDENT LOGIN
================================ */

if ($role === "student") {

    $sql = "SELECT * FROM students WHERE email = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("s", $email);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $student = $result->fetch_assoc();

        if (password_verify($password, $student["password"])) {

            $_SESSION["student_id"] = $student["id"];

            echo "Login successful!";

        } else {

            echo "Invalid password.";

        }

    } else {

        echo "Student not found.";

    }

    $stmt->close();

}


/* ================================
   FACULTY LOGIN
================================ */

elseif ($role === "faculty") {

    $sql = "SELECT * FROM faculty WHERE email = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("s", $email);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $faculty = $result->fetch_assoc();

        if (password_verify($password, $faculty["password"])) {

            $_SESSION["faculty_id"] = $faculty["id"];

            echo "Login successful!";

        } else {

            echo "Invalid password.";

        }

    } else {

        echo "Faculty not found.";

    }

    $stmt->close();

}


/* ================================
   PARENT LOGIN
================================ */

elseif ($role === "parent") {

    echo "Parent login will be added later.";

}


else {

    echo "Invalid role.";

}


$conn->close();

?>