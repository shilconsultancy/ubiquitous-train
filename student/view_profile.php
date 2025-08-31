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
        // Note: The original 'exams' table query in view_student.php might be simplified for student dashboard.
        // Assuming 'exam_sessions' table holds completed exams for users, as seen in dashboard.
        $stmt_exams = $conn->prepare("SELECT subject, score, start_time, end_time FROM exam_sessions WHERE user_id = ? AND completed = 1 ORDER BY start_time DESC");
        $stmt_exams->bind_param("i", $current_user_id);
        $stmt_exams->execute();
        $result_exams = $stmt_exams->get_result();
        while($row = $result_exams->fetch_assoc()) {
            $exams_history[] = $row;
        }
        $stats['exams_scheduled'] = count($exams_history); // Assuming 'scheduled' implies completed tests count from exam_sessions
        $stmt_exams->close();
    } // End if ($is_student)

} else {
    $error_message = "User profile data not found for ID: " . $current_user_id;
}
$stmt_user_details->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PSB Learning Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5', secondary: '#0EA5E9', dark: '#1E293B', light: '#F8FAFC',
                        'theme-red': '#c51a1d', 'theme-dark-red': '#a81013', 'theme-black': '#1a1a1a', 'light-gray': '#f5f7fa'
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c5c5c5; border-radius: 4px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .active-tab {
            background: linear-gradient(90deg, rgba(79,70,229,0.1) 0%, rgba(14,165,233,0.05) 100%);
            border-left: 4px solid #4F46E5;
            color: #4F46E5;
        }
        .shadow-custom { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .sidebar-link:hover { background: linear-gradient(90deg, rgba(79,70,229,0.1) 0%, rgba(14,165,233,0.05) 100%); }
        
        /* Profile Info Group Styles */
        .profile-info-group {
            margin-bottom: 1.5rem;
        }
        .profile-label {
            display: block;
            font-weight: 600;
            color: #334155; /* Slate-700 */
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        .profile-value {
            font-size: 1.1rem;
            color: #1a202c;
            padding: 0.75rem 0;
            border-bottom: 1px dashed #e2e8f0;
            word-wrap: break-word;
        }
        .profile-value:last-child {
            border-bottom: none;
        }

        .action-button {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: all 0.2s ease-in-out;
        }
        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        /* Responsive Table Styles for mobile */
        @media (max-width: 767px) { /* Changed to max-width: 767px for typical mobile breakpoint */
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
                border-bottom: 1px dashed #e2e8f0; 
            }
            .responsive-table td:last-child { border-bottom: none; }
            .responsive-table td::before { 
                content: attr(data-label); 
                font-weight: 600; 
                color: #4b5563; 
                margin-right: 1rem; 
            }
            /* Adjust padding for main content on small screens */
            main {
                padding: 1rem; /* Adjust as needed */
            }
            .grid.grid-cols-1.lg\:grid-cols-3.gap-6 {
                grid-template-columns: 1fr; /* Stack columns on small screens */
            }
            .grid.grid-cols-2.md\:grid-cols-4.gap-4 {
                grid-template-columns: 1fr; /* Stack stat cards on small screens */
            }
            .tab-btn {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <header class="bg-theme-red text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 sm:px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="text-center md:text-left">
                    <h1 class="text-2xl font-bold tracking-tight">PSB Learning Hub</h1>
                </div>

                <div class="hidden md:flex items-center space-x-4">
                    <div class="text-right">
                        <div class="font-semibold">Welcome back, <span class="font-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</span></div>
                        <p class="text-sm text-gray-200 mt-1">Role: <span class="font-medium"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'N/A')); ?></span></p>
                    </div>
                    <a href="../logout.php" class="inline-block bg-white text-theme-red font-semibold py-2 px-5 rounded-full shadow-md hover:bg-gray-100 hover:shadow-lg transition-all duration-300 ease-in-out text-sm">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>

                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-white focus:outline-none">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

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
                        <i class="fas fa-user-circle mr-2 text-slate-500"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">Role: <span class="font-medium"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'N/A')); ?></span></p>
                </div>
                <a href="dashboard.php" class="w-full text-left inline-block bg-gray-100 text-slate-800 font-semibold py-3 px-4 rounded-lg shadow-md hover:bg-gray-200 transition-all duration-300 ease-in-out">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
                <a href="../logout.php" class="w-full text-left inline-block bg-theme-red text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:bg-theme-dark-red transition-all duration-300 ease-in-out">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </div>
    </div>


    <div class="flex flex-1">
        <?php // No sidebar included here, assuming it's part of a larger admin layout or not needed for a universal profile page ?>
        <main class="flex-1 p-4 sm:p-6 pb-24 md:pb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">My Profile</h2>
                    <p class="text-gray-500 mt-1">View your personal information and academic records.</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 sm:mt-0 w-full sm:w-auto">
                    <a href="dashboard.php" class="w-1/2 sm:w-auto bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-arrow-left mr-2"></i> Back to Dashboard</a>
                    <?php if ($user_data): ?>
                    <a href="edit_profile.php" class="w-1/2 sm:w-auto bg-primary hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-edit mr-2"></i> Edit Profile</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($user_data): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 bg-white rounded-xl shadow-custom p-6 text-center fade-in">
                    
                    <img src="<?= htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="w-24 h-24 rounded-full object-cover mx-auto mb-4 border-4 border-white shadow-lg">
                    
                    <h3 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($user_data['username']); ?></h3>
                    <p class="text-gray-500">ACCA ID: <?= htmlspecialchars($user_data['acca_id'] ?? 'N/A'); ?></p>
                    <p class="text-sm text-gray-500 mt-2"><?= htmlspecialchars($user_data['email']); ?></p>
                    <p class="text-xs text-gray-400 mt-4">Account created on: <?= date('M d, Y', strtotime($user_data['created_at'])); ?></p>
                    <?php /* REMOVED: <p class="text-lg font-bold mt-2">Role: <span class="text-primary"><?= htmlspecialchars(ucfirst($user_data['role'])); ?></span></p> */ ?>

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
                            <button class="tab-btn px-6 py-4 font-medium text-primary border-b-2 border-primary" data-tab="fees">Fee History</button>
                            <button class="tab-btn px-6 py-4 font-medium text-gray-500" data-tab="exams">Exam History</button>
                        </div>
                        <div class="p-4">
                            <div id="tab-fees" class="tab-content">
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
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    tabButtons.forEach(btn => {
                        btn.classList.remove('text-primary', 'border-primary');
                        btn.classList.add('text-gray-500');
                    });
                    this.classList.add('text-primary', 'border-primary');
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

            // --- Dropdown Menu Script (for header.php) ---
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');

            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', function() {
                    userMenu.classList.toggle('hidden');
                });

                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.add('hidden');
                    }
                });

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        userMenu.classList.add('hidden');
                    }
                });
            }

            // --- Mobile Menu Toggle Script (Crucial for sidebar visibility on mobile) ---
            const mobileMenuButton = document.getElementById('mobile-menu-button'); 
            const mobileMenu = document.getElementById('mobile-menu'); 
            const mobileMenuCloseBtn = document.getElementById('mobile-menu-close-button');


            if(mobileMenuButton){
                mobileMenuButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    mobileMenu.classList.remove('-translate-x-full'); 
                });
            }
            
            if (mobileMenuCloseBtn) {
                mobileMenuCloseBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    mobileMenu.classList.add('-translate-x-full'); 
                });
            }

            // Close mobile menu if clicked outside
            document.addEventListener('click', (e) => {
                if (mobileMenu && !mobileMenu.classList.contains('-translate-x-full') && !mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                    mobileMenu.classList.add('-translate-x-full'); 
                }
            });
        });
    </script>
</body>
</html>