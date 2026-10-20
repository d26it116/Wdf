<?php
// StudentHub/Pages/admin.php
require_once __DIR__ . '/../php/auth_check.php';

// 1. Role-Based Access Control (Admin only)
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Access Denied</title>";
    echo "<link rel='stylesheet' href='../css/style.css'></head><body style='display:flex; justify-content:center; align-items:center; min-height:100vh; background:#f4f6f9; font-family:sans-serif;'>";
    echo "<div style='background:#fff; padding:35px 40px; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); text-align:center; max-width:480px;'>";
    echo "<h2 style='color:#dc2626; margin-bottom:12px;'>403 - Forbidden</h2>";
    echo "<p style='color:#475569; margin-bottom:20px; font-size:15px;'>Administrator clearance is strictly required to view this dashboard.</p>";
    echo "<a href='../php/login.php' style='display:inline-block; padding:10px 22px; background:#174ea6; color:#fff; text-decoration:none; border-radius:5px; font-weight:600;'>Return to Login</a>";
    echo "</div></body></html>";
    exit;
}

require_once __DIR__ . '/../php/db_connect_mysqli.php';

// 2. Query Live Database Statistics
// Total registered students from users table
$totalStudents = 0;
$resUsers = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($resUsers) {
    $totalStudents = (int)$resUsers->fetch_assoc()['total'];
    $resUsers->free();
}

// Active Events
$totalEvents = 0;
$resEvents = $conn->query("SELECT COUNT(*) AS total FROM events");
if ($resEvents) {
    $totalEvents = (int)$resEvents->fetch_assoc()['total'];
    $resEvents->free();
}

// Total Announcements
$totalAnnouncements = 0;
$chkAnnounce = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($chkAnnounce && $chkAnnounce->num_rows > 0) {
    $resAnn = $conn->query("SELECT COUNT(*) AS total FROM announcements");
    if ($resAnn) {
        $totalAnnouncements = (int)$resAnn->fetch_assoc()['total'];
        $resAnn->free();
    }
} else {
    $totalAnnouncements = 4;
}

// 3. Query 5 Latest Registered Students
$latestStudents = [];
$resLatest = $conn->query("SELECT id, name, username, email, role, COALESCE(created_at, 'Recently') AS reg_time FROM users ORDER BY id DESC LIMIT 5");
if ($resLatest) {
    while ($row = $resLatest->fetch_assoc()) {
        $latestStudents[] = $row;
    }
    $resLatest->free();
}

$adminName = htmlspecialchars($_SESSION['name'] ?? 'Administrator');
$adminUsername = htmlspecialchars($_SESSION['username'] ?? 'admin');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Admin Dashboard</title>
    <link rel="icon" type="image/png" href="../images/Logo.jpg">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .recent-table-card {
            background: white;
            border: 1px solid #e1e5eb;
            border-radius: 9px;
            box-shadow: 0 2px 7px rgba(0,0,0,.05);
            padding: 24px;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .recent-table-card h2 {
            color: #174ea6;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .styled-table th, .styled-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .styled-table th {
            background-color: #f8fafc;
            color: #174ea6;
            font-weight: 600;
        }
        .styled-table tr:hover td {
            background-color: #f9fbff;
        }
        .role-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .role-admin { background: #fee2e2; color: #dc2626; }
        .role-student { background: #dbeafe; color: #2563eb; }
    </style>
</head>

<body>

    <!-- ================= HEADER ================= -->
    <header>
        <div class="logo">
            <img src="../images/Logo.jpg" alt="StudentHub Logo">
            <div>
                <h1>StudentHub</h1>
                <p>Student Information Portal</p>
            </div>
        </div>

        <button id="menuBtn" class="menu-btn" type="button">☰</button>

        <nav id="mainNav">
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="about.html">About</a></li>
                <li><a href="dashboard.html">Dashboard</a></li>
                <li><a href="events.html">Events</a></li>
                <li><a href="profile.html">Profile</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li><a href="admin.php" aria-current="page">Admin</a></li>

                <!-- Dynamic Administrative Links -->
                <li><a href="user_crud_fetch.html" style="font-weight: 600; color: #174ea6;">Async Student CRUD</a></li>
                <li><a href="../php/event_management.php" style="font-weight: 600; color: #174ea6;">Manage Events</a></li>
                <li><a href="../php/student_management.php" style="font-weight: 600; color: #174ea6;">Student Directory</a></li>
                <li><a href="../php/logout.php" style="color: #ff4444; font-weight: bold;">Logout (<?= $adminUsername ?>)</a></li>
            </ul>
        </nav>
    </header>

    <!-- ================= MAIN ================= -->
    <main class="admin-page">

        <!-- ================= ADMIN HEADER ================= -->
        <section class="admin-hero">
            <div>
                <p class="section-label">ADMINISTRATION</p>
                <h2>Admin Dashboard</h2>
                <p>Manage registered students, announcements, events and academic activities from one place.</p>
            </div>

            <div class="admin-profile">
                <span>👨‍💼</span>
                <div>
                    <strong><?= $adminName ?></strong>
                    <small>System Admin (<?= $adminUsername ?>)</small>
                </div>
            </div>
        </section>

        <!-- ================= STATS SECTION ================= -->
        <section class="admin-stats" aria-label="Administration statistics">
            <article class="admin-stat-card">
                <span>👨‍🎓</span>
                <div>
                    <strong><?= number_format($totalStudents) ?></strong>
                    <p>Total Students</p>
                </div>
            </article>

            <article class="admin-stat-card">
                <span>📅</span>
                <div>
                    <strong><?= number_format($totalEvents) ?></strong>
                    <p>Active Events</p>
                </div>
            </article>

            <article class="admin-stat-card">
                <span>📢</span>
                <div>
                    <strong><?= number_format($totalAnnouncements) ?></strong>
                    <p>Announcements</p>
                </div>
            </article>
        </section>

        <!-- ================= MANAGEMENT TOOLS ================= -->
        <section class="admin-management">
            <div class="admin-section-heading">
                <div>
                    <p class="section-label">MANAGEMENT</p>
                    <h2>Administration Tools</h2>
                </div>
            </div>

            <div class="admin-tools">
                <article class="admin-tool-card">
                    <span>👨‍🎓</span>
                    <h3>Student Management (Fetch API)</h3>
                    <p>Asynchronously view, edit, add, or delete student profiles and login access.</p>
                    <a href="user_crud_fetch.html">Manage Students &rarr;</a>
                </article>

                <article class="admin-tool-card">
                    <span>📚</span>
                    <h3>Academic Records (PHP CRUD)</h3>
                    <p>View server-rendered student records, courses, and department details.</p>
                    <a href="../php/student_management.php">View Records &rarr;</a>
                </article>

                <article class="admin-tool-card">
                    <span>📅</span>
                    <h3>Event Management</h3>
                    <p>Schedule and coordinate upcoming college workshops, webinars, and hackathons.</p>
                    <a href="../php/event_management.php">Manage Events &rarr;</a>
                </article>

                <article class="admin-tool-card">
                    <span>📢</span>
                    <h3>Announcements</h3>
                    <p>Publish important circulars, exam notifications, and campus news.</p>
                    <a href="dashboard.html">View Notices &rarr;</a>
                </article>
            </div>
        </section>

        <!-- ================= LATEST REGISTRATIONS TABLE ================= -->
        <section class="recent-table-card">
            <p class="section-label">AUDIT FEED</p>
            <h2>Latest Registered Students</h2>
            <div style="overflow-x: auto;">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Username</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Registration Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($latestStudents)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:#64748b;">No recent registrations found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($latestStudents as $student): ?>
                                <tr>
                                    <td>#<?= (int)$student['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($student['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($student['username']) ?></td>
                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                    <td>
                                        <span class="role-tag role-<?= htmlspecialchars($student['role']) ?>">
                                            <?= htmlspecialchars($student['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($student['reg_time']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ================= QUICK ACTIONS ================= -->
        <section class="admin-quick-actions">
            <p class="section-label">QUICK ACTIONS</p>
            <h2>Common Tasks</h2>

            <div class="quick-action-grid">
                <a href="user_crud_fetch.html">
                    <span>➕</span>
                    <strong>Register Student</strong>
                    <small>Add new student via API</small>
                </a>

                <a href="../php/event_management.php">
                    <span>📅</span>
                    <strong>Create Event</strong>
                    <small>Schedule an activity</small>
                </a>

                <a href="dashboard.html">
                    <span>📢</span>
                    <strong>Announcements</strong>
                    <small>Review notices</small>
                </a>

                <a href="../php/student_management.php">
                    <span>📋</span>
                    <strong>Student Directory</strong>
                    <small>Browse all records</small>
                </a>
            </div>
        </section>
    </main>

    <!-- ================= FOOTER ================= -->
    <footer>
        <div>
            <h3>StudentHub</h3>
            <p>Your digital connection to college life, academics and student services.</p>
        </div>

        <div>
            <h3>Quick Links</h3>
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="dashboard.html">Dashboard</a></li>
                <li><a href="events.html">Events</a></li>
                <li><a href="contact.html">Contact</a></li>
            </ul>
        </div>

        <div>
            <h3>Administration</h3>
            <p>admin@studenthub.com</p>
            <p>+91 98765 43210</p>
            <p>Surat, Gujarat</p>
        </div>

        <hr>
        <p class="copyright">© 2026 StudentHub. All Rights Reserved.</p>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>