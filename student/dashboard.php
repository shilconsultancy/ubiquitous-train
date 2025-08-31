<?php
// --- DEVELOPMENT/DEBUGGING: Display all PHP errors ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Always start the session first

// Include the database connection file
if (!file_exists('db_config.php')) {
    die("FATAL ERROR: db_config.php not found. Please ensure the database connection file exists.");
}
require_once 'db_config.php';

// --- Check for Database Connection Errors ---
if (!isset($conn) || $conn->connect_error) {
    die("Database Connection Failed: " . (isset($conn) ? $conn->connect_error : "The database connection object was not created. Check db_config.php."));
}

// --- Access control ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../index.php');
    exit;
}

// --- DATA FETCHING ---
$current_user_id = $_SESSION['user_id'] ?? 0;

// --- STEP 1: Get user details ---
$username = $_SESSION['username'] ?? 'Guest';
$acca_id = $_SESSION['acca_id'] ?? 'N/A';
$profile_pic = '';

// Fetch user's profile image path
$sql_user_image = "SELECT profile_image_path FROM users WHERE id = ?";
if ($stmt_image = $conn->prepare($sql_user_image)) {
    $stmt_image->bind_param("i", $current_user_id);
    if ($stmt_image->execute()) {
        $result_image = $stmt_image->get_result();
        if ($user_image_data = $result_image->fetch_assoc()) {
            $default_image_relative_path = 'admin/assets/images/default_avatar.png';
            $image_relative_path = !empty($user_image_data['profile_image_path']) ? $user_image_data['profile_image_path'] : $default_image_relative_path;
            $profile_pic = rtrim(BASE_URL, '/') . '/' . ltrim($image_relative_path, '/');
            $profile_pic = str_replace('//', '/', $profile_pic);
        }
    }
    $stmt_image->close();
}


// --- STEP 2: Get recent completed exams for the table ---
$user_exams = [];
$sql_user = "SELECT id, subject, score, start_time, end_time FROM exam_sessions WHERE user_id = ? AND completed = 1 ORDER BY start_time DESC";
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
$all_user_exam_subjects = array_column($user_exams, 'subject');
$chart_subjects_unique_for_avg_query = array_unique($all_user_exam_subjects);

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

// --- STEP 4: Prepare chart data (Highest Scores per Subject) ---
$user_subject_highest_scores = [];
foreach ($user_exams as $exam) {
    $subject = $exam['subject'];
    if (!isset($user_subject_highest_scores[$subject]) || $exam['score'] > $user_subject_highest_scores[$subject]) {
        $user_subject_highest_scores[$subject] = $exam['score'];
    }
}

$chart_labels = [];
$chart_user_scores = [];
$chart_class_average_scores = [];

ksort($user_subject_highest_scores);

foreach ($user_subject_highest_scores as $subject => $highest_score) {
    $chart_labels[] = $subject;
    $chart_user_scores[] = $highest_score;
    $chart_class_average_scores[] = $class_averages[$subject] ?? 0;
}

// --- STEP 5: Get recent announcements ---
$announcements = [];
$sql_announcements = "SELECT title, content, published_date FROM notices WHERE status = 'Published' ORDER BY published_date DESC LIMIT 10";
$result_announcements = $conn->query($sql_announcements);
if ($result_announcements) {
    $announcements = $result_announcements->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Error fetching announcements: " . $conn->error);
}

// --- PREPARE SLIDER NOTICES ---
$slider_notices_string = '';
if (!empty($announcements)) {
    $slider_notices = [];
    foreach ($announcements as $announcement) {
        $slider_notices[] = htmlspecialchars($announcement['title']);
    }
    $slider_notices_string = implode(' &nbsp; &nbsp; ★ &nbsp; &nbsp; ', $slider_notices);
}


// --- STEP 6: Calculate Stats for Stat Cards ---
$courses_taken_count = count($user_subject_highest_scores);
$total_score = 0;
$passed_exams = 0;
foreach($user_subject_highest_scores as $score) {
    $total_score += $score;
    if ($score >= 50) {
        $passed_exams++;
    }
}
$average_score = $courses_taken_count > 0 ? round($total_score / $courses_taken_count) : 0;
$pass_rate = $courses_taken_count > 0 ? round(($passed_exams / $courses_taken_count) * 100) : 0;


// Close the database connection
$conn->close();

// Set the current page for the sidebar active state
$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | PSB Learning Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        @keyframes marquee {
            0%   { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        .animate-marquee {
            animation: marquee 40s linear infinite;
        }
        .marquee-container:hover .animate-marquee {
            animation-play-state: paused;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="h-screen flex flex-col">
        <!-- Header: Full-width at the top -->
        <?php require_once 'header.php'; ?>

        <!-- Main container for sidebar and content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar: On the left -->
            <?php require_once 'sidebar.php'; ?>

            <!-- Content Area: Takes remaining space -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Notice Slider -->
                <?php if (!empty($slider_notices_string)): ?>
                <div class="bg-theme-black text-black shadow-md overflow-hidden marquee-container flex-shrink-0">
                    <div class="container mx-auto px-6 py-2 flex items-center space-x-4">
                        <span class="bg-theme-red text-black text-xs font-bold px-2 py-1 rounded-md flex-shrink-0">UPDATES:</span>
                        <div class="flex-1 relative h-6 overflow-hidden">
                            <p class="absolute whitespace-nowrap animate-marquee font-medium">
                                <?php echo $slider_notices_string; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Main scrollable content -->
                <main class="flex-1 overflow-y-auto p-6">
                    <div class="container mx-auto">
                        <!-- Welcome Banner -->
                        <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 flex items-center space-x-6">
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-md">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
                                <p class="text-gray-500">ACCA ID: <?php echo htmlspecialchars($acca_id); ?></p>
                                <p class="text-sm text-gray-500 mt-1">Let's continue your learning journey.</p>
                            </div>
                        </div>

                        <!-- Stat Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                            <div class="bg-white p-6 rounded-2xl shadow-sm flex items-center space-x-4">
                                <div class="p-3 rounded-full bg-blue-100"><i class="fas fa-book-open text-2xl text-blue-500"></i></div>
                                <div>
                                    <p class="text-sm text-gray-500">Papers Taken</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo $courses_taken_count; ?></p>
                                </div>
                            </div>
                            <div class="bg-white p-6 rounded-2xl shadow-sm flex items-center space-x-4">
                                <div class="p-3 rounded-full bg-green-100"><i class="fas fa-check-circle text-2xl text-green-500"></i></div>
                                <div>
                                    <p class="text-sm text-gray-500">Pass Rate</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo $pass_rate; ?>%</p>
                                </div>
                            </div>
                            <div class="bg-white p-6 rounded-2xl shadow-sm flex items-center space-x-4">
                                <div class="p-3 rounded-full bg-yellow-100"><i class="fas fa-star text-2xl text-yellow-500"></i></div>
                                <div>
                                    <p class="text-sm text-gray-500">Average Score</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo $average_score; ?>%</p>
                                </div>
                            </div>
                            <div class="bg-white p-6 rounded-2xl shadow-sm flex items-center space-x-4">
                                <div class="p-3 rounded-full bg-red-100"><i class="fas fa-pen-alt text-2xl text-red-500"></i></div>
                                <div>
                                    <p class="text-sm text-gray-500">Mocks Completed</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo count($user_exams); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Main Grid -->
                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                            <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm p-6">
                                <h3 class="text-xl font-bold text-gray-800 mb-4">Performance Overview (Highest Scores)</h3>
                                <div class="h-96">
                                    <canvas id="examChart"></canvas>
                                </div>
                            </div>
                            <div class="bg-white rounded-2xl shadow-sm p-6">
                                <h3 class="text-xl font-bold text-gray-800 mb-4">Recent Announcements</h3>
                                <div class="space-y-4">
                                    <?php if (!empty($announcements)): 
                                        $recent_announcements = array_slice($announcements, 0, 3);
                                    ?>
                                        <?php foreach ($recent_announcements as $announcement): ?>
                                        <div class="p-4 bg-gray-50 rounded-lg">
                                            <div class="flex items-start space-x-3">
                                                <i class="fas fa-bullhorn text-theme-red mt-1"></i>
                                                <div>
                                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></p>
                                                    <p class="text-xs text-gray-400 mt-1"><?php echo date('d M Y', strtotime($announcement['published_date'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center p-4 text-gray-500">
                                            <i class="fas fa-info-circle text-2xl mb-2"></i>
                                            <p>No new announcements.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Exam Results Table -->
                        <div class="mt-6 bg-white rounded-2xl shadow-sm p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-gray-800">Recent Mock Results</h3>
                                <a href="exam-selection.php" class="bg-theme-red hover:bg-theme-dark-red text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
                                    Attempt New Mock
                                </a>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="p-3 font-semibold text-gray-600">Paper</th>
                                            <th class="p-3 font-semibold text-gray-600">Score</th>
                                            <th class="p-3 font-semibold text-gray-600">Class Avg.</th>
                                            <th class="p-3 font-semibold text-gray-600">Date</th>
                                            <th class="p-3 font-semibold text-gray-600">Time Taken</th>
                                            <th class="p-3 font-semibold text-gray-600">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($user_exams)): ?>
                                            <?php $recent_exams = array_slice($user_exams, 0, 5); ?>
                                            <?php foreach ($recent_exams as $exam): ?>
                                                <?php
                                                    $time_taken_str = 'N/A';
                                                    if (!is_null($exam['start_time']) && !is_null($exam['end_time'])) {
                                                        $interval = (new DateTime($exam['start_time']))->diff(new DateTime($exam['end_time']));
                                                        $time_taken_str = $interval->format('%h h %i m');
                                                    }
                                                    $score_class = $exam['score'] >= 50 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                                ?>
                                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                    <td class="p-3 font-medium text-gray-800"><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                    <td class="p-3"><span class="px-2 py-1 text-sm font-semibold <?php echo $score_class; ?> rounded-full"><?php echo htmlspecialchars($exam['score']); ?>%</span></td>
                                                    <td class="p-3 text-gray-600"><?php echo htmlspecialchars($class_averages[$exam['subject']] ?? 'N/A'); ?>%</td>
                                                    <td class="p-3 text-gray-600"><?php echo date('d M Y', strtotime($exam['start_time'])); ?></td>
                                                    <td class="p-3 text-gray-600"><?php echo $time_taken_str; ?></td>
                                                    <td class="p-3"><a href="review_exam.php?session_id=<?php echo $exam['id']; ?>" class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm font-medium hover:bg-gray-300 transition-colors">Review</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="p-4 text-center text-gray-500">No mock exam results found. Time to take your first test!</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Chart.js Initialization ---
    const chartLabels = <?php echo json_encode($chart_labels); ?>;
    if (chartLabels.length > 0) {
        const ctx = document.getElementById('examChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Your Highest Score',
                    data: <?php echo json_encode($chart_user_scores); ?>,
                    backgroundColor: '#c51a1d',
                    borderColor: '#a81013',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.6
                }, {
                    label: 'Class Average',
                    data: <?php echo json_encode($chart_class_average_scores); ?>,
                    backgroundColor: '#e5e7eb',
                    borderColor: '#d1d5db',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end' },
                    tooltip: {
                        backgroundColor: '#1a1a1a',
                        titleFont: { size: 14, family: "Inter" },
                        bodyFont: { size: 13, family: "Inter" },
                        padding: 12,
                        callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y}%` }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: '#f3f4f6' },
                        ticks: { color: '#6b7280', font: { family: "Inter" } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#6b7280', font: { family: "Inter" } }
                    }
                }
            }
        });
    } else {
        const chartContainer = document.getElementById('examChart').parentElement;
        chartContainer.innerHTML = `<div class="flex items-center justify-center h-full text-gray-500"><p>No performance data yet. Complete a mock exam to see your progress!</p></div>`;
    }

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