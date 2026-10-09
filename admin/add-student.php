
<?php
session_start();

header("Content-Type: application/json");

require_once "../db.php";

if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Admin login required."
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");
$roll_no = trim($data["roll_no"] ?? "");
$course = trim($data["course"] ?? "");
$semester = filter_var(
    $data["semester"] ?? null,
    FILTER_VALIDATE_INT
);
$password = $data["password"] ?? "";

if (
    $name === "" ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $roll_no === "" ||
    $course === "" ||
    $semester === false ||
    $semester === null ||
    $semester < 1 ||
    $semester > 12 ||
    strlen($password) < 8
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter valid details. Password must be at least 8 characters."
    ]);
    exit;
}

/*
 * Check whether email or roll number already exists.
 */
$check = $conn->prepare(
    "SELECT id FROM students WHERE email = ? OR roll_no = ? LIMIT 1"
);
$check->bind_param("ss", $email, $roll_no);
$check->execute();
$existing = $check->get_result();

if ($existing->num_rows > 0) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "A student with this email or roll number already exists."
    ]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

/*
 * Store a hashed password, never plain text.
 */
$sql = "INSERT INTO students (name, email, roll_no, course, semester, password)
        VALUES (?, ?, ?, ?, ?, ?)";

/*
 * NOTE: This assumes the students table has a column named
 * password and that its other required columns have defaults
 * or allow NULL. We will adjust this query if your schema differs.
 */
$stmt = $conn->prepare(
    "INSERT INTO students (name, email, roll_no, course, semester, password)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not prepare the student record. Check the students table columns."
    ]);
    $conn->close();
    exit;
}
if (!isset($hashedPassword) || $hashedPassword === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Hashed password is missing."
    ]);
    exit;
}

$stmt->bind_param(
    "ssssis",
    $name,
    $email,
    $roll_no,
    $course,
    $semester,
    $hashedPassword
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Student added successfully.",
        "student_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not add the student."
    ]);
}

$stmt->close();
$conn->close();
?>