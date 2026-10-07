<?php

require "db_connect_mysqli.php"; //[cite: 6]

if ($_SERVER["REQUEST_METHOD"] == "POST") { //[cite: 6]

    $name = trim($_POST["name"]); //[cite: 6]
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]); //[cite: 6]
    $password = $_POST["password"]; //[cite: 6]
    $role = trim($_POST["role"] ?? "student");

    // 1. Server-side validation
    if (empty($name) || empty($username) || empty($email) || empty($password) || empty($role)) { //[cite: 6]
        die("All fields are required."); //[cite: 6]
    }

    // Validate role against allowed values
    $allowed_roles = ["student", "admin"];
    if (!in_array($role, $allowed_roles)) {
        die("Invalid role selected.");
    }

    if (!preg_match("/^[a-zA-Z0-9_]{3,50}$/", $username)) {
        die("Username must be between 3 and 50 characters and contain only letters, numbers, and underscores.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { //[cite: 6]
        die("Please enter a valid email address."); //[cite: 6]
    }

    if (strlen($password) < 6) { //[cite: 6]
        die("Password must contain at least 6 characters."); //[cite: 6]
    }

    // 2. Check duplicate email or username
    $check_sql = "SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['username'] === $username) {
            die("This username is already taken.");
        }
        if ($row['email'] === $email) {
            die("This email is already registered."); //[cite: 6]
        }
    }
    $check_stmt->close(); //[cite: 6]

    // 3. Hash password
    $hashed_password = password_hash( //[cite: 6]
        $password, //[cite: 6]
        PASSWORD_DEFAULT //[cite: 6]
    ); //[cite: 6]

    // 4. Insert user securely including role
    $sql = "INSERT INTO users (name, username, email, role, password) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssss",
        $name,
        $username,
        $email,
        $role,
        $hashed_password
    );

    if ($stmt->execute()) { //[cite: 6]
        echo "<h2>Registration Successful!</h2>"; //[cite: 6]
        echo "<p>User has been registered securely as <strong>" . htmlspecialchars(ucfirst($role)) . "</strong> using MySQLi.</p>"; //[cite: 6]
        echo "<a href='../pages/register_user.html'>Register Another User</a>"; //[cite: 6]
    } else {
        echo "Registration failed: " . $stmt->error; //[cite: 6]
    }

    $stmt->close(); //[cite: 6]
    $conn->close(); //[cite: 6]

}
?>