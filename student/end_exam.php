<?php
// File: end_exam.php
// --- This script ends the exam, calculates the score, and saves it ---

session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration file
// Assumed to be in the same directory as end_exam.php
require_once "db_config.php";

// --- Database Connection Validation ---
// FIX: Using $conn as defined in db_config.php
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database Connection Failed: ' . (isset($conn) ? $conn->connect_error : "Connection object not found.")]);
    exit;
}

// Get the JSON data sent from the JavaScript frontend
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['session_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received.']);
    // FIX: Close connection before exiting
    $conn->close();
    exit;
}

$session_id = intval($data['session_id']);
$user_answers = $data['answers'] ?? []; // Default to empty array if no answers
$reason = $data['reason'] ?? 'Completed normally';

// 1. Get the session details to find out the subject and question list
// We add "AND completed = FALSE" to prevent updating an already finished exam
$sql_session = "SELECT subject, questions_list FROM exam_sessions WHERE id = ? AND completed = FALSE";
// FIX: Use $conn for database operations
if ($stmt_session = $conn->prepare($sql_session)) {
    $stmt_session->bind_param("i", $session_id);
    $stmt_session->execute();
    $result_session = $stmt_session->get_result();
    $session_data = $result_session->fetch_assoc();
    $stmt_session->close();
} else {
    error_log("Error preparing session query in end_exam.php: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Error fetching exam session details.']);
    // FIX: Close connection before exiting
    $conn->close();
    exit;
}


if (!$session_data) {
    // This can happen if the exam was already terminated (e.g., by a refresh)
    echo json_encode(['success' => false, 'message' => 'Exam session not found or already completed.']);
    // FIX: Close connection before exiting
    $conn->close();
    exit;
}

$subject_code = $session_data['subject'];
$question_ids_str = $session_data['questions_list'];
$correct_answers_map = [];

if (!empty($question_ids_str)) {
    // 2. Construct the correct question table name based on the subject
    $questions_table_name = "questions_" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $subject_code));

    // 3. Fetch the correct answers from the specific subject's question table
    // Note: Assumes you have a column named `correct_answer` in your questions table
    $sql_correct = "SELECT id, correct_answer FROM `$questions_table_name` WHERE id IN ($question_ids_str)";
    // FIX: Use $conn for database operations
    $result_correct = $conn->query($sql_correct);

    if ($result_correct) {
        while($row = $result_correct->fetch_assoc()) {
            $correct_answers_map[$row['id']] = $row['correct_answer'];
        }
    } else {
        error_log("Error fetching correct answers in end_exam.php: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Error loading correct answers.']);
        // FIX: Close connection before exiting
        $conn->close();
        exit;
    }
} else {
    // If questions_list is empty, no score can be calculated.
    echo json_encode(['success' => false, 'message' => 'No questions found for scoring.']);
    // FIX: Close connection before exiting
    $conn->close();
    exit;
}


// 4. Calculate the score
$raw_score = 0;
foreach($user_answers as $question_id => $user_answer) {
    if (isset($correct_answers_map[$question_id]) && strtolower(trim($user_answer)) === strtolower(trim($correct_answers_map[$question_id]))) {
        $raw_score++;
    }
}

// Calculate the final score as a percentage for the dashboard
$total_questions = count($correct_answers_map);
$final_score_percentage = ($total_questions > 0) ? round(($raw_score / $total_questions) * 100) : 0;


// 5. Update the exam_sessions table with the final results
$answers_str = json_encode($user_answers);
$sql_update = "UPDATE exam_sessions SET end_time = CURRENT_TIMESTAMP, completed = TRUE, score = ?, reason_for_completion = ?, answers_list = ? WHERE id = ?";
// FIX: Use $conn for database operations
if ($stmt_update = $conn->prepare($sql_update)) {
    $stmt_update->bind_param("issi", $final_score_percentage, $reason, $answers_str, $session_id);

    if ($stmt_update->execute()) {
        // --- IMPORTANT: Clear the specific active session variable ---
        // This allows the user to start a new exam for this subject later.
        if (isset($_SESSION['active_exam_id'][$subject_code])) {
            unset($_SESSION['active_exam_id'][$subject_code]);
        }
        
        // Return both raw score and total for display purposes (e.g., "Your score: 42/50")
        echo json_encode(['success' => true, 'score' => $raw_score, 'total' => $total_questions]);
    } else {
        error_log("Error updating exam session in end_exam.php: " . $stmt_update->error);
        echo json_encode(['success' => false, 'message' => 'Failed to update exam session.']);
    }
    $stmt_update->close();
} else {
    error_log("Error preparing update query in end_exam.php: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Error preparing to save exam results.']);
}

// FIX: Close the database connection at the end of the script
$conn->close();
?>
