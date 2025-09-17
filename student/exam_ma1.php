<?php

session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration file
require_once 'db_config.php';

// --- Subject-Specific Configuration ---
$subject_code = 'FA1';
$questions_table_name = 'questions_ma1';
// ------------------------------------

// --- CRITICAL: Robust Login and Role Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id']) || $_SESSION['user_id'] === 0 || !isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// --- Check for Database Connection Errors ---
if (!isset($conn) || $conn->connect_error) {
    die("Database Connection Failed: " . (isset($conn) ? $conn->connect_error : "The database connection object was not created. Check db_config.php."));
}

// --- REVISED AND CORRECTED: STRICT REFRESH-HANDLING LOGIC ---
if (isset($_SESSION['active_exam_id'][$subject_code])) {
    $session_id_from_session = $_SESSION['active_exam_id'][$subject_code];

    $check_sql = "SELECT completed FROM exam_sessions WHERE id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $session_id_from_session);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $db_session_status = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($db_session_status && $db_session_status['completed'] == 0) {
        $sql_terminate = "UPDATE exam_sessions SET end_time = CURRENT_TIMESTAMP, completed = TRUE, score = 0, reason_for_completion = 'Terminated due to page refresh.' WHERE id = ?";
        $stmt_terminate = $conn->prepare($sql_terminate);
        $stmt_terminate->bind_param("i", $session_id_from_session);
        $stmt_terminate->execute();
        $stmt_terminate->close();

        unset($_SESSION['active_exam_id'][$subject_code]);
        $conn->close();

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Exam Terminated</title><script src="https://cdn.tailwindcss.com"></script><meta http-equiv="refresh" content="5;url=dashboard.php"></head><body class="bg-slate-100 flex items-center justify-center h-screen"><div class="text-center p-8 bg-white shadow-lg rounded-lg max-w-lg mx-auto"><h1 class="text-2xl font-bold text-red-600">Exam Terminated</h1><p class="mt-2 text-slate-700">You refreshed the page during an active exam. As per exam rules, your session has been ended and your score has been recorded as 0.</p><p class="mt-4 text-sm text-slate-500">You will be redirected to your dashboard in 5 seconds...</p></div></body></html>';
        exit;
    } else {
        unset($_SESSION['active_exam_id'][$subject_code]);
    }
}
// --- END: REFRESH-HANDLING LOGIC ---


// --- CREATE NEW EXAM SESSION ---
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

$mock_number = 1;
$sql_mock_count = "SELECT COUNT(*) as mock_count FROM exam_sessions WHERE user_id = ? AND subject = ?";
$stmt_count = $conn->prepare($sql_mock_count);
$stmt_count->bind_param("is", $user_id, $subject_code);
$stmt_count->execute();
$row = $stmt_count->get_result()->fetch_assoc();
$mock_number = $row['mock_count'] + 1;
$stmt_count->close();

$sql_insert = "INSERT INTO exam_sessions (user_id, subject, mock_number, questions_list) VALUES (?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);
$stmt_insert->bind_param("isis", $user_id, $subject_code, $mock_number, $questions_list_str);
$stmt_insert->execute();
$new_session_id = $stmt_insert->insert_id;
$stmt_insert->close();

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACCA Mock Exam - <?php echo htmlspecialchars($subject_code); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'theme-red': '#c51a1d',
                        'theme-dark-red': '#a81013',
                    },
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        html, body { height: 100%; overflow: hidden; }
        .noselect { user-select: none; }
        .navigator-item { border: 2px solid transparent; } /* Prevents jumping by having a transparent border */
    </style>
</head>
<body class="bg-slate-100 text-slate-800 noselect">

    <div id="start-screen-overlay" class="fixed inset-0 bg-slate-100 z-50 flex items-center justify-center p-4">
        <div class="text-center p-8 sm:p-12 bg-white rounded-2xl shadow-xl max-w-lg w-full">
            <i class="fas fa-file-alt text-5xl text-theme-red mb-4"></i>
            <h1 class="text-3xl font-bold text-slate-800">ACCA Mock Exam: <?php echo htmlspecialchars($exam_page_data['subjectCode']); ?></h1>
            <p class="text-slate-600 mt-2">Mock Exam #<?php echo htmlspecialchars($exam_page_data['mockNumber']); ?></p>
            <div class="mt-6 text-left bg-slate-50 p-6 rounded-lg border border-slate-200 space-y-3">
                <p class="flex items-center text-slate-700"><i class="fas fa-list-ol w-5 mr-3 text-theme-red"></i><strong>Questions:</strong><span class="ml-auto font-semibold">50</span></p>
                <p class="flex items-center text-slate-700"><i class="fas fa-clock w-5 mr-3 text-theme-red"></i><strong>Time Limit:</strong><span class="ml-auto font-semibold">2 Hours</span></p>
                <p class="flex items-center text-slate-700"><i class="fas fa-exclamation-triangle w-5 mr-3 text-theme-red"></i><strong>Rule:</strong><span class="ml-auto font-semibold">No Page Refreshes</span></p>
            </div>
            <button id="start-exam-fullscreen-btn" class="mt-8 w-full bg-theme-red hover:bg-theme-dark-red text-white font-bold py-4 px-6 rounded-lg text-lg transition-transform transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-red-300">
                <i class="fas fa-play-circle mr-2"></i> Start Exam
            </button>
        </div>
    </div>

    <div id="exam-ui" class="flex flex-col h-screen" style="display: none;">
        <header class="bg-white shadow-md p-4 flex justify-between items-center z-20 flex-shrink-0">
            <div>
                <h1 id="exam-title" class="text-xl font-bold text-slate-800"></h1>
                <p class="text-sm text-slate-500">Candidate ID: <?php echo htmlspecialchars($user_id); ?></p>
            </div>
            <div class="flex items-center gap-4 bg-slate-800 text-white px-4 py-2 rounded-lg">
                <i class="far fa-clock text-xl"></i>
                <span id="timer" class="text-xl font-semibold tracking-wider">02:00:00</span>
            </div>
        </header>

        <main class="flex-grow flex flex-col md:flex-row overflow-hidden">
            <div class="flex-grow p-6 md:p-10 overflow-y-auto custom-scrollbar bg-slate-50"><div id="question-container"></div></div>
            <aside class="w-full md:w-80 bg-theme-red text-white p-6 flex flex-col flex-shrink-0 overflow-y-auto custom-scrollbar">
                <h2 class="text-lg font-semibold mb-4 text-slate-200">Question Navigator</h2>
                <div id="navigator-grid" class="grid grid-cols-5 gap-2 mb-6"></div>
                <div class="space-y-3 mt-auto text-sm text-slate-200">
                    <h3 class="text-md font-semibold text-white">Legend</h3>
                    <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-slate-600 mr-3"></span>Not Answered</div>
                    <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-white mr-3"></span>Answered</div>
                    <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-yellow-400 mr-3"></span>Flagged</div>
                    <div class="flex items-center"><span class="w-4 h-4 rounded-full border-2 border-white mr-3"></span>Current Question</div>
                </div>
            </aside>
        </main>
        <footer class="bg-white p-4 flex justify-between items-center border-t z-10 flex-shrink-0">
            <button id="flag-btn" class="px-6 py-2 border border-yellow-500 text-yellow-500 font-semibold rounded-lg hover:bg-yellow-50 transition"><i class="far fa-flag mr-2"></i> Flag for Review</button>
            <div class="flex gap-4">
                <button id="prev-btn" class="px-6 py-2 border border-slate-300 font-semibold rounded-lg hover:bg-slate-100 disabled:opacity-50 transition"><i class="fas fa-arrow-left mr-2"></i> Previous</button>
                <button id="next-btn" class="px-6 py-2 bg-slate-800 text-white font-semibold rounded-lg hover:bg-slate-700 transition">Next <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
            <button id="end-exam-btn" class="px-6 py-2 bg-theme-red text-white font-semibold rounded-lg hover:bg-theme-dark-red transition">End Exam <i class="fas fa-check-circle ml-2"></i></button>
        </footer>
    </div>
    
    <div id="end-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden"><div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-sm"><h2 class="text-xl font-bold mb-4">Are you sure?</h2><p class="text-slate-600 mb-6">Your answers will be submitted and the exam will end.</p><div class="flex justify-center gap-4"><button id="cancel-end-btn" class="px-6 py-2 border rounded-lg hover:bg-slate-100">Cancel</button><button id="confirm-end-btn" class="px-6 py-2 bg-theme-red text-white font-semibold rounded-lg">End Exam</button></div></div></div>
    <div id="result-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden"><div class="bg-white p-10 rounded-lg shadow-xl text-center max-w-md"><div id="result-icon"></div><h2 id="result-title" class="text-2xl font-bold mb-4"></h2><div id="result-text" class="text-slate-600 mb-6"></div><p id="redirect-message" class="text-sm text-slate-500"></p></div></div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const examData = <?php echo json_encode($exam_page_data); ?>;
        
        // --- Message Banks ---
        const passMessages = [ "Excellent work! You've passed. Keep up the momentum!", "Congratulations on passing! Your hard work is paying off.", "Great job! You have successfully passed this exam." ];
        const failMessages = [ "Don't be discouraged. Every attempt is a learning opportunity.", "This was a tough one. Review your answers and you'll get it next time.", "Keep your head up and prepare for the next attempt." ];

        let questions = examData.questions.map(q => ({ ...q, userAnswer: null, isFlagged: false }));
        let currentQuestionIndex = 0;
        let examEnded = false;
        let examTimerInterval;

        const dom = { 
            startScreen: document.getElementById('start-screen-overlay'),
            startBtn: document.getElementById('start-exam-fullscreen-btn'),
            examUI: document.getElementById('exam-ui'),
            title: document.getElementById('exam-title'), questionContainer: document.getElementById('question-container'), navigatorGrid: document.getElementById('navigator-grid'), prevBtn: document.getElementById('prev-btn'), nextBtn: document.getElementById('next-btn'), flagBtn: document.getElementById('flag-btn'), endExamBtn: document.getElementById('end-exam-btn'), endModal: document.getElementById('end-modal'), cancelEndBtn: document.getElementById('cancel-end-btn'), confirmEndBtn: document.getElementById('confirm-end-btn'), resultModal: document.getElementById('result-modal'), resultTitle: document.getElementById('result-title'), resultText: document.getElementById('result-text'), resultIcon: document.getElementById('result-icon'), timer: document.getElementById('timer'), redirectMessage: document.getElementById('redirect-message')
        };
        
        function renderQuestion(index) {
            const q = questions[index];
            dom.questionContainer.innerHTML = `<h2 class="text-lg font-semibold text-slate-600 mb-2">Question ${index + 1} of ${questions.length}</h2><p class="text-slate-800 text-xl mb-8 leading-relaxed">${q.text}</p><div class="space-y-4">${Object.entries(q.options).map(([key, value]) => `<label class="flex items-start p-4 border rounded-lg cursor-pointer transition-all bg-white hover:border-theme-red has-[:checked]:bg-red-50 has-[:checked]:border-theme-red has-[:checked]:ring-2 has-[:checked]:ring-red-200"><input type="radio" name="q-${q.id}" value="${key}" class="mt-1 h-5 w-5 text-theme-red focus:ring-theme-red" ${q.userAnswer === key ? 'checked' : ''}><span class="ml-4 text-base">${value}</span></label>`).join('')}</div>`;
            updateUiElements();
            dom.questionContainer.querySelectorAll(`input[type="radio"]`).forEach(radio => radio.addEventListener('change', handleAnswerSelection));
        }

        function handleAnswerSelection(e) {
            questions[currentQuestionIndex].userAnswer = e.target.value;
            updateNavigatorItem(currentQuestionIndex);
        }
        
        function renderNavigator() {
            dom.navigatorGrid.innerHTML = questions.map((q, i) => `<button data-index="${i}" class="navigator-item h-10 w-10 flex items-center justify-center rounded-md font-semibold"></button>`).join('');
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
             item.className = 'navigator-item h-10 w-10 flex items-center justify-center rounded-md font-semibold border-2 border-transparent'; 
             if(q.isFlagged) item.classList.add('bg-yellow-400', 'text-slate-800');
             else if (q.userAnswer) item.classList.add('bg-white', 'text-slate-800');
             else item.classList.add('bg-slate-600', 'text-slate-200');
             if (index === currentQuestionIndex) item.classList.add('border-white');
        }
        
        const updatePrevNextButtons = () => { dom.prevBtn.disabled = currentQuestionIndex === 0; dom.nextBtn.disabled = currentQuestionIndex === questions.length - 1; };
        function updateFlagButton() {
            dom.flagBtn.innerHTML = questions[currentQuestionIndex].isFlagged ? '<i class="fas fa-flag mr-2"></i> Unflag' : '<i class="far fa-flag mr-2"></i> Flag for Review';
            dom.flagBtn.classList.toggle('bg-yellow-400', questions[currentQuestionIndex].isFlagged);
            dom.flagBtn.classList.toggle('text-slate-800', questions[currentQuestionIndex].isFlagged);
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
                if ((reason.includes('closed') || reason.includes('tab')) && navigator.sendBeacon) {
                    navigator.sendBeacon('end_exam.php', data);
                } else {
                    const response = await fetch('end_exam.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: data });
                    const result = await response.json();
                    if(result.success) {
                        const isPass = (result.score / result.total) >= 0.5;
                        const title = isPass ? 'Congratulations!' : 'Keep Pushing Forward!';
                        const message = isPass ? passMessages[Math.floor(Math.random() * passMessages.length)] : failMessages[Math.floor(Math.random() * failMessages.length)];
                        const resultHTML = `<p>Your score is: <strong class="text-2xl">${result.score}/${result.total}</strong></p><p class="mt-4 text-slate-600 italic">"${message}"</p>`;
                        showResultModal(title, resultHTML, isPass ? 'success' : 'fail');
                    } else {
                         showResultModal('Error', `Submission failed: ${result.message}`, 'error');
                    }
                }
            } catch (error) {
                 showResultModal('Connection Error', 'Could not submit your results.', 'error');
            }
        }
        
        function showResultModal(title, textHTML, type) {
            dom.examUI.style.display = 'none';
            dom.resultTitle.textContent = title;
            dom.resultText.innerHTML = textHTML;
            let iconHTML = '';
            if (type === 'success') {
                iconHTML = `<svg class="w-16 h-16 mx-auto text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
            } else if (type === 'fail') {
                iconHTML = `<svg class="w-16 h-16 mx-auto text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`;
            } else { // error
                iconHTML = `<svg class="w-16 h-16 mx-auto text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`;
            }
            dom.resultIcon.innerHTML = iconHTML;
            dom.resultModal.classList.remove('hidden');
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
        
        function openFullscreen() {
            const elem = document.documentElement;
            if (elem.requestFullscreen) { elem.requestFullscreen().catch(err => console.error(err)); } 
            else if (elem.mozRequestFullScreen) { elem.mozRequestFullScreen(); } 
            else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); } 
            else if (elem.msRequestFullscreen) { elem.msRequestFullscreen(); }
        }

        // --- Event Listeners ---
        dom.startBtn.addEventListener('click', () => {
            openFullscreen();
            dom.startScreen.style.display = 'none';
            dom.examUI.style.display = 'flex';
            startTimer();
        });
        dom.prevBtn.addEventListener('click', () => { if (currentQuestionIndex > 0) navigateTo(currentQuestionIndex - 1); });
        dom.nextBtn.addEventListener('click', () => { if (currentQuestionIndex < questions.length - 1) navigateTo(currentQuestionIndex + 1); });
        dom.flagBtn.addEventListener('click', () => { questions[currentQuestionIndex].isFlagged = !questions[currentQuestionIndex].isFlagged; updateFlagButton(); updateNavigatorItem(currentQuestionIndex); });
        dom.endExamBtn.addEventListener('click', () => dom.endModal.classList.remove('hidden'));
        dom.cancelEndBtn.addEventListener('click', () => dom.endModal.classList.add('hidden'));
        dom.confirmEndBtn.addEventListener('click', () => { dom.endModal.classList.add('hidden'); endExam('User ended exam manually.'); });
        
        // --- Security Listeners ---
        document.addEventListener('visibilitychange', () => {
             if (document.hidden && !examEnded && dom.examUI.style.display === 'flex') {
                 showResultModal('Exam Terminated', `You have violated the exam rules by leaving the exam window. Your progress has been submitted.`, 'error');
                 endExam('Switched to another tab.');
            }
        });
        window.addEventListener('beforeunload', () => {
            if (!examEnded && dom.examUI.style.display === 'flex') {
                endExam('Window or tab was closed.');
            }
        });
        
        // --- Initialize Page ---
        dom.title.textContent = `ACCA Mock Exam - ${examData.subjectCode} #${examData.mockNumber}`;
        renderNavigator();
        renderQuestion(currentQuestionIndex);
    });
    </script>
</body>
</html>