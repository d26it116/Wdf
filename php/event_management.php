<?php
// StudentHub/php/event_management.php
require_once __DIR__ . '/auth_check.php';

// 1. Role and Authentication Check
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

// Session timeout (15 mins)
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
    exit("Database connection failed. Please check db_connect_mysqli.php.");
}

// Generate CSRF Token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirectWithStatus(string $status): void {
    header("Location: event_management.php?status=" . rawurlencode($status));
    exit;
}

// Check database columns dynamically
$eventCols = [];
$colRes = $conn->query("SHOW COLUMNS FROM events");
if ($colRes) {
    while ($r = $colRes->fetch_assoc()) {
        $eventCols[] = $r['Field'];
    }
    $colRes->free();
}

$hasCategory = in_array('category', $eventCols, true);
$hasLocation = in_array('location', $eventCols, true);
$hasPoster   = in_array('poster', $eventCols, true);

$error = "";
$uploadDir = __DIR__ . "/../uploads/events/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle Form Submissions: Add, Edit, Delete
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = $_POST["csrf_token"] ?? "";
    if (!is_string($postedToken) || !hash_equals($_SESSION["csrf_token"], $postedToken)) {
        http_response_code(403);
        exit("Invalid CSRF token. Please refresh the page.");
    }

    $action = $_POST["action"] ?? "";

    if ($action === "add" || $action === "update") {
        $id          = filter_var($_POST["event_id"] ?? null, FILTER_VALIDATE_INT);
        $eventName   = trim((string)($_POST["event_name"] ?? ""));
        $eventDate   = trim((string)($_POST["event_date"] ?? ""));
        $category    = trim((string)($_POST["category"] ?? "Technical"));
        $location    = trim((string)($_POST["location"] ?? ""));
        $description = trim((string)($_POST["description"] ?? ""));
        $oldPoster   = trim((string)($_POST["old_poster"] ?? ""));

        // Basic Validations
        if ($eventName === "" || mb_strlen($eventName) > 100) {
            $error = "Event name is required (max 100 characters).";
        } elseif ($eventDate === "") {
            $error = "Please provide a valid event date.";
        } elseif ($action === "update" && (!$id || $id < 1)) {
            $error = "Invalid event ID.";
        } else {
            $posterFileName = $oldPoster;

            // Handle Poster File Upload with strict validation
            if (isset($_FILES["poster"]) && $_FILES["poster"]["error"] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES["poster"];

                if ($file["error"] !== UPLOAD_ERR_OK) {
                    $error = "File upload failed with error code: " . $file["error"];
                } else {
                    // 1. File Size Validation (Max 2MB = 2 * 1024 * 1024 bytes)
                    $maxSize = 2 * 1024 * 1024;
                    if ($file["size"] > $maxSize) {
                        $error = "Event poster size must not exceed 2 MB.";
                    } else {
                        // 2. File Extension and MIME Type Validation
                        $allowedMimes = [
                            'jpg'  => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png'  => 'image/png',
                            'webp' => 'image/webp'
                        ];

                        $fileExt = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $file["tmp_name"]);
                        finfo_close($finfo);

                        if (!array_key_exists($fileExt, $allowedMimes) || !in_array($mimeType, $allowedMimes, true)) {
                            $error = "Invalid poster format. Allowed extensions: JPG, JPEG, PNG, WEBP.";
                        } else {
                            // Generate unique secure filename
                            $newFileName = "event_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $fileExt;
                            $destination = $uploadDir . $newFileName;

                            if (move_uploaded_file($file["tmp_name"], $destination)) {
                                // Delete previous poster file if updating
                                if ($action === "update" && !empty($oldPoster) && file_exists($uploadDir . $oldPoster)) {
                                    @unlink($uploadDir . $oldPoster);
                                }
                                $posterFileName = $newFileName;
                            } else {
                                $error = "Unable to save uploaded poster file.";
                            }
                        }
                    }
                }
            }

            // Database Insertion or Update if no errors
            if ($error === "") {
                if ($action === "add") {
                    if ($hasCategory && $hasLocation && $hasPoster) {
                        $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, category, location, description, poster) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssss", $eventName, $eventDate, $category, $location, $description, $posterFileName);
                    } elseif ($hasPoster) {
                        $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, description, poster) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $eventName, $eventDate, $description, $posterFileName);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, description) VALUES (?, ?, ?)");
                        $stmt->bind_param("sss", $eventName, $eventDate, $description);
                    }

                    if ($stmt && $stmt->execute()) {
                        $stmt->close();
                        redirectWithStatus("added");
                    } else {
                        $error = "Failed to insert event: " . ($stmt ? $stmt->error : $conn->error);
                    }
                } else {
                    // Update
                    if ($hasCategory && $hasLocation && $hasPoster) {
                        $stmt = $conn->prepare("UPDATE events SET event_name = ?, event_date = ?, category = ?, location = ?, description = ?, poster = ? WHERE event_id = ?");
                        $stmt->bind_param("ssssssi", $eventName, $eventDate, $category, $location, $description, $posterFileName, $id);
                    } elseif ($hasPoster) {
                        $stmt = $conn->prepare("UPDATE events SET event_name = ?, event_date = ?, description = ?, poster = ? WHERE event_id = ?");
                        $stmt->bind_param("ssssi", $eventName, $eventDate, $description, $posterFileName, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE events SET event_name = ?, event_date = ?, description = ? WHERE event_id = ?");
                        $stmt->bind_param("sssi", $eventName, $eventDate, $description, $id);
                    }

                    if ($stmt && $stmt->execute()) {
                        $stmt->close();
                        redirectWithStatus("updated");
                    } else {
                        $error = "Failed to update event: " . ($stmt ? $stmt->error : $conn->error);
                    }
                }
            }
        }
    } elseif ($action === "delete") {
        $id = filter_var($_POST["event_id"] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            $error = "Invalid event ID.";
        } else {
            // Retrieve poster file to delete from disk
            if ($hasPoster) {
                $pStmt = $conn->prepare("SELECT poster FROM events WHERE event_id = ?");
                $pStmt->bind_param("i", $id);
                $pStmt->execute();
                $pRes = $pStmt->get_result();
                if ($pRow = $pRes->fetch_assoc()) {
                    if (!empty($pRow["poster"]) && file_exists($uploadDir . $pRow["poster"])) {
                        @unlink($uploadDir . $pRow["poster"]);
                    }
                }
                $pStmt->close();
            }

            $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $stmt->close();
                    redirectWithStatus("deleted");
                } else {
                    $error = "Event could not be deleted or does not exist.";
                    $stmt->close();
                }
            } else {
                $error = "Delete failed: " . $conn->error;
            }
        }
    }
}

// Search and Filter Setup
$search = trim((string)($_GET["q"] ?? ""));
$categoryFilter = trim((string)($_GET["category"] ?? ""));

$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(event_name LIKE ? OR description LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if ($hasCategory && $categoryFilter !== "" && $categoryFilter !== "all") {
    $where[] = "category = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}

$whereSQL = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

// Fetch all events
$querySQL = "SELECT * FROM events {$whereSQL} ORDER BY event_date ASC, event_id DESC";
$stmt = $conn->prepare($querySQL);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
} else {
    die("Database query error: " . htmlspecialchars($conn->error, ENT_QUOTES, "UTF-8"));
}

// Flash status messages
$statusMessages = [
    "added"   => "Event added successfully.",
    "updated" => "Event updated successfully.",
    "deleted" => "Event deleted successfully."
];
$status = (string)($_GET["status"] ?? "");
$successMessage = $statusMessages[$status] ?? "";

// Pre-fill form if editing
$editEvent = null;
$editId = filter_var($_GET["edit"] ?? null, FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    $eStmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
    if ($eStmt) {
        $eStmt->bind_param("i", $editId);
        $eStmt->execute();
        $editRes = $eStmt->get_result();
        $editEvent = $editRes->fetch_assoc() ?: null;
        $eStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Event Management</title>
    <link rel="icon" type="image/png" href="../images/Logo.jpg">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .crud-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 15px; }
        .crud-form label { display: block; font-weight: 600; margin-bottom: 6px; }
        .crud-form input, .crud-form select, .crud-form textarea { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
        .crud-form textarea { resize: vertical; min-height: 80px; }
        .crud-form .full-width { grid-column: 1 / -1; }
        .crud-form button, .search-form button, .action-button { border: 0; border-radius: 6px; padding: 10px 16px; background: #174ea6; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; font: inherit; }
        .secondary-button { background: #64748b !important; }
        .search-form { display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0; align-items: center; }
        .search-form input, .search-form select { padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
        .crud-table { width: 100%; border-collapse: collapse; min-width: 850px; margin-top: 10px; }
        .crud-table th, .crud-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .crud-table th { background: #edf3fc; color: #174ea6; font-weight: 600; }
        .crud-table tr:hover td { background: #f8fbff; }
        .action-cell { white-space: nowrap; }
        .delete-form { display: inline; }
        .delete-button { background: #b42318; border: 0; border-radius: 6px; color: #fff; padding: 7px 12px; cursor: pointer; font: inherit; }
        .notice { padding: 12px 16px; border-radius: 6px; margin: 15px 0; font-weight: 500; }
        .notice-success { background: #e8f7ee; color: #17663a; border: 1px solid #b8e3c8; }
        .notice-error { background: #fff0f0; color: #a61b1b; border: 1px solid #f2c2c2; }
        .poster-preview { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; display: block; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; background: #e0f2fe; color: #0369a1; }
        @media (max-width: 650px) { .crud-form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <!-- Admin Header & Navigation -->
    <header>
        <div class="logo">
            <img src="../images/Logo.jpg" alt="StudentHub Logo">
            <div>
                <h1>StudentHub Admin</h1>
                <p>Event Management System</p>
            </div>
        </div>
        <nav id="mainNav">
            <ul>
                <li><a href="../Pages/admin.php">Admin Home</a></li>
                <li><a href="../Pages/student_crud_fetch.html">Async Student CRUD</a></li>
                <li><a href="student_management.php">Students</a></li>
                <li><a href="event_management.php" class="active" style="border-bottom: 2px solid #fff; font-weight: bold;">Manage Events</a></li>
                <li><a href="logout.php" style="color: #ff4444; font-weight: bold;">Logout</a></li>
            </ul>
        </nav>
    </header>

<main class="dashboard-page" style="padding: 20px;">
    <section class="dashboard-welcome">
        <h2>Campus Events Control Panel</h2>
        <p>Total events recorded: <strong><?php echo count($events); ?></strong></p>
    </section>

    <!-- Feedback Alerts -->
    <?php if ($successMessage !== ""): ?>
        <div class="notice notice-success"><?php echo h($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <div class="notice notice-error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Add / Edit Event Form -->
    <section class="dashboard-info">
        <h2><?php echo $editEvent ? "Edit Event (#" . (int)$editEvent["event_id"] . ")" : "Add New Campus Event"; ?></h2>

        <form class="crud-form" method="post" action="event_management.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION["csrf_token"]); ?>">
            <input type="hidden" name="action" value="<?php echo $editEvent ? "update" : "add"; ?>">
            <?php if ($editEvent): ?>
                <input type="hidden" name="event_id" value="<?php echo (int)$editEvent["event_id"]; ?>">
                <input type="hidden" name="old_poster" value="<?php echo h($editEvent["poster"] ?? ""); ?>">
            <?php endif; ?>

            <div>
                <label for="event_name">Event Name *</label>
                <input id="event_name" name="event_name" maxlength="100" required placeholder="e.g. CodeCraft Hackathon" value="<?php echo h($editEvent["event_name"] ?? ""); ?>">
            </div>

            <div>
                <label for="event_date">Event Date *</label>
                <input id="event_date" name="event_date" type="date" required value="<?php echo h($editEvent["event_date"] ?? ""); ?>">
            </div>

            <?php if ($hasCategory): ?>
            <div>
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <?php 
                    $categories = ["Technical", "Workshop", "Sports", "Cultural", "Career"];
                    foreach ($categories as $cat): 
                    ?>
                        <option value="<?php echo h($cat); ?>" <?php echo (($editEvent["category"] ?? "") === $cat) ? "selected" : ""; ?>>
                            <?php echo h($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($hasLocation): ?>
            <div>
                <label for="location">Location / Venue</label>
                <input id="location" name="location" maxlength="100" placeholder="e.g. Computer Lab / Auditorium" value="<?php echo h($editEvent["location"] ?? ""); ?>">
            </div>
            <?php endif; ?>

            <div class="full-width">
                <label for="description">Event Description</label>
                <textarea id="description" name="description" maxlength="255" placeholder="Brief details about the event..."><?php echo h($editEvent["description"] ?? ""); ?></textarea>
            </div>

            <!-- Poster File Upload with Type & Size Guidelines -->
            <div class="full-width">
                <label for="poster">
                    Event Poster (Allowed: JPG, PNG, WEBP &bull; Max Size: 2 MB)
                </label>
                <input id="poster" name="poster" type="file" accept=".jpg,.jpeg,.png,.webp">
                <?php if ($editEvent && !empty($editEvent["poster"])): ?>
                    <p style="margin-top: 8px; font-size: 0.9rem; color: #64748b;">
                        Current Poster: <a href="../uploads/events/<?php echo h($editEvent["poster"]); ?>" target="_blank" style="color:#174ea6; font-weight:600;">View Current Poster</a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="full-width">
                <button type="submit">
                    <?php echo $editEvent ? "Update Event" : "Save Event"; ?>
                </button>
                <?php if ($editEvent): ?>
                    <a class="action-button secondary-button" href="event_management.php">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Events List Table -->
    <section class="dashboard-info">
        <h2>Existing Events Directory</h2>

        <form class="search-form" method="get" action="event_management.php">
            <input type="search" name="q" value="<?php echo h($search); ?>" placeholder="Search by name or description...">
            
            <?php if ($hasCategory): ?>
            <select name="category">
                <option value="all">All Categories</option>
                <?php foreach (["Technical", "Workshop", "Sports", "Cultural", "Career"] as $cat): ?>
                    <option value="<?php echo h($cat); ?>" <?php echo ($categoryFilter === $cat) ? "selected" : ""; ?>>
                        <?php echo h($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button type="submit">Filter / Search</button>
            <a class="action-button secondary-button" href="event_management.php">Reset</a>
        </form>

        <div style="overflow-x:auto;">
            <table class="crud-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Poster</th>
                        <th>Event Name</th>
                        <th>Date</th>
                        <?php if ($hasCategory): ?><th>Category</th><?php endif; ?>
                        <?php if ($hasLocation): ?><th>Location</th><?php endif; ?>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding: 20px; color: #64748b;">
                            No events found matching your search.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $ev): ?>
                        <tr>
                            <td><?php echo (int)$ev["event_id"]; ?></td>
                            <td>
                                <?php if (!empty($ev["poster"]) && file_exists($uploadDir . $ev["poster"])): ?>
                                    <a href="../uploads/events/<?php echo h($ev["poster"]); ?>" target="_blank">
                                        <img class="poster-preview" src="../uploads/events/<?php echo h($ev["poster"]); ?>" alt="Poster">
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.85rem; color:#94a3b8;">No Poster</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo h($ev["event_name"]); ?></strong></td>
                            <td><?php echo date("d M Y", strtotime($ev["event_date"])); ?></td>
                            <?php if ($hasCategory): ?>
                                <td><span class="badge"><?php echo h($ev["category"] ?? "General"); ?></span></td>
                            <?php endif; ?>
                            <?php if ($hasLocation): ?>
                                <td><?php echo h($ev["location"] ?? "-"); ?></td>
                            <?php endif; ?>
                            <td><?php echo h($ev["description"] ?? "-"); ?></td>
                            <td class="action-cell">
                                <a class="action-button" href="event_management.php?edit=<?php echo (int)$ev["event_id"]; ?>">Edit</a>
                                <form class="delete-form" method="post" action="event_management.php" onsubmit="return confirm('Are you sure you want to delete event \'<?php echo h(addslashes($ev['event_name'])); ?>\'?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION["csrf_token"]); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="event_id" value="<?php echo (int)$ev["event_id"]; ?>">
                                    <button class="delete-button" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>