<?php
// File: review_exam.php
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration file
require_once 'db_config.php';

// 1. --- AUTHENTICATION & INPUT VALIDATION ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

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
$user_answers = (is_string($session['answers_list']) && !empty($session['answers_list'])) ? json_decode($session['answers_list'], true) : [];
$question_ids_str = $session['questions_list'];

$review_data = [];
if (!empty($question_ids_str)) {
    $clean_subject = preg_replace('/[^a-zA-Z0-9]/', '', $session['subject']);
    $questions_table_name = "questions_" . strtolower($clean_subject);

    // MODIFIED: Added `explanation` to the SELECT statement
    $sql_questions = "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation FROM `$questions_table_name` WHERE id IN ($question_ids_str) ORDER BY FIELD(id, $question_ids_str)";
    $result_questions = $conn->query($sql_questions);

    if ($result_questions) {
        while ($question = $result_questions->fetch_assoc()) {
            $q_id = $question['id'];
            $user_answer_for_q = $user_answers[$q_id] ?? null;

            // MODIFIED: Added `explanation` to the review data array
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
                'is_correct' => ($user_answer_for_q === $question['correct_answer']),
                'explanation' => $question['explanation'] ?? null // Use null coalescing for safety
            ];
        }
    } else {
        error_log("Error fetching questions for review: " . $conn->error);
        die("Error loading questions for review. Please ensure the question table exists for " . htmlspecialchars($session['subject']));
    }
} else {
    die("No questions found for this exam session.");
}
$conn->close();

// Set the current page for the sidebar active state (part of 'history')
$currentPage = 'history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Review - <?php echo htmlspecialchars($session['subject']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { transition: background-color 0.3s, color 0.3s; }
        .sidebar-link:hover, .sidebar-link.active {
            background-color: #c51a1d;
            color: white;
        }
        .sidebar-link:hover .sidebar-icon, .sidebar-link.active .sidebar-icon {
            color: white;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

        .option { transition: background-color 0.2s, border-color 0.2s; }
        .user-correct { background-color: #dcfce7; border-color: #22c55e; border-left-width: 4px; }
        .user-incorrect { background-color: #fee2e2; border-color: #ef4444; border-left-width: 4px; }
        .actual-answer { background-color: #dbeafe; border-color: #3b82f6; border-left-width: 4px; }
    </style>
</head>
<body class="bg-gray-50 h-screen flex flex-col">

    <?php require_once 'header.php'; ?>

    <div class="flex flex-1 overflow-hidden">
        
        <?php require_once 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <main class="flex-1 overflow-y-auto p-6">
                <div class="container mx-auto">
                    <div class="bg-white rounded-2xl shadow-sm p-6 lg:p-8">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">Exam Review: <?php echo htmlspecialchars($session['subject']); ?></h2>
                                <p class="text-gray-500 mt-1">Reviewing your performance, question by question.</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <a href="full-history.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-300 flex items-center">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Mock History
                                </a>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4 mb-8 border-b pb-4">
                            <div class="flex items-center text-sm"><span class="w-4 h-4 rounded-full bg-green-100 border border-green-600 mr-2"></span> Your Correct Answer</div>
                            <div class="flex items-center text-sm"><span class="w-4 h-4 rounded-full bg-red-100 border border-red-600 mr-2"></span> Your Incorrect Answer</div>
                            <div class="flex items-center text-sm"><span class="w-4 h-4 rounded-full bg-blue-100 border border-blue-600 mr-2"></span> The Correct Answer</div>
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
                                            <div class="p-3 border rounded-md bg-slate-50 text-slate-500 italic">You did not answer this question. The correct answer was <strong><?php echo strtoupper($q['correct_answer']); ?></strong>.</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($q['explanation'])): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <h4 class="font-semibold text-gray-800 mb-2 flex items-center">
                                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                                Explanation
                                            </h4>
                                            <div class="p-4 bg-gray-50 rounded-lg text-sm text-gray-700 leading-relaxed">
                                                <?php echo nl2br(htmlspecialchars($q['explanation'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Mobile Menu Toggle ---
    const menuButton = document.getElementById('menu-button');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    const toggleSidebar = () => {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    };

    if (menuButton && sidebar && overlay) {
        menuButton.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleSidebar();
        });
        overlay.addEventListener('click', toggleSidebar);
    }
});
</script>
</body>
</html>