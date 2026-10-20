<?php
// StudentHub/php/student_management.php
require_once __DIR__ . '/auth_check.php';

// Authentication and Authorization Check
if (function_exists('require_role')) {
    require_role(['admin']);
} else {
    if (empty($_SESSION["user_id"])) {
        header("Location: ../Pages/login.html");
        exit;
    }
    $role = $_SESSION["role"] ?? $_SESSION["user_role"] ?? "";
    if ($role !== "admin") {
        header("Location: ../Pages/index.html");
        exit;
    }
}

// 15-minute inactivity timeout
$timeout = 900;
if (isset($_SESSION["last_activity"]) && (time() - $_SESSION["last_activity"] > $timeout)) {
    $_SESSION = [];
    session_destroy();
    header("Location: ../Pages/login.html?timeout=1");
    exit;
}
$_SESSION["last_activity"] = time();

if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . "/db_connect_mysqli.php";
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("Database connection failed. Check db_connect_mysqli.php.");
}

// Generate CSRF Token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirectWithStatus(string $status, string $extra = ""): void {
    $url = "student_management.php?status=" . rawurlencode($status);
    if ($extra !== "") {
        $url .= "&" . $extra;
    }
    header("Location: " . $url);
    exit;
}

// Inspect `users` table columns dynamically to support any schema version
$userColumns = [];
$colQuery = $conn->query("SHOW COLUMNS FROM users");
if ($colQuery) {
    while ($c = $colQuery->fetch_assoc()) {
        $userColumns[] = $c['Field'];
    }
    $colQuery->free();
}

$hasUsername = in_array('username', $userColumns, true);
$hasMobile   = in_array('mobile', $userColumns, true);
$hasCourse   = in_array('course', $userColumns, true);
$hasCourseId = in_array('course_id', $userColumns, true);
$hasYear     = in_array('year', $userColumns, true);
$hasGender   = in_array('gender', $userColumns, true);

// Retrieve Course master records
$courses = [];
$courseResult = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name ASC");
if ($courseResult) {
    while ($cRow = $courseResult->fetch_assoc()) {
        $courses[] = $cRow;
    }
    $courseResult->free();
}

$error = "";

// Handle POST actions: Add, Update, Delete
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = $_POST["csrf_token"] ?? "";
    if (!is_string($postedToken) || !hash_equals($_SESSION["csrf_token"], $postedToken)) {
        http_response_code(403);
        exit("Invalid CSRF token. Please refresh the page.");
    }

    $action = $_POST["action"] ?? "";

    if ($action === "add" || $action === "update") {
        $id       = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
        $name     = trim((string)($_POST["name"] ?? ""));
        $username = trim((string)($_POST["username"] ?? ""));
        $email    = trim((string)($_POST["email"] ?? ""));
        $mobile   = trim((string)($_POST["mobile"] ?? ""));
        $courseId = filter_var($_POST["course_id"] ?? null, FILTER_VALIDATE_INT);
        $year     = filter_var($_POST["year"] ?? null, FILTER_VALIDATE_INT);
        $gender   = trim((string)($_POST["gender"] ?? ""));
        $password = (string)($_POST["password"] ?? "");
        $role     = trim((string)($_POST["role"] ?? "student"));

        $selectedCourse = null;
        if (!empty($courses) && $courseId) {
            foreach ($courses as $c) {
                if ((int)$c["course_id"] === (int)$courseId) {
                    $selectedCourse = $c;
                    break;
                }
            }
        }

        // Auto-generate username from email prefix if empty
        if ($hasUsername && $username === "") {
            $username = explode("@", $email)[0];
        }

        // Form Validations
        if ($name === "" || mb_strlen($name) > 100) {
            $error = "Student name is required (maximum 100 characters).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $error = "Enter a valid email address (maximum 150 characters).";
        } elseif ($action === "add" && strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($action === "update" && (!$id || $id < 1)) {
            $error = "Invalid user ID.";
        } else {
            // Duplicate Email / Username Check
            if ($action === "add") {
                if ($hasUsername) {
                    $dupStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                    $dupStmt->bind_param("ss", $email, $username);
                } else {
                    $dupStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $dupStmt->bind_param("s", $email);
                }
            } else {
                if ($hasUsername) {
                    $dupStmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
                    $dupStmt->bind_param("ssi", $email, $username, $id);
                } else {
                    $dupStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $dupStmt->bind_param("si", $email, $id);
                }
            }

            $dupStmt->execute();
            $dupResult = $dupStmt->get_result();

            if ($dupResult->num_rows > 0) {
                $error = "A user with that email or username already exists.";
                $dupStmt->close();
            } else {
                $dupStmt->close();
                $courseName = $selectedCourse["course_name"] ?? ($courses[0]["course_name"] ?? "General");

                if ($action === "add") {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Dynamically build INSERT based on active table fields
                    $cols = ["name", "email", "password", "role"];
                    $vals = [$name, $email, $hashedPassword, $role];
                    $tStr = "ssss";

                    if ($hasUsername) { $cols[] = "username"; $vals[] = $username; $tStr .= "s"; }
                    if ($hasMobile)   { $cols[] = "mobile";   $vals[] = $mobile;   $tStr .= "s"; }
                    if ($hasCourse)   { $cols[] = "course";   $vals[] = $courseName; $tStr .= "s"; }
                    if ($hasCourseId) { $cols[] = "course_id";$vals[] = (int)$courseId; $tStr .= "i"; }
                    if ($hasYear)     { $cols[] = "year";     $vals[] = (int)$year; $tStr .= "i"; }
                    if ($hasGender)   { $cols[] = "gender";   $vals[] = $gender;   $tStr .= "s"; }

                    $placeholders = implode(", ", array_fill(0, count($cols), "?"));
                    $colNames = implode(", ", $cols);

                    $insertSQL = "INSERT INTO users ({$colNames}) VALUES ({$placeholders})";
                    $stmt = $conn->prepare($insertSQL);

                    if ($stmt) {
                        $stmt->bind_param($tStr, ...$vals);
                        if ($stmt->execute()) {
                            $stmt->close();
                            redirectWithStatus("added");
                        } else {
                            $error = "Failed to add user: " . $stmt->error;
                        }
                    } else {
                        $error = "Database preparation error: " . $conn->error;
                    }
                } else {
                    // Update user
                    $updates = ["name = ?", "email = ?", "role = ?"];
                    $vals = [$name, $email, $role];
                    $tStr = "sss";

                    if ($hasUsername) { $updates[] = "username = ?"; $vals[] = $username; $tStr .= "s"; }
                    if ($hasMobile)   { $updates[] = "mobile = ?";   $vals[] = $mobile;   $tStr .= "s"; }
                    if ($hasCourse)   { $updates[] = "course = ?";   $vals[] = $courseName; $tStr .= "s"; }
                    if ($hasCourseId) { $updates[] = "course_id = ?";$vals[] = (int)$courseId; $tStr .= "i"; }
                    if ($hasYear)     { $updates[] = "year = ?";     $vals[] = (int)$year; $tStr .= "i"; }
                    if ($hasGender)   { $updates[] = "gender = ?";   $vals[] = $gender;   $tStr .= "s"; }

                    // Only update password if a new one is provided
                    if (!empty($password)) {
                        $updates[] = "password = ?";
                        $vals[] = password_hash($password, PASSWORD_DEFAULT);
                        $tStr .= "s";
                    }

                    $vals[] = $id;
                    $tStr .= "i";

                    $updateSQL = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
                    $stmt = $conn->prepare($updateSQL);

                    if ($stmt) {
                        $stmt->bind_param($tStr, ...$vals);
                        if ($stmt->execute()) {
                            $stmt->close();
                            redirectWithStatus("updated");
                        } else {
                            $error = "Failed to update record: " . $stmt->error;
                        }
                    } else {
                        $error = "Database preparation error: " . $conn->error;
                    }
                }
            }
        }
    } elseif ($action === "delete") {
        $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            $error = "Invalid student ID.";
        } elseif ($id === (int)$_SESSION["user_id"]) {
            $error = "You cannot delete your own active administrator account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $stmt->close();
                    redirectWithStatus("deleted");
                } else {
                    $error = "Record could not be deleted or does not exist.";
                    $stmt->close();
                }
            } else {
                $error = "Delete failed: " . $conn->error;
            }
        }
    }
}

// Search, Filter, and Pagination Setup
$search       = trim((string)($_GET["q"] ?? ""));
$filterRole   = trim((string)($_GET["filter_role"] ?? "student")); // Default to students
$filterGender = trim((string)($_GET["filter_gender"] ?? ""));

$perPage = 10;
$page    = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
$page    = ($page && $page > 0) ? $page : 1;

$whereClauses = [];
$params       = [];
$types        = "";

// Filter by Role (default: 'student')
if ($filterRole !== "" && $filterRole !== "all") {
    $whereClauses[] = "role = ?";
    $params[] = $filterRole;
    $types .= "s";
}

// Keyword Search
if ($search !== "") {
    $like = "%" . $search . "%";
    if ($hasCourse && $hasUsername) {
        $whereClauses[] = "(name LIKE ? OR email LIKE ? OR username LIKE ? OR course LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like]);
        $types .= "ssss";
    } elseif ($hasCourse) {
        $whereClauses[] = "(name LIKE ? OR email LIKE ? OR course LIKE ?)";
        $params = array_merge($params, [$like, $like, $like]);
        $types .= "sss";
    } else {
        $whereClauses[] = "(name LIKE ? OR email LIKE ?)";
        $params = array_merge($params, [$like, $like]);
        $types .= "ss";
    }
}

// Gender Filter
if ($hasGender && $filterGender !== "" && in_array($filterGender, ["Male", "Female", "Other"], true)) {
    $whereClauses[] = "gender = ?";
    $params[] = $filterGender;
    $types .= "s";
}

$whereSQL = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

// Count Total Records
$countSQL = "SELECT COUNT(*) AS total FROM users" . $whereSQL;
$countStmt = $conn->prepare($countSQL);
if ($countStmt) {
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    $totalRecords = (int)($countRes->fetch_assoc()["total"] ?? 0);
    $countStmt->close();
} else {
    $totalRecords = 0;
}

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page       = min($page, $totalPages);
$offset     = (int)(($page - 1) * $perPage);

// Fetch Paginated User Records
$selectSQL = "SELECT * FROM users {$whereSQL} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";

$stmt = $conn->prepare($selectSQL);
if ($stmt) {
    if (!empty($params)) {$stmt->bind_param($types, ...$params);
    }
    $stmt->execute();$result = $stmt->get_result();$students = [];
    while ($row =$result->fetch_assoc()) {
        $students[] =$row;
    }
    $stmt->close();
} else {
    die("Database query error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

// Flash status messages
$statusMessages = [
    "added"   => "User record has been successfully added to users table.",
    "updated" => "User record has been successfully updated.",
    "deleted" => "User record has been successfully deleted."
];
$status = (string)($_GET["status"] ?? "");
$successMessage = $statusMessages[$status] ?? "";

// Pre-fill form when editing
$editUser = null;
$editId = filter_var($_GET["edit"] ?? null, FILTER_VALIDATE_INT);
if ($editId &&$editId > 0) {
    $editStmt =$conn->prepare("SELECT * FROM users WHERE id = ?");
    if ($editStmt) {$editStmt->bind_param("i", $editId);$editStmt->execute();
        $editRes =$editStmt->get_result();
        $editUser =$editRes->fetch_assoc() ?: null;
        $editStmt->close();
    }
}

function buildQueryString(array $override = []): string {$current = [
        "q"             => $_GET["q"] ?? "",
        "filter_role"   => $_GET["filter_role"] ?? "student",
        "filter_gender" => $_GET["filter_gender"] ?? "",
        "page"          => $_GET["page"] ?? 1
    ];
    $merged = array_merge($current,$override);
    return http_build_query(array_filter($merged, fn($v) => $v !== "" && $v !== null));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - User & Student Management</title>
    <link rel="icon" type="image/png" href="../images/Logo.jpg">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .crud-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 15px; }
        .crud-form label { display: block; font-weight: 600; margin-bottom: 6px; }
        .crud-form input, .crud-form select { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
        .crud-form .full-width { grid-column: 1 / -1; }
        .crud-form button, .search-form button, .action-button { border: 0; border-radius: 6px; padding: 10px 16px; background: #174ea6; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; font: inherit; }
        .secondary-button { background: #64748b !important; }
        .search-form { display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0; align-items: center; }
        .search-form input, .search-form select { padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
        .crud-table { width: 100%; border-collapse: collapse; min-width: 900px; margin-top: 10px; }
        .crud-table th, .crud-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .crud-table th { background: #edf3fc; color: #174ea6; font-weight: 600; }
        .crud-table tr:hover td { background: #f8fbff; }
        .action-cell { white-space: nowrap; }
        .delete-form { display: inline; }
        .delete-button { background: #b42318; border: 0; border-radius: 6px; color: #fff; padding: 7px 12px; cursor: pointer; font: inherit; }
        .notice { padding: 12px 16px; border-radius: 6px; margin: 15px 0; font-weight: 500; }
        .notice-success { background: #e8f7ee; color: #17663a; border: 1px solid #b8e3c8; }
        .notice-error { background: #fff0f0; color: #a61b1b; border: 1px solid #f2c2c2; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .badge-student { background: #e0f2fe; color: #0369a1; }
        .badge-admin { background: #fef3c7; color: #b45309; }
        .pagination-container { display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 25px; }
        @media (max-width: 650px) { .crud-form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <!-- Header & Navigation Bar -->
    <header>
        <div class="logo">
            <img src="../images/Logo.jpg" alt="StudentHub Logo">
            <div>
                <h1>StudentHub</h1>
                <p>Student Information Portal</p>
            </div>
        </div>

        <nav id="mainNav">
            <ul>
                <li><a href="../Pages/admin.php">Admin Home</a></li>
                   <li><a href="../Pages/student_crud_fetch.html" class="active" style="border-bottom: 2px solid #fff; font-weight: bold;">Async CRUD</a></li>
                 <li><a href="event_management.php" class="active" style="border-bottom: 2px solid #fff; font-weight: bold;">Manage Events</a></li>
                <li><a href="student_management.php" class="active" style="border-bottom: 2px solid #fff; font-weight: bold;">Manage Students (Users)</a></li>
                <li><a href="logout.php" style="color: #ff4444; font-weight: bold;">Logout</a></li>
            </ul>
        </nav>
    </header>

<main class="dashboard-page" style="padding: 20px;">
    <section class="dashboard-welcome">
        <h2>User &amp; Student Management (Database: <code>users</code> table)</h2>
        <p>Total matching records found: <strong><?php echo $totalRecords; ?></strong></p>
    </section>

    <!-- Feedback Alerts -->
    <?php if ($successMessage !== ""): ?>
        <div class="notice notice-success" role="status"><?php echo h($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <div class="notice notice-error" role="alert"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Add / Edit User Form -->
    <section class="dashboard-info">
        <h2><?php echo $editUser ? "Edit User Record (#" . (int)$editUser["id"] . ")" : "Add New Student / User"; ?></h2>

        <form class="crud-form" method="post" action="student_management.php">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION["csrf_token"]); ?>">
            <input type="hidden" name="action" value="<?php echo $editUser ? "update" : "add"; ?>">
            <?php if ($editUser): ?>
                <input type="hidden" name="id" value="<?php echo (int)$editUser["id"]; ?>">
            <?php endif; ?>

            <div>
                <label for="name">Full Name *</label>
                <input id="name" name="name" maxlength="100" required placeholder="e.g. Aaryan Bhandari" value="<?php echo h($editUser["name"] ?? ""); ?>">
            </div>

            <?php if ($hasUsername): ?>
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" maxlength="50" placeholder="e.g. aaryan_bhandari" value="<?php echo h($editUser["username"] ?? ""); ?>">
            </div>
            <?php endif; ?>

            <div>
                <label for="email">Email Address *</label>
                <input id="email" name="email" type="email" maxlength="150" required placeholder="name@domain.com" value="<?php echo h($editUser["email"] ?? ""); ?>">
            </div>

            <div>
                <label for="password">Password <?php echo $editUser ? "(Leave blank to keep unchanged)" : "*"; ?></label>
                <input id="password" name="password" type="password" <?php echo $editUser ? "" : "required"; ?> minlength="6" placeholder="<?php echo $editUser ? '••••••••' : 'Min 6 characters'; ?>">
            </div>

            <div>
                <label for="role">User Role *</label>
                <select id="role" name="role" required>
                    <option value="student" <?php echo (($editUser["role"] ?? "student") === "student") ? "selected" : ""; ?>>Student</option>
                    <option value="admin" <?php echo (($editUser["role"] ?? "") === "admin") ? "selected" : ""; ?>>Admin</option>
                </select>
            </div>

            <?php if ($hasMobile): ?>
            <div>
                <label for="mobile">Mobile Number</label>
                <input id="mobile" name="mobile" maxlength="15" placeholder="e.g. 9876543210" value="<?php echo h($editUser["mobile"] ?? ""); ?>">
            </div>
            <?php endif; ?>

            <?php if (!empty($courses)): ?>
            <div>
                <label for="course_id">Course Enrolled</label>
                <select id="course_id" name="course_id">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as$c): ?>
                        <option value="<?php echo (int)$c["course_id"]; ?>" 
                            <?php echo (isset($editUser["course_id"]) && (int)$editUser["course_id"] === (int)$c["course_id"]) ? "selected" : ""; ?>>
                            <?php echo h($c["course_name"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($hasYear): ?>
            <div>
                <label for="year">Academic Year</label>
                <input id="year" name="year" type="number" min="1" max="10" value="<?php echo h($editUser["year"] ?? "1"); ?>">
            </div>
            <?php endif; ?>

            <?php if ($hasGender): ?>
            <div>
                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                    <option value="">-- Select Gender --</option>
                    <?php foreach (["Male", "Female", "Other"] as $g): ?>
                        <option value="<?php echo h($g); ?>" <?php echo (($editUser["gender"] ?? "") === $g) ? "selected" : ""; ?>>
                            <?php echo h($g); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="full-width">
                <button type="submit">
                    <?php echo $editUser ? "Update User" : "Save Record"; ?>
                </button>
                <?php if ($editUser): ?>
                    <a class="action-button secondary-button" href="student_management.php">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Search, Filter & Users Table -->
    <section class="dashboard-info">
        <h2>Existing Records (Table: <code>users</code>)</h2>

        <form class="search-form" method="get" action="student_management.php">
            <input type="search" name="q" value="<?php echo h($search); ?>" placeholder="Search name or email..." aria-label="Search">

            <select name="filter_role" aria-label="Filter by role">
                <option value="student" <?php echo ($filterRole === "student") ? "selected" : ""; ?>>Students Only</option>
                <option value="admin" <?php echo ($filterRole === "admin") ? "selected" : ""; ?>>Admins Only</option>
                <option value="all" <?php echo ($filterRole === "all") ? "selected" : ""; ?>>All Roles</option>
            </select>

            <?php if ($hasGender): ?>
            <select name="filter_gender" aria-label="Filter by gender">
                <option value="">All Genders</option>
                <?php foreach (["Male", "Female", "Other"] as $g): ?>
                    <option value="<?php echo h($g); ?>" <?php echo ($filterGender ===$g) ? "selected" : ""; ?>>
                        <?php echo h($g); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button type="submit">Filter / Search</button>
            <a class="action-button secondary-button" href="student_management.php">Reset</a>
        </form>

        <div style="overflow-x:auto;">
            <table class="crud-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <?php if ($hasUsername): ?><th>Username</th><?php endif; ?>
                        <th>Email</th>
                        <th>Role</th>
                        <?php if ($hasMobile): ?><th>Mobile</th><?php endif; ?>
                        <?php if ($hasCourse): ?><th>Course</th><?php endif; ?>
                        <?php if ($hasYear): ?><th>Year</th><?php endif; ?>
                        <?php if ($hasGender): ?><th>Gender</th><?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center; padding: 20px; color: #64748b;">
                            No matching user records found in the <code>users</code> table.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as$u): ?>
                        <tr>
                            <td><?php echo (int)$u["id"]; ?></td>
                            <td><strong><?php echo h($u["name"]); ?></strong></td>
                            <?php if ($hasUsername): ?><td><code><?php echo h($u["username"] ?? "-"); ?></code></td><?php endif; ?>
                            <td><?php echo h($u["email"]); ?></td>
                            <td>
                                <span class="badge <?php echo ($u['role'] === 'admin') ? 'badge-admin' : 'badge-student'; ?>">
                                    <?php echo ucfirst(h($u["role"])); ?>
                                </span>
                            </td>
                            <?php if ($hasMobile): ?><td><?php echo h($u["mobile"] ?? "-"); ?></td><?php endif; ?>
                            <?php if ($hasCourse): ?><td><?php echo h($u["course"] ?? "-"); ?></td><?php endif; ?>
                            <?php if ($hasYear): ?><td><?php echo (int)($u["year"] ?? 1); ?></td><?php endif; ?>
                            <?php if ($hasGender): ?><td><?php echo h($u["gender"] ?? "-"); ?></td><?php endif; ?>
                            <td class="action-cell">
                                <a class="action-button" href="student_management.php?edit=<?php echo (int)$u["id"]; ?>">Edit</a>
                                <form class="delete-form" method="post" action="student_management.php" onsubmit="return confirm('Confirm deletion of user #<?php echo (int)$u['id']; ?> (<?php echo h(addslashes($u['name'])); ?>)?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION["csrf_token"]); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$u["id"]; ?>">
                                    <button class="delete-button" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a class="action-button secondary-button" href="student_management.php?<?php echo buildQueryString(['page' => $page - 1]); ?>">
                        &laquo; Previous
                    </a>
                <?php endif; ?>

                <span>Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> (<?php echo$totalRecords; ?> total)</span>

                <?php if ($page <$totalPages): ?>
                    <a class="action-button" href="student_management.php?<?php echo buildQueryString(['page' => $page + 1]); ?>">
                        Next &raquo;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html> 