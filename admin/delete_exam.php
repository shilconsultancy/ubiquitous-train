<?php
session_start();

// Enable error reporting for debugging. Turn this off in a production environment.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration file.
require_once 'db_config.php';

// --- CRITICAL DATABASE CONNECTION VALIDATION ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established. Please check 'db_config.php' and your MySQL server status.");
}

// --- Admin Authentication Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../index.php'); // Redirect to login page
    exit;
}

// Default message values, will only be set if a deletion attempt occurs
$message_type = null;
$message = null;

// Only process deletion if it's a POST request and parameters are set
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['exam_id']) && isset($_POST['super_admin_password'])) {
    $password_to_check = $_POST['super_admin_password'];
    $super_admin_id = $_SESSION['user_id'];

    // Fetch super admin's hashed password from the database
    $stmt_pass = $conn->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'super_admin'");
    $stmt_pass->bind_param("i", $super_admin_id);
    $stmt_pass->execute();
    $result_pass = $stmt_pass->get_result();
    $user = $result_pass->fetch_assoc();
    $stmt_pass->close();

    if ($user && password_verify($password_to_check, $user['password_hash'])) {
        // Password is correct, proceed with deletion logic
        $exam_id = (int)$_POST['exam_id'];

        if ($exam_id > 0) {
            $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->bind_param("i", $exam_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message_type = 'success';
                    $message = 'Exam schedule with ID #' . htmlspecialchars($exam_id) . ' has been successfully deleted.';
                } else {
                    $message_type = 'warning';
                    $message = 'Exam schedule with ID #' . htmlspecialchars($exam_id) . ' not found.';
                }
            } else {
                $message = 'Error deleting exam schedule: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Invalid exam ID provided.';
            $message_type = 'error';
        }
    } else {
        // Incorrect super admin password
        $message = 'Incorrect super admin password. Deletion denied.';
        $message_type = 'error';
    }
}

// Close the database connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

// Redirect back to the exam scheduling dashboard ONLY if a message needs to be displayed
if ($message_type !== null) {
    // Redirect to view_exam page if coming from there, otherwise to the main list
    $redirect_url = 'exam_scheduling.php';
    if (isset($_POST['exam_id'])) {
       $redirect_url = 'view_exam.php?id=' . (int)$_POST['exam_id'];
    }
    header('Location: ' . $redirect_url . '?message_type=' . urlencode($message_type) . '&message=' . urlencode($message));
    exit;
}

// If it was a direct GET, redirect back to the main list to prevent blank page
header('Location: exam_scheduling.php');
exit;
?>