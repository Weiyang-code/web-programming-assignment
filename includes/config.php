<?php
// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$db = "questions_bank"; // Fixed database name for consistency
$port = 3307; // Ensure the port is set correctly if needed

// Create connection
$conn = new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Set charset to utf8mb4 for proper character support
$conn->set_charset("utf8mb4");
?>