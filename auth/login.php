<?php

session_start();

include "../db.php";

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$role = $_POST["role"] ?? "";

/* ================================
   BASIC VALIDATION
================================ */

if ($email === "" || $password === "") {
    echo "Email and password are required.";
    exit;
}

if (!in_array($role, ["student", "faculty", "parent"], true)) {
    echo "Invalid role.";
    exit;
}

/* ================================
   OTP VERIFICATION CHECK
================================ */

$emailVerified =
    isset($_SESSION["otp_verified_email"]) &&
    isset($_SESSION["otp_verified_role"]) &&
    isset($_SESSION["otp_verified_at"]) &&
    strtolower($_SESSION["otp_verified_email"]) === strtolower($email) &&
    $_SESSION["otp_verified_role"] === $role &&
    (time() - (int)$_SESSION["otp_verified_at"]) <= 300 &&
    (time() - (int)$_SESSION["otp_verified_at"]) >= 0;

if (!$emailVerified) {
    echo "Please verify your email with OTP first.";
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

            session_regenerate_id(true);

            unset($_SESSION["faculty_id"]);
            unset($_SESSION["parent_id"]);

            unset($_SESSION["otp_verified_email"]);
            unset($_SESSION["otp_verified_role"]);
            unset($_SESSION["otp_verified_at"]);

            $_SESSION["student_id"] = $student["id"];

            echo "Login successful!";

        } else {
            echo "Invalid email or password.";
        }

    } else {
        echo "Invalid email or password.";
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

            session_regenerate_id(true);

            unset($_SESSION["student_id"]);
            unset($_SESSION["parent_id"]);

            unset($_SESSION["otp_verified_email"]);
            unset($_SESSION["otp_verified_role"]);
            unset($_SESSION["otp_verified_at"]);

            $_SESSION["faculty_id"] = $faculty["id"];

            echo "Login successful!";

        } else {
            echo "Invalid email or password.";
        }

    } else {
        echo "Invalid email or password.";
    }

    $stmt->close();
}

/* ================================
   PARENT LOGIN
================================ */

elseif ($role === "parent") {

    $sql = "SELECT * FROM parents WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $parent = $result->fetch_assoc();

        if (password_verify($password, $parent["password"])) {

            session_regenerate_id(true);

            unset($_SESSION["student_id"]);
            unset($_SESSION["faculty_id"]);

            unset($_SESSION["otp_verified_email"]);
            unset($_SESSION["otp_verified_role"]);
            unset($_SESSION["otp_verified_at"]);

            $_SESSION["parent_id"] = $parent["id"];

            echo "Login successful!";

        } else {
            echo "Invalid email or password.";
        }

    } else {
        echo "Invalid email or password.";
    }

    $stmt->close();
}

$conn->close();

?>