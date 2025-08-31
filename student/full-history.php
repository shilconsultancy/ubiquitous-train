<?php
// *** START SESSION TO GET USER ID ***
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration file
require_once 'db_config.php';

// --- Authentication Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php'); // Redirect if not logged in
    exit;
}
$current_user_id = $_SESSION['user_id'];

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established: " . (isset($conn) ? $conn->connect_error : "Connection object not found."));
}

// --- PAGINATION & DATA FETCHING ---

// 1. Determine the current page number
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// 2. Define records per page
$items_per_page = 10;

// 3. Get total number of exams for this user
$total_items_sql = "SELECT COUNT(id) FROM exam_sessions WHERE user_id = ? AND completed = 1";
$total_items = 0;
if ($stmt_count = $conn->prepare($total_items_sql)) {
    $stmt_count->bind_param("i", $current_user_id);
    $stmt_count->execute();
    $stmt_count->bind_result($total_items);
    $stmt_count->fetch();
    $stmt_count->close();
} else {
    error_log("Error preparing total items count query: " . $conn->error);
}

// 4. Calculate total pages
$total_pages = ceil($total_items / $items_per_page);

// Ensure page number does not exceed total pages
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
} elseif ($total_pages == 0) {
    $page = 1; // If no items, ensure page is 1 to avoid negative offset
}

// 5. Calculate the OFFSET
$offset = ($page - 1) * $items_per_page;
if ($offset < 0) $offset = 0; // Ensure offset is not negative

// 6. Fetch records for the current page
$history_sql = "
    SELECT 
        id,
        subject, 
        score, 
        start_time, 
        end_time
    FROM 
        exam_sessions
    WHERE 
        user_id = ? AND completed = 1
    ORDER BY 
        start_time DESC
    LIMIT ? OFFSET ?
";

$exam_history = [];
if ($stmt_history = $conn->prepare($history_sql)) {
    $stmt_history->bind_param("iii", $current_user_id, $items_per_page, $offset);
    $stmt_history->execute();
    $result = $stmt_history->get_result();
    $exam_history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_history->close();
} else {
    error_log("Error preparing exam history query: " . $conn->error);
}

// Close the database connection
$conn->close();

// Set the current page for the sidebar active state
$currentPage = 'history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full Exam History - PSB Learning Hub</title>
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
        /* Custom scrollbar for webkit browsers */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

        .pagination-link {
            padding: 8px 16px;
            border-radius: 6px;
            transition: background-color 0.2s, color 0.2s;
            border: 1px solid #e2e8f0;
        }
        .pagination-link.active {
            background-color: #c51a1d;
            color: white;
            font-weight: bold;
            border-color: #c51a1d;
        }
        .pagination-link:not(.active) {
            background-color: white;
            color: #2d3748;
        }
        .pagination-link:not(.active):hover {
            background-color: #f7fafc;
        }
        .pagination-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f1f5f9;
        }
        /* Responsive Table Styles for mobile */
        @media (max-width: 767px) {
            .responsive-table thead { display: none; }
            .responsive-table tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                padding: 1rem;
                background-color: white;
            }
            .responsive-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 0;
                border-bottom: 1px dashed #e5e7eb;
            }
            .responsive-table td:last-child { border-bottom: none; }
            .responsive-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #4b5563;
                margin-right: 1rem;
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
                    <div class="bg-white rounded-2xl shadow-sm p-6 lg:p-8">
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">Full Exam History</h2>
                            <p class="text-gray-500 mt-1">Review your performance in all completed mock exams.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left responsive-table">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="p-3 font-semibold text-gray-600">Paper</th>
                                        <th class="p-3 font-semibold text-gray-600">Score</th>
                                        <th class="p-3 font-semibold text-gray-600">Date</th>
                                        <th class="p-3 font-semibold text-gray-600">Time Taken</th>
                                        <th class="p-3 font-semibold text-gray-600">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="md:divide-y md:divide-gray-200">
                                    <?php if (!empty($exam_history)): ?>
                                        <?php foreach ($exam_history as $exam): ?>
                                            <?php
                                                // Calculate Time Taken
                                                $time_taken_str = 'N/A';
                                                if (!is_null($exam['start_time']) && !is_null($exam['end_time'])) {
                                                    $start = new DateTime($exam['start_time']);
                                                    $end = new DateTime($exam['end_time']);
                                                    $interval = $start->diff($end);
                                                    $time_taken_str = $interval->format('%h h %i m');
                                                }

                                                // Determine Score Badge Color
                                                $score_class = 'bg-red-100 text-red-800'; // Fail
                                                if ($exam['score'] >= 65) {
                                                    $score_class = 'bg-green-100 text-green-800'; // Pass
                                                } elseif ($exam['score'] >= 50) {
                                                    $score_class = 'bg-yellow-100 text-yellow-800'; // Borderline
                                                }
                                            ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="p-3 font-medium text-gray-800" data-label="Paper"><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                <td class="p-3" data-label="Score">
                                                    <span class="px-2 py-1 text-sm font-semibold <?php echo $score_class; ?> rounded-full"><?php echo htmlspecialchars($exam['score']); ?>%</span>
                                                </td>
                                                <td class="p-3 text-gray-600" data-label="Date"><?php echo date('d M Y, H:i', strtotime($exam['start_time'])); ?></td>
                                                <td class="p-3 text-gray-600" data-label="Time Taken"><?php echo $time_taken_str; ?></td>
                                                <td class="p-3" data-label="Action">
                                                    <a href="review_exam.php?session_id=<?php echo $exam['id']; ?>" class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm font-medium hover:bg-gray-300 transition-colors">
                                                        Review
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="p-4 text-center text-gray-500">You have not completed any exams yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="mt-8 flex flex-wrap justify-center items-center gap-2">
                                <a href="?page=<?php echo $page - 1; ?>" class="pagination-link <?php if($page <= 1){ echo 'disabled'; } ?>">
                                    &laquo; Previous
                                </a>

                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="pagination-link <?php if($page == $i) {echo 'active';} ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <a href="?page=<?php echo $page + 1; ?>" class="pagination-link <?php if($page >= $total_pages) { echo 'disabled'; } ?>">
                                    Next &raquo;
                                </a>
                            </div>
                        <?php endif; ?>

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