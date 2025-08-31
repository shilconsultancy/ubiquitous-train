<?php
// File: review_exam.php
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration file
// Assumed to be in the same directory as review_exam.php
require_once 'db_config.php';

// 1. --- AUTHENTICATION & INPUT VALIDATION ---
// Redirect user if not logged in or if user_id is not set in session
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Get the session ID from the URL and validate it
$session_id = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
if (!$session_id) {
    die("Invalid exam session ID.");
}
$current_user_id = $_SESSION['user_id'];

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established: " . (isset($conn) ? $conn->connect_error : "Connection object not found."));
}

// 2. --- DATA FETCHING ---
// Fetch the specific exam session details from the database
$sql_session = "SELECT user_id, subject, questions_list, answers_list FROM exam_sessions WHERE id = ?";
if ($stmt_session = $conn->prepare($sql_session)) {
    $stmt_session->bind_param("i", $session_id);
    $stmt_session->execute();
    $result_session = $stmt_session->get_result();
    $session = $result_session->fetch_assoc();
    $stmt_session->close();
} else {
    error_log("Error preparing session query: " . $conn->error);
    die("Error fetching exam session details.");
}


// Security Check: Ensure the session exists and belongs to the current user
if (!$session || $session['user_id'] != $current_user_id) {
    die("Access Denied: You do not have permission to review this exam.");
}

// 3. --- DATA PREPARATION ---
// FIX: Check if answers_list is a string before decoding to prevent deprecation warning
$user_answers = (is_string($session['answers_list']) && !empty($session['answers_list'])) ? json_decode($session['answers_list'], true) : [];
$question_ids_str = $session['questions_list'];

$review_data = [];
if (!empty($question_ids_str)) {
    // Construct the dynamic table name for questions
    // Ensure the subject name is clean for table name construction
    $clean_subject = preg_replace('/[^a-zA-Z0-9]/', '', $session['subject']);
    $questions_table_name = "questions_" . strtolower($clean_subject);

    // Fetch all question details (including the correct answer) for this exam
    $sql_questions = "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer FROM `$questions_table_name` WHERE id IN ($question_ids_str) ORDER BY FIELD(id, $question_ids_str)";
    $result_questions = $conn->query($sql_questions);

    if ($result_questions) {
        // Combine all data into a single array for easy display
        while ($question = $result_questions->fetch_assoc()) {
            $q_id = $question['id'];
            $user_answer_for_q = $user_answers[$q_id] ?? null; // User's selected option ('a', 'b', etc.) or null if not answered

            $review_data[] = [
                'id' => $q_id,
                'text' => $question['question_text'],
                'options' => [
                    'a' => $question['option_a'],
                    'b' => $question['option_b'],
                    'c' => $question['option_c'],
                    'd' => $question['option_d'],
                ],
                'user_answer' => $user_answer_for_q,
                'correct_answer' => $question['correct_answer'],
                'is_correct' => ($user_answer_for_q === $question['correct_answer'])
            ];
        }
    } else {
        error_log("Error fetching questions for review: " . $conn->error);
        die("Error loading questions for review. Please ensure the question table exists for " . htmlspecialchars($session['subject']));
    }
} else {
    die("No questions found for this exam session.");
}
// Close the database connection at the end of PHP logic
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Review - <?php echo htmlspecialchars($session['subject']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .option { transition: background-color 0.2s, border-color 0.2s; }
        /* Style for the answer the user selected and was CORRECT */
        .user-correct { background-color: #dcfce7; border-color: #22c55e; border-left-width: 4px; }
        /* Style for the answer the user selected and was INCORRECT */
        .user-incorrect { background-color: #fee2e2; border-color: #ef4444; border-left-width: 4px; }
        /* Style for the ACTUAL correct answer when the user was wrong */
        .actual-answer { background-color: #dbeafe; border-color: #3b82f6; border-left-width: 4px; }
    </style>
</head>
<body class="bg-slate-100">

    <header class="bg-white shadow-md">
        <div class="container mx-auto px-6 py-4">
            <h1 class="text-2xl font-bold text-slate-800">Exam Review: <?php echo htmlspecialchars($session['subject']); ?></h1>
            <p class="text-slate-600">Reviewing your performance, question by question.</p>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="flex flex-wrap gap-4 mb-8 border-b pb-4">
                <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-green-100 border border-green-600 mr-2"></span> Your Correct Answer</div>
                <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-red-100 border border-red-600 mr-2"></span> Your Incorrect Answer</div>
                <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-blue-100 border border-blue-600 mr-2"></span> The Correct Answer</div>
            </div>

            <div class="space-y-8">
                <?php foreach ($review_data as $index => $q): ?>
                    <div class="question-block">
                        <h3 class="font-semibold text-lg text-slate-800 mb-2">Question <?php echo $index + 1; ?></h3>
                        <p class="text-slate-700 mb-4"><?php echo nl2br(htmlspecialchars($q['text'])); ?></p>
                        <div class="space-y-3">
                            <?php foreach ($q['options'] as $key => $text): ?>
                                <?php
                                    $classes = 'option p-3 border rounded-md';
                                    if ($q['is_correct'] && $key === $q['user_answer']) {
                                        $classes .= ' user-correct'; // User was right
                                    } elseif (!$q['is_correct'] && $key === $q['user_answer']) {
                                        $classes .= ' user-incorrect'; // User was wrong
                                    } elseif (!$q['is_correct'] && $key === $q['correct_answer']) {
                                        $classes .= ' actual-answer'; // Show the right one if user was wrong
                                    }
                                ?>
                                <div class="<?php echo $classes; ?>">
                                    <strong><?php echo strtoupper($key); ?>.</strong> <?php echo htmlspecialchars($text); ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($q['user_answer'] === null): ?>
                                <div class="p-3 border rounded-md bg-slate-50 text-slate-500 italic">You did not answer this question. The correct answer was <?php echo strtoupper($q['correct_answer']); ?>.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 pt-6 border-t text-center">
                <a href="dashboard.php" class="bg-theme-red hover:bg-theme-dark-red text-white font-semibold py-3 px-6 rounded-lg transition duration-300">
                    &larr; Back to Dashboard
                </a>
            </div>
        </div>
    </main>

</body>
</html>
