<?php
// StudentHub/php/api.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db_connect_mysqli.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ==========================================
    // 1. LIST USERS OR GET SINGLE USER (GET)
    // ==========================================
    case 'GET':
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT id, name, username, email, role, created_at FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();

            if ($user) {
                echo json_encode(["status" => "success", "data" => $user]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "User not found"]);
            }
        } else {
            // Select all users excluding password hashes
            $sql = "SELECT id, name, username, email, role, created_at FROM users ORDER BY id DESC";
            $result = $conn->query($sql);
            $users = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                $result->free();
            }
            echo json_encode(["status" => "success", "data" => $users]);
        }
        break;

    // ==========================================
    // 2. ADD NEW USER (POST)
    // ==========================================
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        $name     = trim($data['name'] ?? '');
        $username = trim($data['username'] ?? '');
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $role     = trim($data['role'] ?? 'student');

        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "All fields (name, username, email, password) are required."]);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid email format."]);
            exit();
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long."]);
            exit();
        }

        $allowed_roles = ['student', 'admin'];
        if (!in_array($role, $allowed_roles, true)) {
            $role = 'student';
        }

        // Check for duplicate username or email
        $chk = $conn->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $chk->bind_param("ss", $username, $email);
        $chk->execute();
        $chk_res = $chk->get_result();
        if ($existing = $chk_res->fetch_assoc()) {
            http_response_code(409);
            $msg = ($existing['username'] === $username) ? "Username is already taken." : "Email is already registered.";
            echo json_encode(["status" => "error", "message" => $msg]);
            $chk->close();
            exit();
        }
        $chk->close();

        // Secure password hashing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (name, username, email, role, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $username, $email, $role, $hashed_password);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode([
                "status"  => "success",
                "message" => "User added successfully!",
                "id"      => $conn->insert_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to create user: " . $stmt->error]);
        }
        $stmt->close();
        break;

    // ==========================================
    // 3. UPDATE USER (PUT)
    // ==========================================
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);

        $id       = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $name     = trim($data['name'] ?? '');
        $username = trim($data['username'] ?? '');
        $email    = trim($data['email'] ?? '');
        $role     = trim($data['role'] ?? 'student');
        $password = trim($data['password'] ?? '');

        if (!$id || empty($name) || empty($username) || empty($email)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID, Name, Username, and Email are required."]);
            exit();
        }

        // Check for duplicate email or username belonging to another user
        $chk = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
        $chk->bind_param("ssi", $username, $email, $id);
        $chk->execute();
        $chk_res = $chk->get_result();
        if ($chk_res->num_rows > 0) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "Username or Email is already used by another account."]);
            $chk->close();
            exit();
        }
        $chk->close();

        // Update password if a new one is provided; otherwise preserve the current password
        if (!empty($password)) {
            if (strlen($password) < 6) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long."]);
                exit();
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $name, $username, $email, $role, $hashed, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $username, $email, $role, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User updated successfully!"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Update failed: " . $stmt->error]);
        }
        $stmt->close();
        break;

    // ==========================================
    // 4. DELETE USER (DELETE)
    // ==========================================
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        $id = filter_var($data['id'] ?? ($_GET['id'] ?? null), FILTER_VALIDATE_INT);

        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Valid user ID is required for deletion."]);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "User deleted successfully!"]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "User ID not found."]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Delete failed: " . $stmt->error]);
        }
        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "HTTP Method not allowed."]);
        break;
}