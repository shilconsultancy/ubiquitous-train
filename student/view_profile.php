<?php
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// This now includes the BASE_URL constant
require_once 'db_config.php';

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established.");
}

// --- Authentication Check (for all logged-in users) ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id']) || $_SESSION['user_id'] === 0) {
    header('Location: ../index.php'); // Redirect to login page if not properly authenticated
    exit;
}

$current_user_id = $_SESSION['user_id'];
$user_data = null; // Will hold all fetched user details (username, email, role, etc.)
$error_message = '';

// Initialize student-specific data
$fees_history = [];
$exams_history = [];
$stats = [
    'total_billed' => 0,
    'total_paid' => 0,
    'total_pending' => 0,
    'exams_scheduled' => 0,
];
$is_student = false; // Flag to check if the current user is a student

// 1. Fetch Current User's Primary Details (regardless of role)
$stmt_user_details = $conn->prepare("SELECT id, username, email, acca_id, created_at, profile_image_path, role FROM users WHERE id = ?");
$stmt_user_details->bind_param("i", $current_user_id);
$stmt_user_details->execute();
$result_user_details = $stmt_user_details->get_result();

if ($result_user_details->num_rows === 1) {
    $user_data = $result_user_details->fetch_assoc();

    // Determine if the user is a student
    if ($user_data['role'] === 'student') {
        $is_student = true;
    }

    // --- Corrected Image Path Handling ---
    $default_image_relative_path = 'admin/assets/images/default_avatar.png'; // Path relative to project root
    $image_relative_path = !empty($user_data['profile_image_path']) ? $user_data['profile_image_path'] : $default_image_relative_path;

    $profile_pic = rtrim(BASE_URL, '/') . '/' . ltrim($image_relative_path, '/');
    $profile_pic = str_replace('//', '/', $profile_pic); // Remove any double slashes


    // 2. Fetch Fee History and Calculate Stats ONLY IF the user is a student
    if ($is_student) {
        $stmt_fees = $conn->prepare("SELECT fee_type, amount, due_date, payment_status FROM fees WHERE student_id = ? ORDER BY due_date DESC");
        $stmt_fees->bind_param("i", $current_user_id);
        $stmt_fees->execute();
        $result_fees = $stmt_fees->get_result();
        while($row = $result_fees->fetch_assoc()) {
            $fees_history[] = $row;
            $stats['total_billed'] += $row['amount'];
            if ($row['payment_status'] === 'Paid') {
                $stats['total_paid'] += $row['amount'];
            }
        }
        $stats['total_pending'] = $stats['total_billed'] - $stats['total_paid'];
        $stmt_fees->close();

        // 3. Fetch Exam History and Stats ONLY IF the user is a student
        $stmt_exams = $conn->prepare("SELECT subject, score, start_time, end_time FROM exam_sessions WHERE user_id = ? AND completed = 1 ORDER BY start_time DESC");
        $stmt_exams->bind_param("i", $current_user_id);
        $stmt_exams->execute();
        $result_exams = $stmt_exams->get_result();
        while($row = $result_exams->fetch_assoc()) {
            $exams_history[] = $row;
        }
        $stats['exams_scheduled'] = count($exams_history);
        $stmt_exams->close();
    } // End if ($is_student)

} else {
    $error_message = "User profile data not found for ID: " . $current_user_id;
}
$stmt_user_details->close();

// --- Fetch Announcements ---
$announcements = [];
$sql_announcements = "SELECT title, content, published_date FROM notices WHERE status = 'Published' ORDER BY published_date DESC LIMIT 5"; // Increased limit for tab view
$result_announcements = $conn->query($sql_announcements);
if ($result_announcements) {
    $announcements = $result_announcements->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Error fetching announcements: " . $conn->error);
}


$conn->close();

// Set the current page for the sidebar active state
$currentPage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PSB Learning Hub</title>
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
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .shadow-custom { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        /* Responsive Table Styles for mobile */
        @media (max-width: 767px) {
            .responsive-table thead { display: none; }
            .responsive-table tr { 
                display: block; 
                margin-bottom: 1rem; 
                border: 1px solid #e5e7eb; 
                border-radius: 0.5rem; 
                padding: 1rem; 
            }
            .responsive-table td { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 0.5rem 0; 
                border-bottom: 1px dashed #e5e7eb; 
            }
            .responsive-table td:last-child { border-bottom: none; }
            .responsive-table td::before { 
                content: attr(data-label); 
                font-weight: 600; 
                color: #4b5563; 
                margin-right: 1rem; 
            }
            main {
                padding: 1rem;
            }
            .grid.grid-cols-1.lg\:grid-cols-3.gap-6 {
                grid-template-columns: 1fr;
            }
            .grid.grid-cols-2.md\:grid-cols-4.gap-4 {
                grid-template-columns: 1fr;
            }
            .tab-btn {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 h-screen flex flex-col">

    <?php require_once 'header.php'; ?>

    <div class="flex flex-1 overflow-hidden">
        
        <?php require_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="container mx-auto">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">My Profile</h2>
                            <p class="text-gray-500 mt-1">View your personal information and academic records.</p>
                        </div>
                        <?php if ($user_data): ?>
                        <div class="mt-4 sm:mt-0">
                            <a href="edit_profile.php" class="bg-theme-red hover:bg-theme-dark-red text-black font-semibold py-2 px-4 rounded-lg transition duration-300 flex items-center"><i class="fas fa-edit mr-2"></i> Edit Profile</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($user_data): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 lg:items-start gap-6">
                        <div class="lg:col-span-1 bg-white rounded-xl shadow-custom p-6 text-center fade-in">
                            
                            <img src="<?= htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="w-24 h-24 rounded-full object-cover mx-auto mb-4 border-4 border-white shadow-lg">
                            
                            <h3 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($user_data['username']); ?></h3>
                            <p class="text-gray-500">ACCA ID: <?= htmlspecialchars($user_data['acca_id'] ?? 'N/A'); ?></p>
                            <p class="text-sm text-gray-500 mt-2"><?= htmlspecialchars($user_data['email']); ?></p>
                            <p class="text-xs text-gray-400 mt-4">Account created on: <?= date('M d, Y', strtotime($user_data['created_at'])); ?></p>
                            <p class="text-lg font-bold mt-2">Role: <span class="text-theme-red"><?= htmlspecialchars(ucfirst($user_data['role'])); ?></span></p>

                        </div>

                        <div class="lg:col-span-2 space-y-6">
                            <?php if ($is_student): ?>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 fade-in" style="animation-delay: 0.1s;">
                                <div class="bg-white p-4 rounded-xl shadow-custom text-center"><p class="text-sm text-gray-500">Total Billed</p><p class="text-xl font-bold mt-1">BDT <?= number_format($stats['total_billed'], 2); ?></p></div>
                                <div class="bg-white p-4 rounded-xl shadow-custom text-center"><p class="text-sm text-gray-500">Total Paid</p><p class="text-xl font-bold mt-1 text-green-600">BDT <?= number_format($stats['total_paid'], 2); ?></p></div>
                                <div class="bg-white p-4 rounded-xl shadow-custom text-center"><p class="text-sm text-gray-500">Pending</p><p class="text-xl font-bold mt-1 text-red-600">BDT <?= number_format($stats['total_pending'], 2); ?></p></div>
                                <div class="bg-white p-4 rounded-xl shadow-custom text-center"><p class="text-sm text-gray-500">Exams Taken</p><p class="text-xl font-bold mt-1"><?= $stats['exams_scheduled']; ?></p></div>
                            </div>

                            <div class="bg-white rounded-xl shadow-custom fade-in" style="animation-delay: 0.2s;">
                                <div class="flex border-b" id="history-tabs">
                                    <button class="tab-btn px-6 py-4 font-medium text-theme-red border-b-2 border-theme-red" data-tab="notices">Notices</button>
                                    <button class="tab-btn px-6 py-4 font-medium text-gray-500" data-tab="fees">Fee History</button>
                                    <button class="tab-btn px-6 py-4 font-medium text-gray-500" data-tab="exams">Exam History</button>
                                </div>
                                <div class="p-4">
                                    <div id="tab-notices" class="tab-content">
                                        <?php if (!empty($announcements)): ?>
                                            <div class="space-y-4">
                                                <?php foreach ($announcements as $announcement): ?>
                                                    <div class="bg-white rounded-lg p-5 border-l-4 border-theme-red shadow-sm">
                                                        <div class="flex justify-between items-start">
                                                            <div>
                                                                <h4 class="font-bold text-md text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                                                <p class="text-xs text-gray-500 mb-2">Published on: <?php echo date('F d, Y', strtotime($announcement['published_date'])); ?></p>
                                                            </div>
                                                            <div class="bg-red-100 text-theme-red p-2 rounded-full flex-shrink-0">
                                                               <i class="fas fa-bullhorn text-md h-5 w-5 text-center"></i>
                                                            </div>
                                                        </div>
                                                        <p class="text-gray-600 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-6 rounded-lg">
                                                <div class="flex items-center">
                                                    <i class="fas fa-info-circle text-3xl mr-4"></i>
                                                    <div>
                                                        <p class="font-bold">All Clear!</p>
                                                        <p>No new notices at this time. Check back later for updates.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div id="tab-fees" class="tab-content hidden">
                                        <div class="overflow-x-auto">
                                        <table class="w-full text-sm responsive-table">
                                            <thead class="text-left"><tr class="border-b"><th class="py-2 px-3 font-medium text-gray-600">Type</th><th class="py-2 px-3 font-medium text-gray-600">Amount</th><th class="py-2 px-3 font-medium text-gray-600">Due Date</th><th class="py-2 px-3 font-medium text-gray-600">Status</th></tr></thead>
                                            <tbody class="divide-y md:divide-y-0">
                                                <?php if (!empty($fees_history)): foreach ($fees_history as $fee): ?>
                                                <tr><td data-label="Type" class="py-3 px-3"><?= ucwords(htmlspecialchars($fee['fee_type'])); ?></td><td data-label="Amount" class="py-3 px-3">BDT <?= number_format($fee['amount'], 2); ?></td><td data-label="Due Date" class="py-3 px-3"><?= date('M d, Y', strtotime($fee['due_date'])); ?></td><td data-label="Status" class="py-3 px-3"><span class="text-xs font-medium px-2 py-1 rounded-full <?= match ($fee['payment_status']) { 'Paid' => 'bg-green-100 text-green-800', 'Pending' => 'bg-yellow-100 text-yellow-800', 'Overdue' => 'bg-red-100 text-red-800', default => 'bg-gray-100 text-gray-800' }; ?>"><?= htmlspecialchars($fee['payment_status']); ?></span></td></tr>
                                                <?php endforeach; else: ?>
                                                <tr><td colspan="4" class="text-center py-4 text-gray-500">No fee records found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                    <div id="tab-exams" class="tab-content hidden">
                                        <div class="overflow-x-auto">
                                        <table class="w-full text-sm responsive-table">
                                            <thead class="text-left"><tr class="border-b"><th class="py-2 px-3 font-medium text-gray-600">Subject</th><th class="py-2 px-3 font-medium text-gray-600">Score</th><th class="py-2 px-3 font-medium text-gray-600">Date</th><th class="py-2 px-3 font-medium text-gray-600">Time Taken</th></tr></thead>
                                            <tbody class="divide-y md:divide-y-0">
                                                <?php if (!empty($exams_history)): foreach ($exams_history as $exam): 
                                                    $time_taken_str = 'N/A';
                                                    if (!is_null($exam['start_time']) && !is_null($exam['end_time'])) {
                                                        $interval = (new DateTime($exam['start_time']))->diff(new DateTime($exam['end_time']));
                                                        $time_taken_str = $interval->format('%h h %i m');
                                                    }
                                                    $score_class = $exam['score'] >= 65 ? 'bg-green-100 text-green-800' : ($exam['score'] >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                ?>
                                                <tr>
                                                    <td data-label="Subject" class="py-3 px-3"><?= htmlspecialchars($exam['subject']); ?></td>
                                                    <td data-label="Score" class="py-3 px-3"><span class="px-2 py-1 <?= $score_class; ?> rounded-md"><?= htmlspecialchars($exam['score']); ?>%</span></td>
                                                    <td data-label="Date" class="py-3 px-3"><?= date('M d, Y', strtotime($exam['start_time'])); ?></td>
                                                    <td data-label="Time Taken" class="py-3 px-3"><?= htmlspecialchars($time_taken_str); ?></td>
                                                </tr>
                                                <?php endforeach; else: ?>
                                                <tr><td colspan="4" class="text-center py-4 text-gray-500">No exam records found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                                <div class="bg-white rounded-xl shadow-custom p-6 text-center">
                                    <i class="fas fa-info-circle text-4xl text-gray-400 mb-4"></i>
                                    <p class="text-gray-600 text-lg">Fee and Exam history are only applicable for student accounts.</p>
                                    <p class="text-gray-500 text-sm mt-2">As a <?= htmlspecialchars(ucfirst($user_data['role'])); ?>, this section is not available for your role.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="bg-white rounded-xl shadow-custom p-8 text-center">
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                                <p class="font-bold">Error!</p>
                                <p><?= htmlspecialchars($error_message); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    tabButtons.forEach(btn => {
                        btn.classList.remove('text-theme-red', 'border-theme-red');
                        btn.classList.add('text-gray-500');
                    });
                    this.classList.add('text-theme-red', 'border-theme-red');
                    this.classList.remove('text-gray-500');
                    
                    const targetTab = this.dataset.tab;
                    tabContents.forEach(content => {
                        if (content.id === 'tab-' + targetTab) {
                            content.classList.remove('hidden');
                        } else {
                            content.classList.add('hidden');
                        }
                    });
                });
            });

            // --- Mobile Menu Toggle Script ---
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