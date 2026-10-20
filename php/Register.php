<?php
require "db_connect_mysqli.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitize and trim inputs
    $name            = trim($_POST["name"] ?? "");
    $username        = trim($_POST["username"] ?? "");
    $email           = trim($_POST["email"] ?? "");
    $mobile          = trim($_POST["mobile"] ?? "");
    $role            = trim($_POST["role"] ?? "admin"); // Default fallback to admin
    $password        = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirmPassword"] ?? "";
    $course          = trim($_POST["course"] ?? "");
    $year            = trim($_POST["year"] ?? "");
    $gender          = trim($_POST["gender"] ?? "");
    $terms           = $_POST["terms"] ?? "";

    // 1. Server-side Validation
    if (empty($name) || empty($username) || empty($email) || empty($mobile) || empty($role) || empty($password) || empty($confirmPassword) || empty($course) || empty($year) || empty($gender)) {
        die("<p style='color:red;'>Error: All fields are required.</p>");
    }

    // Role whitelist check
    $allowed_roles = ["student", "admin"];
    if (!in_array($role, $allowed_roles)) {
        die("<p style='color:red;'>Error: Invalid role selected.</p>");
    }

    if ($terms !== "accepted") {
        die("<p style='color:red;'>Error: You must agree to the terms and conditions.</p>");
    }

    if (!preg_match("/^[a-zA-Z0-9_]{3,50}$/", $username)) {
        die("<p style='color:red;'>Error: Username must be 3-50 alphanumeric characters or underscores.</p>");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("<p style='color:red;'>Error: Please enter a valid email address.</p>");
    }

    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        die("<p style='color:red;'>Error: Mobile number must be exactly 10 digits.</p>");
    }

    if (strlen($password) < 6) {
        die("<p style='color:red;'>Error: Password must contain at least 6 characters.</p>");
    }

    if ($password !== $confirmPassword) {
        die("<p style='color:red;'>Error: Password and Confirm Password do not match.</p>");
    }

    // 2. Duplicate Checks (Username, Email, Mobile) in users table
    $check_sql = "SELECT username, email, mobile FROM users WHERE username = ? OR email = ? OR mobile = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);

    if (!$check_stmt) {
        die("<p style='color:red;'>Database preparation error: " . $conn->error . "</p>");
    }

    $check_stmt->bind_param("sss", $username, $email, $mobile);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['username'] === $username) {
            die("<p style='color:red;'>Error: This username is already taken.</p>");
        }
        if ($row['email'] === $email) {
            die("<p style='color:red;'>Error: This email is already registered.</p>");
        }
        if ($row['mobile'] === $mobile) {
            die("<p style='color:red;'>Error: This mobile number is already registered.</p>");
        }
    }
    $check_stmt->close();

    // 3. Password Hashing
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 4. Secure Insert into users Table
    $insert_sql = "INSERT INTO users (name, username, email, mobile, password, course, year, gender, role) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);

    if (!$stmt) {
        die("<p style='color:red;'>Database preparation error: " . $conn->error . "</p>");
    }

    $year_int = (int)$year;
    $stmt->bind_param("ssssssiss", $name, $username, $email, $mobile, $hashed_password, $course, $year_int, $gender, $role);

    if ($stmt->execute()) {
        // Automatically start session and log the user in
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id']       = $stmt->insert_id;
        $_SESSION['username']      = $username;
        $_SESSION['name']          = $name;
        $_SESSION['role']          = $role;
        $_SESSION['last_activity'] = time();

        // Redirect directly to your home page
        header("Location: ../Pages/index.html");
        exit();
    } else {
        echo "<p style='color:red;'>Registration failed: " . htmlspecialchars($stmt->error) . "</p>";
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: ../pages/register.html");
    exit();
}
?>