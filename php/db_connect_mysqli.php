<?php
// StudentHub/php/db_connect_mysqli.php

$host     = "localhost";
$username = "root";
$password = "";
$dbname   = "studenthub";

// Create MySQLi connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die("Database connection failed: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, "UTF-8"));
}

// Set UTF-8 encoding to match database schema
$conn->set_charset("utf8mb4");