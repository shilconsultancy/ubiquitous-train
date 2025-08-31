<?php
// --- DEVELOPMENT/DEBUGGING: Display all PHP errors ---
// This will help us see the actual error instead of a generic HTTP 500 page.
// IMPORTANT: REMOVE THESE TWO LINES IN A LIVE PRODUCTION ENVIRONMENT.
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Always start the session first

// Include the database connection file from the parent directory
if (!file_exists('db_config.php')) { // Assuming db_config.php is in the same directory
    die("FATAL ERROR: db_config.php not found. Please ensure the database connection file exists in the correct directory.");
}
require_once 'db_config.php';

// --- Check for Database Connection Errors ---
// The $conn object should be created in db_config.php.
// This checks if the variable was set AND if the connection was successful.
if (!isset($conn) || $conn->connect_error) {
    // Stop the script and display a clear error message.
    die("Database Connection Failed: " . (isset($conn) ? $conn->connect_error : "The database connection object was not created. Check db_config.php."));
}

// --- Adjusted access control for student dashboard ---
// Allow 'student', 'admin', and 'super_admin' roles to access this page IF they are logged in.
// This allows admins/superadmins to view the student dashboard without redirection loops.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../index.php');
    exit; // Not logged in at all, redirect to login page
}

// --- DATA FETCHING ---
$current_user_id = $_SESSION['user_id'] ?? 0;

// --- STEP 1: Get user details ---
// Initialize variables with default values in case data isn't found
$username = 'Guest';
$acca_id = 'N/A';

// Prepare the SQL statement to fetch username and acca_id for the logged-in user
$sql_user_details = "SELECT username, acca_id FROM users WHERE id = ?";
if ($stmt_details = $conn->prepare($sql_user_details)) {
    $stmt_details->bind_param("i", $current_user_id);
    if ($stmt_details->execute()) {
        $result_details = $stmt_details->get_result();
        if ($user_data = $result_details->fetch_assoc()) {
            $username = htmlspecialchars($user_data['username']);
            // Use null coalescing operator to prevent error if acca_id is null
            $acca_id = htmlspecialchars($user_data['acca_id'] ?? 'N/A');
        }
    } else {
        error_log("Error executing user details query: " . $stmt_details->error);
    }
    $stmt_details->close();
} else {
    error_log("Error preparing user details query: " . $conn->error);
}


// --- STEP 2: Get recent completed exams for the table ---
// This data is used for the table, so we need all recent exams.
$user_exams = [];
$sql_user = "SELECT id, subject, score, start_time, end_time FROM exam_sessions WHERE user_id = ? AND completed = 1 ORDER BY start_time DESC LIMIT 5";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $current_user_id);
    if ($stmt_user->execute()) {
        $result = $stmt_user->get_result();
        $user_exams = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Error executing user exams query: " . $stmt_user->error);
    }
    $stmt_user->close();
} else {
    error_log("Error preparing user exams query: " . $conn->error);
}

// --- STEP 3: Get class averages ---
$class_averages = [];
// Collect all subjects from user's exams (even if duplicated) to query class averages for them
$all_user_exam_subjects = array_column($user_exams, 'subject');
$chart_subjects_unique_for_avg_query = array_unique($all_user_exam_subjects); // Use unique subjects for this query

if (!empty($chart_subjects_unique_for_avg_query)) {
    $placeholders = implode(',', array_fill(0, count($chart_subjects_unique_for_avg_query), '?'));
    $types = str_repeat('s', count($chart_subjects_unique_for_avg_query));
    $sql_avg = "SELECT subject, ROUND(AVG(score)) as average_score FROM exam_sessions WHERE subject IN ($placeholders) AND completed = 1 GROUP BY subject";
    if ($stmt_avg = $conn->prepare($sql_avg)) {
        $stmt_avg->bind_param($types, ...$chart_subjects_unique_for_avg_query);
        if ($stmt_avg->execute()) {
            $result_avg = $stmt_avg->get_result();
            while ($row = $result_avg->fetch_assoc()) {
                $class_averages[$row['subject']] = $row['average_score'];
            }
        } else {
            error_log("Error executing class averages query: " . $stmt_avg->error);
        }
        $stmt_avg->close();
    } else {
        error_log("Error preparing class averages query: " . $conn->error);
    }
}

// --- STEP 4: Prepare chart data (FIXED FOR UNIQUE SUBJECTS AND HIGHEST SCORES) ---
$user_subject_highest_scores = [];

// Aggregate user's highest scores by subject
foreach ($user_exams as $exam) {
    $subject = $exam['subject'];
    if (!isset($user_subject_highest_scores[$subject]) || $exam['score'] > $user_subject_highest_scores[$subject]) {
        $user_subject_highest_scores[$subject] = $exam['score'];
    }
}

$chart_labels = [];
$chart_user_scores = []; // This will now hold highest scores
$chart_class_average_scores = [];

// Sort subjects alphabetically for consistent chart display
ksort($user_subject_highest_scores);

// Populate chart data with unique subjects and their highest scores
foreach ($user_subject_highest_scores as $subject => $highest_score) {
    $chart_labels[] = $subject;
    $chart_user_scores[] = $highest_score;
    // Fetch the class average for this specific subject, defaulting to 0 if not found
    $chart_class_average_scores[] = $class_averages[$subject] ?? 0;
}

// If no exams, ensure chart data is empty arrays to prevent JavaScript errors
if (empty($chart_labels)) {
    $chart_labels = [];
    $chart_user_scores = [];
    $chart_class_average_scores = [];
}

// --- STEP 5: Get recent announcements from the notices table ---
$announcements = [];
// FIX: Fetch 'content' as well for the notice board display
$sql_announcements = "SELECT title, content FROM notices WHERE status = 'Published' ORDER BY published_date DESC LIMIT 10";
$result_announcements = $conn->query($sql_announcements);
if ($result_announcements) {
    $announcements = $result_announcements->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Error fetching announcements: " . $conn->error);
}

// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 'theme-red': '#c51a1d', 'theme-dark-red': '#a81013', 'theme-black': '#1a1a1a', 'light-gray': '#f5f7fa' },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .solid-header { background: linear-gradient(to right, #c51a1d, #a81013); }
        .dashboard-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .dashboard-card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -10px rgba(0,0,0,0.15); }
        .tip-card { border-left: 4px solid #c51a1d; }
        .chart-legend { display: flex; justify-content: center; gap: 20px; padding-top: 10px; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; font-size: 0.8rem; }
        .legend-color { width: 12px; height: 12px; margin-right: 5px; border-radius: 2px; }

        /* FIX: Styles for the new static notice board section */
        .notice-board-section {
            padding: 2rem;
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-top: 2rem; /* Spacing from the content above */
        }
        .notice-item {
            border-bottom: 1px solid #e2e8f0; /* Light gray border between notices */
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .notice-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .notice-title {
            font-size: 1.25rem; /* text-xl */
            font-weight: 700; /* font-bold */
            color: #c51a1d; /* theme-red */
            margin-bottom: 0.5rem;
        }
        .notice-content {
            font-size: 0.95rem; /* text-base slightly smaller */
            color: #4a5568; /* gray-700 */
            line-height: 1.6;
        }

        /* --- NEW MOBILE MENU STYLES --- */
        .mobile-menu {
            transition: transform 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-light-gray text-gray-800">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="solid-header text-white shadow-lg sticky top-0 z-50">
            <div class="container mx-auto px-4 sm:px-6 py-4">
                <div class="flex justify-between items-center">
                    <!-- Logo / Title -->
                    <div class="text-center md:text-left">
                        <h1 class="text-2xl font-bold tracking-tight">PSB Learning Hub</h1>
                    </div>

                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-4">
                        <div class="text-right">
                            <div class="font-semibold">Welcome back, <span class="font-bold"><?php echo $username; ?>!</span></div>
                            <p class="text-sm text-gray-200 mt-1">ACCA ID: <span class="font-medium"><?php echo $acca_id; ?></span></p>
                        </div>
                        <a href="../logout.php" class="inline-block bg-white text-theme-red font-semibold py-2 px-5 rounded-full shadow-md hover:bg-gray-100 hover:shadow-lg transition-all duration-300 ease-in-out text-sm">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>

                    <!-- Mobile Menu Button -->
                    <div class="md:hidden">
                        <button id="mobile-menu-button" class="text-white focus:outline-none">
                            <i class="fas fa-bars text-2xl"></i>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Menu (Hidden by default) -->
        <div id="mobile-menu" class="mobile-menu fixed inset-0 bg-black bg-opacity-50 z-40 transform -translate-x-full">
            <div class="w-64 h-full bg-white shadow-xl p-6">
                <div class="flex justify-between items-center mb-8">
                    <h2 class="text-lg font-bold text-theme-red">Menu</h2>
                    <button id="mobile-menu-close-button" class="text-slate-600 focus:outline-none">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="text-left p-4 bg-light-gray rounded-lg">
                        <div class="font-semibold text-slate-800 flex items-center">
                            <i class="fas fa-user-circle mr-2 text-slate-500"></i> <?php echo $username; ?>
                        </div>
                        <p class="text-sm text-slate-500 mt-1">ACCA ID: <span class="font-medium"><?php echo $acca_id; ?></span></p>
                    </div>
                    <a href="../logout.php" class="w-full text-left inline-block bg-theme-red text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:bg-theme-dark-red transition-all duration-300 ease-in-out">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>


        <main class="flex-grow container mx-auto px-4 py-6">
            <!-- Main content grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Recent Mock Exam Results -->
                <div class="lg:col-span-2">
                    <div class="dashboard-card bg-white rounded-xl shadow-md p-6 mb-6">
                        <div class="flex justify-between items-center mb-6 flex-wrap"> <h2 class="text-xl font-bold text-theme-black">Recent Mock Exam Results</h2>
                        </div>
                        <div class="h-80"> <canvas id="examChart"></canvas> </div>
                        <div class="chart-legend">
                            <div class="legend-item"><div class="legend-color bg-theme-red"></div> <span class="text-theme-black">Your Score</span></div>
                            <div class="legend-item"><div class="legend-color bg-gray-400"></div> <span class="text-theme-black">Class Average</span></div>
                        </div>
                        <div class="overflow-x-auto mt-8">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="p-3 text-left text-theme-black font-medium">Paper</th>
                                        <th class="p-3 text-left text-theme-black font-medium">Score</th>
                                        <th class="p-3 text-left text-theme-black font-medium">Class Avg.</th>
                                        <th class="p-3 text-left text-theme-black font-medium">Date</th>
                                        <th class="p-3 text-left text-theme-black font-medium">Time Taken</th>
                                        <th class="p-3 text-left text-theme-black font-medium">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($user_exams)): ?>
                                        <?php foreach ($user_exams as $exam): ?>
                                            <?php
                                                $time_taken_str = 'N/A';
                                                if (!is_null($exam['start_time']) && !is_null($exam['end_time'])) {
                                                    $interval = (new DateTime($exam['start_time']))->diff(new DateTime($exam['end_time']));
                                                    $time_taken_str = $interval->format('%h h %i m');
                                                }
                                                $score_class = $exam['score'] >= 65 ? 'bg-green-100 text-green-800' : ($exam['score'] >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                            ?>
                                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                <td class="p-3 font-medium text-theme-black"><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                <td class="p-3"><span class="px-2 py-1 <?php echo $score_class; ?> rounded-md"><?php echo htmlspecialchars($exam['score']); ?>%</span></td>
                                                <td class="p-3 text-gray-600"><?php echo htmlspecialchars($class_averages[$exam['subject']] ?? 'N/A'); ?>%</td>
                                                <td class="p-3 text-gray-600"><?php echo date('d M Y', strtotime($exam['start_time'])); ?></td>
                                                <td class="p-3 text-gray-600"><?php echo $time_taken_str; ?></td>
                                                <td class="p-3"><a href="review_exam.php?session_id=<?php echo $exam['id']; ?>" class="bg-slate-200 text-slate-800 px-3 py-1 rounded text-sm font-medium hover:bg-slate-300 transition-colors">Review</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="p-4 text-center text-gray-500">No mock exam results found. Time to take your first test!</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-8 flex justify-end">
                            <a href='exam-selection.php' >
                                <button class="bg-theme-red hover:bg-theme-dark-red text-white font-semibold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105">
                                    <i class="fas fa-pen mr-2"></i> Attempt a New Mock
                                </button>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- Right Column: Tips and Resources -->
                <div class="lg:col-span-1">
                    <div class="dashboard-card tip-card bg-white rounded-xl shadow-md p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-theme-black">Examiner's Tips</h2>
                            <i class="fas fa-lightbulb text-theme-red text-xl"></i>
                        </div>
                        <div class="mb-4">
                            <div id="currentTip" class="bg-gray-50 p-4 rounded-lg"><p class="text-theme-black"></p></div>
                        </div>
                        <div class="flex justify-center">
                            <button id="newTipBtn" class="bg-gray-100 hover:bg-gray-200 text-theme-red font-medium py-2 px-4 rounded-md transition-colors">
                                <i class="fas fa-sync-alt mr-2"></i> Show Another Tip
                            </button>
                        </div>
                    </div>
                    <div class="dashboard-card bg-white rounded-xl shadow-md p-6">
                         <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-theme-black">Your Resources</h2>
                            <i class="fas fa-bookmark text-theme-red text-xl"></i>
                        </div>
                        <div class="space-y-4">
                            <!-- New "My Profile" link added here -->
                            <a href="view_profile.php" class="group flex items-center p-3 bg-gray-50 rounded-lg hover:bg-theme-red transition-colors">
                                <div class="bg-theme-red text-white p-2 rounded-full mr-3">
                                    <i class="fas fa-user-circle w-5 h-5"></i>
                                </div>
                                <span class="font-medium text-theme-black group-hover:text-white">My Profile</span>
                            </a>
                            <a href="full-history.php" class="group flex items-center p-3 bg-gray-50 rounded-lg hover:bg-theme-red transition-colors"><div class="bg-theme-red text-white p-2 rounded-full mr-3"><i class="fas fa-chart-line w-5 h-5"></i></div><span class="font-medium text-theme-black group-hover:text-white">Full Mock History</span></a>
                            <a href="tutors.php" class="group flex items-center p-3 bg-gray-50 rounded-lg hover:bg-theme-red transition-colors"><div class="bg-theme-red text-white p-2 rounded-full mr-3"><i class="fas fa-comments w-5 h-5"></i></div><span class="font-medium text-theme-black group-hover:text-white">Contact a Tutor</span></a>
                            <a href="resources.php" class="group flex items-center p-3 bg-gray-50 rounded-lg hover:bg-theme-red transition-colors"><div class="bg-theme-red text-white p-2 rounded-full mr-3"><i class="fas fa-book w-5 h-5"></i></div><span class="font-medium text-theme-black group-hover:text-white">Resource Library</span></a>
                            <a href="support.php" class="group flex items-center p-3 bg-gray-50 rounded-lg hover:bg-theme-red transition-colors"><div class="bg-theme-red text-white p-2 rounded-full mr-3"><i class="fas fa-headset w-5 h-5"></i></div><span class="font-medium text-theme-black group-hover:text-white">Get Support</span></a>
                            <!-- <a href="schedule-exam.php" class="group flex items-center p-3 bg-gray-50 rounded-lg hover:bg-theme-red transition-colors"><div class="bg-theme-red text-white p-2 rounded-full mr-3"><i class="fas fa-calendar-alt w-5 h-5"></i></div><span class="font-medium text-theme-black group-hover:text-white">Schedule Exam</span></a> -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- NEW: Full-width Notice Board Section -->
            <div class="notice-board-section container mx-auto mt-8">
                <h2 class="text-2xl font-bold text-theme-black mb-6 text-center">Important Announcements</h2>
                <?php if (!empty($announcements)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="bg-gray-50 p-6 rounded-lg shadow-sm border border-gray-200">
                                <h3 class="notice-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <p class="notice-content"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center p-8 bg-gray-50 rounded-lg">
                        <i class="fas fa-bullhorn text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-500">No important announcements at this time. Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>

        <footer class="bg-theme-black text-white py-6">
            <div class="container mx-auto px-4">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="mb-4 md:mb-0">
                        <h3 class="font-bold text-xl">PSB Learning Hub</h3>
                        <p class="text-gray-400 text-sm">Preparing tomorrow's leaders</p>
                    </div>
                    <div class="flex space-x-6">
                        <a href="#" class="hover:text-theme-red transition-colors"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="hover:text-theme-red transition-colors"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="hover:text-theme-red transition-colors"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="hover:text-theme-red transition-colors"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="border-t border-gray-700 mt-6 pt-6 text-sm text-gray-400 text-center">
                    <p>&copy; <?php echo date('Y'); ?> Learning Hub. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart.js initialization
            const ctx = document.getElementById('examChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'bar', data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [ { label: 'Your Score', data: <?php echo json_encode($chart_user_scores); ?>, backgroundColor: '#c51a1d', borderRadius: 6, barPercentage: 0.7 }, { label: 'Class Average', data: <?php echo json_encode($chart_class_average_scores); ?>, backgroundColor: '#a0a0a0', borderRadius: 6, barPercentage: 0.7 } ] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1a1a1a', titleFont: { size: 14, family: "Inter" }, bodyFont: { size: 13, family: "Inter" }, padding: 12, displayColors: false, callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y}%` } } }, scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0, 0, 0, 0.05)' }, ticks: { color: '#1a1a1a', font: { family: "Inter" } } }, x: { grid: { display: false }, ticks: { color: '#1a1a1a', font: { family: "Inter" } } } } }
            });

            // Tip rotation functionality
            const tips = [ "<strong>Stay consistent:</strong> Regular, short study sessions are more effective than cramming.", "<strong>Understand, don't memorize:</strong> Focus on grasping concepts rather than just memorizing facts.", "<strong>Practice past papers:</strong> This is key to understanding exam patterns and time management.", "<strong>Review mistakes:</strong> Learn from your errors by revisiting incorrect answers.", "<strong>Take breaks:</strong> Step away from your studies to refresh your mind and avoid burnout." ];
            const tipElement = document.getElementById('currentTip').querySelector('p');
            const newTipBtn = document.getElementById('newTipBtn');
            let currentTipIndex = 0;
            function showTip() { tipElement.innerHTML = tips[currentTipIndex]; }
            newTipBtn.addEventListener('click', () => { currentTipIndex = (currentTipIndex + 1) % tips.length; showTip(); });
            showTip();

            // --- MOBILE MENU SCRIPT ---
            const menuButton = document.getElementById('mobile-menu-button');
            const closeButton = document.getElementById('mobile-menu-close-button');
            const mobileMenu = document.getElementById('mobile-menu');

            menuButton.addEventListener('click', () => {
                mobileMenu.classList.remove('-translate-x-full');
            });

            closeButton.addEventListener('click', () => {
                mobileMenu.classList.add('-translate-x-full');
            });
            
            // Close menu if user clicks on the overlay
            mobileMenu.addEventListener('click', (e) => {
                if (e.target === mobileMenu) {
                    mobileMenu.classList.add('-translate-x-full');
                }
            });
        });
    </script>
</body>
</html>
