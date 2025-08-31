<?php
// ==========================================================
// SELF-CONTAINED EXAM PAGE: FA1 (STRICT RULES)
// ==========================================================

session_start();
// Include the database connection file from the parent directory
require_once "../db_connect.php";

// --- Subject-Specific Configuration ---
$subject_code = 'FA2';
$questions_table_name = 'questions_fa2';
// ------------------------------------

// --- CRITICAL FIX: Robust Login and Role Check ---
// Redirect to login if user is not logged in, or if user_id/role is missing from session.
// This ensures that only properly authenticated users can proceed.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id']) || $_SESSION['user_id'] === 0 || !isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit; // Stop script execution
}

// Now we can safely assume user_id and role are set
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// --- Check for Database Connection Errors ---
// The $conn object should be created in db_connect.php.
if (!isset($conn) || $conn->connect_error) {
    // Stop the script and display a clear error message.
    die("Database Connection Failed: " . (isset($conn) ? $conn->connect_error : "The database connection object was not created. Check db_connect.php."));
}

// --- NEW: STRICT REFRESH-HANDLING LOGIC (APPLIES TO ALL USERS TAKING EXAM) ---
// This code runs every time the page loads.
// If an active exam session for this subject already exists in the session, it means the page was refreshed.
if (isset($_SESSION['active_exam_id'][$subject_code])) {
    $session_id_to_terminate = $_SESSION['active_exam_id'][$subject_code];

    // Immediately terminate the exam in the database.
    $sql_terminate = "UPDATE exam_sessions SET end_time = CURRENT_TIMESTAMP, completed = TRUE, score = 0, reason_for_completion = 'Terminated due to page refresh.' WHERE id = ?";
    $stmt_terminate = $conn->prepare($sql_terminate);
    $stmt_terminate->bind_param("i", $session_id_to_terminate);
    $stmt_terminate->execute();
    $stmt_terminate->close();

    // Clear the session variable for this exam.
    unset($_SESSION['active_exam_id'][$subject_code]);
    $conn->close(); // Close connection before redirecting

    // Display a termination message and redirect to the dashboard.
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Exam Terminated</title><script src="https://cdn.tailwindcss.com"></script><meta http-equiv="refresh" content="5;url=dashboard.php"></head><body class="bg-slate-100 flex items-center justify-center h-screen"><div class="text-center p-8 bg-white shadow-lg rounded-lg"><h1 class="text-2xl font-bold text-red-600">Exam Terminated</h1><p class="mt-2 text-slate-700">You have refreshed the page. As per the exam rules, your session has been ended.</p><p class="mt-4 text-sm text-slate-500">You will be redirected to the dashboard in 5 seconds...</p></div></body></html>';
    exit; // Stop the script from executing further.
}
// --- END: REFRESH-HANDLING LOGIC ---


// --- CREATE NEW EXAM SESSION (This code now only runs on the first visit) ---
// Get 50 random question IDs.
$question_ids = [];
$sql_questions = "SELECT id FROM `$questions_table_name` ORDER BY RAND() LIMIT 50";
$result = $conn->query($sql_questions);
if ($result && $result->num_rows >= 50) {
    while ($row = $result->fetch_assoc()) { $question_ids[] = $row['id']; }
} else {
    $conn->close();
    die("ERROR: Not enough questions for subject '$subject_code'. Please check that the table '$questions_table_name' exists and contains at least 50 questions.");
}
$questions_list_str = implode(',', $question_ids);

// Get the next mock number.
$mock_number = 1;
$sql_mock_count = "SELECT COUNT(*) as mock_count FROM exam_sessions WHERE user_id = ? AND subject = ?";
$stmt_count = $conn->prepare($sql_mock_count);
$stmt_count->bind_param("is", $user_id, $subject_code);
$stmt_count->execute();
$row = $stmt_count->get_result()->fetch_assoc();
$mock_number = $row['mock_count'] + 1;
$stmt_count->close();

// Insert the new session record.
$sql_insert = "INSERT INTO exam_sessions (user_id, subject, mock_number, questions_list) VALUES (?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);
$stmt_insert->bind_param("isis", $user_id, $subject_code, $mock_number, $questions_list_str);
$stmt_insert->execute();
$new_session_id = $stmt_insert->insert_id;
$stmt_insert->close();

// Store the new session ID in the PHP session to detect refreshes for ALL users taking the exam.
$_SESSION['active_exam_id'][$subject_code] = $new_session_id;


// --- Load all necessary data for the page ---
$sql_get_questions = "SELECT id, question_text, option_a, option_b, option_c, option_d FROM `$questions_table_name` WHERE id IN ($questions_list_str) ORDER BY FIELD(id, $questions_list_str)";
$questions_result = $conn->query($sql_get_questions);
$exam_questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $exam_questions[] = [
        'id' => $row['id'], 'text' => $row['question_text'],
        'options' => ['a' => $row['option_a'], 'b' => $row['option_b'], 'c' => $row['option_c'], 'd' => $row['option_d']]
    ];
}

$exam_page_data = [
    'sessionId' => $new_session_id,
    'subjectCode' => $subject_code,
    'mockNumber' => $mock_number,
    'questions' => $exam_questions
];

$conn->close(); // Close the database connection at the end of PHP logic
?>
<!-- HTML and JavaScript Section -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACCA Mock Exam - <?php echo htmlspecialchars($subject_code); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        html, body { height: 100%; overflow: hidden; }
        .noselect { user-select: none; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 noselect">

    <div id="exam-ui" class="flex flex-col h-screen">
        <!-- Header -->
        <header class="bg-white shadow-md p-4 flex justify-between items-center z-20">
            <div>
                <h1 id="exam-title" class="text-xl font-bold text-slate-800"></h1>
                <p class="text-sm text-slate-500">Candidate ID: <?php echo htmlspecialchars($user_id); ?></p>
            </div>
            <div class="flex items-center gap-4 bg-slate-800 text-white px-4 py-2 rounded-lg">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span id="timer" class="text-xl font-semibold tracking-wider">02:00:00</span>
            </div>
        </header>

        <!-- Main Content & Footer -->
        <main class="flex-grow flex flex-row overflow-hidden">
            <div class="flex-grow p-8 overflow-y-auto custom-scrollbar"><div id="question-container"></div></div>
            <aside class="w-72 bg-white p-6 flex flex-col border-l border-slate-200 overflow-y-auto custom-scrollbar">
                <h2 class="text-lg font-semibold mb-4">Question Navigator</h2>
                <div id="navigator-grid" class="grid grid-cols-5 gap-2 mb-6"></div>
                <div class="space-y-3 mt-auto">
                    <h3 class="text-md font-semibold">Legend</h3>
                    <div class="flex items-center text-sm"><span class="w-4 h-4 rounded-full bg-white border-2 border-slate-400 mr-2"></span>Not Answered</div>
                    <div class="flex items-center text-sm"><span class="w-4 h-4 rounded-full bg-slate-800 mr-2"></span>Answered</div>
                    <div class="flex items-center text-sm"><span class="w-4 h-4 rounded-full bg-blue-500 mr-2"></span>Flagged</div>
                    <div class="flex items-center text-sm"><span class="w-4 h-4 ring-2 ring-offset-1 ring-slate-800 rounded-full mr-2"></span>Current</div>
                </div>
            </aside>
        </main>
        <footer class="bg-white p-4 flex justify-between items-center border-t z-10">
            <button id="flag-btn" class="px-6 py-2 border border-blue-500 text-blue-500 font-semibold rounded-lg hover:bg-blue-50">Flag for Review</button>
            <div class="flex gap-4">
                <button id="prev-btn" class="px-6 py-2 border border-slate-300 font-semibold rounded-lg hover:bg-slate-100 disabled:opacity-50">Previous</button>
                <button id="next-btn" class="px-6 py-2 bg-slate-800 text-white font-semibold rounded-lg hover:bg-slate-700">Next</button>
            </div>
            <button id="end-exam-btn" class="px-6 py-2 bg-[rgb(197,26,29)] text-white font-semibold rounded-lg hover:bg-[rgb(177,23,26)]">End Exam</button>
        </footer>
    </div>
    
    <!-- Modals -->
    <div id="end-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden"><div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-sm"><h2 class="text-xl font-bold mb-4">Are you sure?</h2><p class="text-slate-600 mb-6">Your answers will be submitted.</p><div class="flex justify-center gap-4"><button id="cancel-end-btn" class="px-6 py-2 border rounded-lg hover:bg-slate-100">Cancel</button><button id="confirm-end-btn" class="px-6 py-2 bg-[rgb(197,26,29)] text-white font-semibold rounded-lg">End Exam</button></div></div></div>
    <div id="result-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden"><div class="bg-white p-10 rounded-lg shadow-xl text-center max-w-md"><div id="result-icon"></div><h2 id="result-title" class="text-2xl font-bold mb-4"></h2><p id="result-text" class="text-slate-600 mb-6"></p><p id="redirect-message" class="text-sm text-slate-500"></p></div></div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const examData = <?php echo json_encode($exam_page_data); ?>;
        
        let questions = examData.questions.map(q => ({ ...q, userAnswer: null, isFlagged: false }));
        let currentQuestionIndex = 0;
        let examEnded = false;
        let examTimerInterval;

        const dom = { 
            title: document.getElementById('exam-title'), questionContainer: document.getElementById('question-container'), navigatorGrid: document.getElementById('navigator-grid'), prevBtn: document.getElementById('prev-btn'), nextBtn: document.getElementById('next-btn'), flagBtn: document.getElementById('flag-btn'), endExamBtn: document.getElementById('end-exam-btn'), endModal: document.getElementById('end-modal'), cancelEndBtn: document.getElementById('cancel-end-btn'), confirmEndBtn: document.getElementById('confirm-end-btn'), resultModal: document.getElementById('result-modal'), resultTitle: document.getElementById('result-title'), resultText: document.getElementById('result-text'), resultIcon: document.getElementById('result-icon'), timer: document.getElementById('timer'), redirectMessage: document.getElementById('redirect-message')
        };
        
        function renderQuestion(index) {
            const q = questions[index];
            dom.questionContainer.innerHTML = `<h2 class="text-lg font-semibold text-slate-600 mb-2">Question ${index + 1} of ${questions.length}</h2><p class="text-slate-800 text-lg mb-8">${q.text}</p><div class="space-y-4">${Object.entries(q.options).map(([key, value]) => `<label class="flex items-start p-4 border rounded-lg cursor-pointer transition-all hover:border-[rgb(197,26,29)] has-[:checked]:bg-red-50 has-[:checked]:border-[rgb(197,26,29)] has-[:checked]:ring-1"><input type="radio" name="q-${q.id}" value="${key}" class="mt-1" ${q.userAnswer === key ? 'checked' : ''}><span class="ml-4">${value}</span></label>`).join('')}</div>`;
            updateUiElements();
            dom.questionContainer.querySelectorAll(`input[type="radio"]`).forEach(radio => radio.addEventListener('change', handleAnswerSelection));
        }

        function handleAnswerSelection(e) {
            questions[currentQuestionIndex].userAnswer = e.target.value;
            updateNavigatorItem(currentQuestionIndex);
        }
        
        function renderNavigator() {
            dom.navigatorGrid.innerHTML = questions.map((q, i) => `<button data-index="${i}" class="navigator-item h-10 w-10 flex items-center justify-center rounded-md font-semibold border-2"></button>`).join('');
            dom.navigatorGrid.querySelectorAll('.navigator-item').forEach(item => item.addEventListener('click', e => navigateTo(parseInt(e.target.dataset.index))));
            updateAllNavigatorItems();
        }

        const updateUiElements = () => { updateAllNavigatorItems(); updatePrevNextButtons(); updateFlagButton(); };
        const updateAllNavigatorItems = () => questions.forEach((_, i) => updateNavigatorItem(i));

        function updateNavigatorItem(index) {
             const item = dom.navigatorGrid.querySelector(`[data-index="${index}"]`);
             if (!item) return;
             const q = questions[index];
             item.textContent = index + 1;
             item.className = 'navigator-item h-10 w-10 flex items-center justify-center rounded-md font-semibold border-2'; 
             if(q.isFlagged) item.classList.add('bg-blue-500', 'text-white', 'border-blue-500');
             else if (q.userAnswer) item.classList.add('bg-slate-800', 'text-white', 'border-slate-800');
             else item.classList.add('border-slate-400');
             if (index === currentQuestionIndex) item.classList.add('ring-2', 'ring-offset-1', 'ring-slate-800');
        }
        
        const updatePrevNextButtons = () => { dom.prevBtn.disabled = currentQuestionIndex === 0; dom.nextBtn.disabled = currentQuestionIndex === questions.length - 1; };
        function updateFlagButton() {
            dom.flagBtn.textContent = questions[currentQuestionIndex].isFlagged ? 'Unflag' : 'Flag for Review';
            dom.flagBtn.classList.toggle('bg-blue-500', questions[currentQuestionIndex].isFlagged);
            dom.flagBtn.classList.toggle('text-white', questions[currentQuestionIndex].isFlagged);
        }
        
        function startTimer() {
            let duration = 2 * 60 * 60; 
            examTimerInterval = setInterval(() => {
                if (examEnded) return clearInterval(examTimerInterval);
                duration--;
                if (duration < 0) return endExam('Time is up!');
                dom.timer.textContent = new Date(duration * 1000).toISOString().substr(11, 8);
            }, 1000);
        }

        async function endExam(reason) {
            if (examEnded) return;
            examEnded = true;
            clearInterval(examTimerInterval);
            const answersToSubmit = questions.reduce((acc, q) => { if (q.userAnswer) acc[q.id] = q.userAnswer; return acc; }, {});
            try {
                const data = JSON.stringify({ session_id: examData.sessionId, answers: answersToSubmit, reason: reason });
                // Use sendBeacon for security violations (tab change, window close) for all users
                if ((reason.includes('closed') || reason.includes('tab')) && navigator.sendBeacon) {
                    // We don't wait for a response here, the page will be terminated by the browser.
                    navigator.sendBeacon('end_exam.php', data);
                } else {
                    // For normal endings (manual or timer), we use fetch and wait for the score.
                    const response = await fetch('end_exam.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: data });
                    const result = await response.json();
                    if(result.success) {
                        showResultModal('Exam Finished!', `Your score: <strong class="text-2xl">${result.score}/${result.total}</strong>`, 'success');
                    } else {
                         showResultModal('Error', `Submission failed: ${result.message}`, 'error');
                    }
                }
            } catch (error) {
                 showResultModal('Connection Error', 'Could not submit your results.', 'error');
            }
        }
        
        function showResultModal(title, text, type) {
            // Hide the main exam UI before showing the modal
            document.getElementById('exam-ui').style.display = 'none';
            
            dom.resultTitle.textContent = title;
            dom.resultText.innerHTML = text;
            dom.resultIcon.innerHTML = type === 'success' ? `<svg class="w-16 h-16 mx-auto text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>` : `<svg class="w-16 h-16 mx-auto text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`;
            dom.resultModal.classList.remove('hidden');
            
            // Auto-redirect after 5 seconds
            let countdown = 5;
            dom.redirectMessage.textContent = `Redirecting to dashboard in ${countdown}...`;
            const redirectInterval = setInterval(() => {
                countdown--;
                dom.redirectMessage.textContent = `Redirecting to dashboard in ${countdown}...`;
                if (countdown <= 0) {
                    clearInterval(redirectInterval);
                    window.location.href = 'dashboard.php';
                }
            }, 1000);
        }

        const navigateTo = (index) => { currentQuestionIndex = index; renderQuestion(index); };
        
        // --- Event Listeners ---
        dom.prevBtn.addEventListener('click', () => { if (currentQuestionIndex > 0) navigateTo(currentQuestionIndex - 1); });
        dom.nextBtn.addEventListener('click', () => { if (currentQuestionIndex < questions.length - 1) navigateTo(currentQuestionIndex + 1); });
        dom.flagBtn.addEventListener('click', () => { questions[currentQuestionIndex].isFlagged = !questions[currentQuestionIndex].isFlagged; updateFlagButton(); updateNavigatorItem(currentQuestionIndex); });
        dom.endExamBtn.addEventListener('click', () => dom.endModal.classList.remove('hidden'));
        dom.cancelEndBtn.addEventListener('click', () => dom.endModal.classList.add('hidden'));
        dom.confirmEndBtn.addEventListener('click', () => { dom.endModal.classList.add('hidden'); endExam('User ended exam manually.'); });
        
        // --- Security Listeners (Apply to all users taking exam) ---
        document.addEventListener('visibilitychange', () => {
             if (document.hidden && !examEnded) {
                // When tab changes, immediately show the termination message and send data.
                showResultModal('Exam Terminated', `You have violated the exam rules by leaving the exam window. Your progress has been submitted.`, 'error');
                endExam('Switched to another tab.');
            }
        });
        window.addEventListener('beforeunload', () => {
            if (!examEnded) {
                // This will attempt to send data as the window closes.
                endExam('Window or tab was closed.');
            }
        });
        
        // --- Initialize Page ---
        dom.title.textContent = `ACCA Mock Exam - ${examData.subjectCode} #${examData.mockNumber}`;
        renderNavigator();
        renderQuestion(currentQuestionIndex);
        startTimer();
    });
    </script>
</body>
</html>
