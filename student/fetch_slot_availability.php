<?php
// fetch_slot_availability.php
// This script provides real-time exam slot availability for a given date.

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set header to return JSON content
header('Content-Type: application/json');

// Include the database configuration file
// Assumed to be in the same directory as this script
require_once 'db_config.php'; 

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// Get the date from the GET request and sanitize it
$exam_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Define allowed time slots (must match those in schedule-exam.php)
$allowed_time_slots = ['11:00-13:00', '14:00-16:00', '16:00-18:00'];

$slot_counts = [];

if (!empty($exam_date)) {
    // Validate date format (basic check)
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $exam_date)) {
        echo json_encode(['error' => 'Invalid date format.']);
        $conn->close();
        exit;
    }

    foreach ($allowed_time_slots as $slot) {
        $sql_count_slot = "SELECT COUNT(id) as count FROM exams WHERE exam_date = ? AND time_slot = ?";
        if ($stmt_count_slot = $conn->prepare($sql_count_slot)) {
            $stmt_count_slot->bind_param("ss", $exam_date, $slot);
            $stmt_count_slot->execute();
            $result_count_slot = $stmt_count_slot->get_result();
            $row = $result_count_slot->fetch_assoc();
            $slot_counts[$slot] = $row['count'];
            $stmt_count_slot->close();
        } else {
            error_log("Error preparing slot count query in fetch_slot_availability.php: " . $conn->error);
            // On error, return empty counts or an error message to the client
            echo json_encode(['error' => 'Could not fetch slot counts.']);
            $conn->close();
            exit;
        }
    }
}

echo json_encode($slot_counts);

$conn->close();
?>
