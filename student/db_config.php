<?php
// db_config.php - Database Connection and Configuration
// This file is assumed to be located inside the 'student/' directory.

// --- Database Credentials ---
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "lms_db";

// --- Establish Database Connection ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8 for proper handling of various characters
$conn->set_charset("utf8mb4");

// --- Define BASE_URL Constant ---
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$current_script_dir = realpath(__DIR__);
$project_root_dir = realpath($current_script_dir . '/..');
$document_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$project_root_relative_path = str_replace($document_root, '', str_replace('\\', '/', $project_root_dir));
$base_url_calculated = '/' . ltrim($project_root_relative_path, '/') . '/';
$base_url_calculated = str_replace('//', '/', $base_url_calculated);
define('BASE_URL', $base_url_calculated);
?>