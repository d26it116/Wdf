<?php
// php/auth_check.php

// 1. Enforce strict session cookie attributes
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 0,                                                    // Closes on browser exit
    'path'     => '/',
    'domain'   => '',                                                   // Current host
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only
    'httponly' => true,                                                 // Mitigate XSS cookie theft
    'samesite' => 'Strict'                                              // Mitigate CSRF
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect_mysqli.php';

define('SESSION_TIMEOUT_DURATION', 900); // 15 minutes inactivity window

/**
 * Handle idle session expiry
 */
function check_session_timeout(): void {
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_DURATION)) {
            logout_user();
            header('Location: login.php?msg=session_expired');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Split-Token Remember-Me validator
 */
function verify_remember_me_cookie(mysqli $conn): void {
    if (isset($_SESSION['user_id']) || empty($_COOKIE['remember_me'])) {
        return;
    }

    $token_parts = explode(':', $_COOKIE['remember_me']);
    if (count($token_parts) !== 2) {
        clear_remember_cookie();
        return;
    }

    [$selector, $validator] = $token_parts;

    // Lookup token record by public selector
    $stmt = $conn->prepare('SELECT id, user_id, token_hash, expires_at FROM auth_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1');
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $result = $stmt->get_result();
    $token_record = $result->fetch_assoc();
    $stmt->close();

    if ($token_record && hash_equals($token_record['token_hash'], hash('sha256', $validator))) {
        // Fetch user from users table
        $user_stmt = $conn->prepare('SELECT id, name, username, email, role FROM users WHERE id = ? LIMIT 1');
        $user_stmt->bind_param('i', $token_record['user_id']);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();

        if ($user) {
            // Prevent session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['name']          = $user['name'];
            $_SESSION['role']          = $user['role'] ?? 'student';
            $_SESSION['last_activity'] = time();

            // Rotate token for anti-replay defense
            issue_remember_token($conn, (int)$user['id'], (int)$token_record['id']);
        }
    } else {
        clear_remember_cookie();
    }
}

/**
 * Generate split token (Selector + Validator), save hash to DB, send cookie
 */
function issue_remember_token(mysqli $conn, int $user_id, ?int $old_token_id = null): void {
    if ($old_token_id !== null) {
        $del = $conn->prepare('DELETE FROM auth_tokens WHERE id = ?');
        $del->bind_param('i', $old_token_id);
        $del->execute();
        $del->close();
    }

    $selector         = bin2hex(random_bytes(8));       // 16 chars (public lookup)
    $validator        = bin2hex(random_bytes(32));     // 64 chars (private secret)
    $token_hash       = hash('sha256', $validator);    // 64 chars sha256
    $expires_at_stamp = time() + (86400 * 30);         // 30 days
    $expires_at       = date('Y-m-d H:i:s', $expires_at_stamp);

    $stmt = $conn->prepare('INSERT INTO auth_tokens (selector, token_hash, user_id, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssis', $selector, $token_hash, $user_id, $expires_at);
    $stmt->execute();
    $stmt->close();

    setcookie('remember_me', "{$selector}:{$validator}", [
        'expires'  => $expires_at_stamp,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

/**
 * Remove persistent cookie
 */
function clear_remember_cookie(): void {
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        unset($_COOKIE['remember_me']);
    }
}

/**
 * Guard for dashboard pages (RBAC)
 */
function require_role(array $allowed_roles = []): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php?msg=unauthorized');
        exit;
    }

    if (!empty($allowed_roles)) {
        $current_role = $_SESSION['role'] ?? 'student';
        if (!in_array($current_role, $allowed_roles, true)) {
            http_response_code(403);
            die('<h2 style="color:red; text-align:center; margin-top:50px;">403 Forbidden: You do not have permission to view this page.</h2>');
        }
    }
}

/**
 * Full logout routine
 */
function logout_user(): void {
    global $conn;

    if (!empty($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) === 2) {
            $stmt = $conn->prepare('DELETE FROM auth_tokens WHERE selector = ?');
            $stmt->bind_param('s', $parts[0]);
            $stmt->execute();
            $stmt->close();
        }
        clear_remember_cookie();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

// Run checks automatically on every request that includes this file
check_session_timeout();
verify_remember_me_cookie($conn);