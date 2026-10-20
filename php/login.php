<?php
// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// php/login.php
require_once __DIR__ . '/auth_check.php';

// 1. If already logged in, redirect immediately
if (!empty($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? 'student') === 'admin') {
        header('Location: ../Pages/admin.php');
    } else {
        header('Location: ../Pages/index.html');
    }
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $remember   = !empty($_POST['remember_me']);

    if (empty($identifier) || empty($password)) {
        $error_message = 'Please provide both your username/email and password.';
    } else {
        // Query user by username or email
        $sql = "SELECT id, name, username, email, password, role FROM users WHERE email = ? OR username = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Database query error: " . $conn->error);
        }

        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Verify password using password_verify
        if ($user && password_verify($password, $user['password'])) {
            
            // Rehash if algorithm/cost upgraded
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param('si', $new_hash, $user['id']);
                $update->execute();
                $update->close();
            }

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Populate session
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['name']          = $user['name'];
            $_SESSION['role']          = $user['role'] ?? 'student';
            $_SESSION['last_activity'] = time();

            // Set Remember-Me persistent cookie if selected
            if ($remember && function_exists('issue_remember_token')) {
                issue_remember_token($conn, (int)$user['id']);
            }

            // 2. Role-based redirection after login
           if ($_SESSION['role'] === 'admin') {
             header('Location: ../Pages/admin.php');
            }        else {
                header('Location: ../Pages/index.html');
            }
            exit;
        } else {
            $error_message = 'Invalid email/username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>StudentHub | Login</title>
    <link rel="icon" type="image/png" href="../images/Logo.jpg">
</head>
<body class="auth-page">

<header>
    <h1>StudentHub Login</h1>
</header>

<main>
    <form method="POST" action="login.php" autocomplete="off">
        <fieldset>
            <legend>Login Details</legend>

            <?php if (!empty($error_message)): ?>
                <div style="color: #ff3333; font-weight: bold; margin-bottom: 15px;">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'session_expired'): ?>
                <div style="color: #e67e22; margin-bottom: 15px;">
                    Session expired due to inactivity. Please sign in again.
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
                <div style="color: #27ae60; margin-bottom: 15px;">
                    You have been logged out successfully.
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'unauthorized'): ?>
                <div style="color: #e74c3c; margin-bottom: 15px;">
                    Please log in to access this page.
                </div>
            <?php endif; ?>

            <label for="identifier">Email Address or Username</label><br>
            <input type="text" id="identifier" name="identifier" placeholder="Enter Email or Username" required><br><br>

            <label for="password">Password</label><br>
            <input type="password" id="password" name="password" placeholder="Enter Password" required><br><br>

            <label>
                <input type="checkbox" name="remember_me" value="1"> Remember Me (30 Days)
            </label><br><br>

            <button type="submit">Login</button>
            <button type="reset">Clear</button>
        </fieldset>
    </form>

    <section>
        <h3>Need Help?</h3>
        <p>Don't have an account? <a href="../Pages/register.html">Create Account</a></p>
    </section>
</main>

<footer>
    <p>StudentHub &copy; 2026</p>
</footer>

</body>
</html>