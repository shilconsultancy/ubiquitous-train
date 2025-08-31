<?php
// *** START SESSION TO GET USER ID ***
session_start(); 

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration file
// Assumed to be in the same directory based on previous context
require_once 'db_config.php';

// --- PAGINATION & DATA FETCHING ---

// *** UPDATED: Use the logged-in user's ID from the session ***
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) { // FIX: Check for 'user_id'
    header('Location: ../index.php'); // Redirect if not logged in
    exit;
}
$current_user_id = $_SESSION['user_id']; // FIX: Use 'user_id'

// --- Database Connection Validation ---
// FIX: Using $conn as defined in db_config.php
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established: " . (isset($conn) ? $conn->connect_error : "Connection object not found."));
}

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
// FIX: Use $conn for database operations
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
// FIX: Use $conn for database operations
if ($stmt_history = $conn->prepare($history_sql)) {
    $stmt_history->bind_param("iii", $current_user_id, $items_per_page, $offset);
    $stmt_history->execute();
    $result = $stmt_history->get_result();
    $exam_history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_history->close();
} else {
    error_log("Error preparing exam history query: " . $conn->error);
}

// FIX: Close the database connection at the end of PHP logic
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full Exam History - Student Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'theme-red': '#c51a1d',
                        'theme-dark-red': '#a81013',
                        'theme-black': '#1a1a1a',
                        'light-gray': '#f5f7fa'
                    }
                }
            }
        }
    </script>
    <style>
        .pagination-link {
            padding: 8px 16px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        .pagination-link.active {
            background-color: #c51a1d;
            color: white;
            font-weight: bold;
        }
        .pagination-link:not(.active) {
            background-color: #e2e8f0;
            color: #2d3748;
        }
        .pagination-link:not(.active):hover {
            background-color: #cbd5e0;
        }
        .pagination-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        }
    </style>
</head>
<body class="bg-light-gray font-sans">
    <div class="min-h-screen flex flex-col">
        <header class="bg-theme-red text-white shadow-md">
            <div class="container mx-auto px-4 py-5">
                <h1 class="text-2xl font-bold">PSB Learning Hub</h1>
            </div>
        </header>

        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="bg-white rounded-xl shadow-md p-6 lg:p-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                    <h2 class="text-2xl font-bold text-theme-black mb-4 sm:mb-0">Full Exam History</h2>
                    <a href="dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-theme-black font-semibold py-2 px-4 rounded-lg transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse responsive-table"> <!-- Added responsive-table class -->
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="p-3 text-left text-theme-black font-medium">Paper</th>
                                <th class="p-3 text-left text-theme-black font-medium">Score</th>
                                <th class="p-3 text-left text-theme-black font-medium">Date</th>
                                <th class="p-3 text-left text-theme-black font-medium">Time Taken</th>
                                <th class="p-3 text-left text-theme-black font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
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
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="p-3 font-medium text-theme-black" data-label="Paper"><?php echo htmlspecialchars($exam['subject']); ?></td>
                                        <td class="p-3" data-label="Score">
                                            <span class="px-2 py-1 <?php echo $score_class; ?> rounded-md"><?php echo htmlspecialchars($exam['score']); ?>%</span>
                                        </td>
                                        <td class="p-3 text-gray-600" data-label="Date"><?php echo date('d M Y, H:i', strtotime($exam['start_time'])); ?></td>
                                        <td class="p-3 text-gray-600" data-label="Time Taken"><?php echo $time_taken_str; ?></td>
                                        <td class="p-3" data-label="Action">
                                            <a href="review_exam.php?session_id=<?php echo $exam['id']; ?>" class="bg-slate-200 text-slate-800 px-3 py-1 rounded text-sm font-medium hover:bg-slate-300 transition-colors">
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
                    <div class="mt-8 flex justify-center items-center space-x-2">
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
        </main>

        <footer class="bg-theme-black text-white py-6">
            <div class="container mx-auto px-4 text-center">
                 <p>&copy; <?php echo date('Y'); ?> Learning Hub. All rights reserved.</p>
            </div>
        </footer>
    </div>
</body>
</html>
