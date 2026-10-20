<?php
// php/logout.php
require_once __DIR__ . '/auth_check.php';

logout_user();

header('Location: login.php?msg=logged_out');
exit;