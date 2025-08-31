<?php
/* THIS IS THE COMPLETE AND FULLY CORRECTED CODE FOR lms/student/resources.php */

session_start(); // Start the session to make session variables available

// These lines will help show any future errors instead of a white page.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// The path goes up one level from 'student' to the project root 'lms' to find the config file. This is correct.
require_once 'db_config.php';

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established: " . (isset($conn) ? $conn->connect_error : "Connection object not found."));
}

// Fetch all resources from the database, ordered by subject and type
$sql = "SELECT subject, title, type, description, file_path, file_size FROM resources ORDER BY subject, type, title";
$results = $conn->query($sql);

// Create a new array to hold resources grouped by subject
$grouped_resources = [];
if ($results) {
    while ($row = $results->fetch_assoc()) {
        // Assuming file_path in DB is relative to project root (e.g., 'uploads/resources/file.pdf')
        // And BASE_URL is defined in db_config.php
        $file_full_path = rtrim(BASE_URL, '/') . '/' . ltrim($row['file_path'], '/');
        $file_full_path = str_replace('//', '/', $file_full_path); // Remove any double slashes
        $row['file_path_full'] = $file_full_path; // Add a new key for the full URL

        $grouped_resources[$row['subject']][] = $row;
    }
} else {
    // Log or display error if query fails
    error_log("Error fetching resources: " . $conn->error);
    $grouped_resources = [];
}

// Close the database connection at the end of PHP logic
$conn->close();

// Set the current page for the sidebar active state
$currentPage = 'resources';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Library - PSB Learning Hub</title>
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

        .accordion-header { cursor: pointer; transition: background-color 0.3s ease; }
        .accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out; background-color: #fafafa; }
        .accordion-header .accordion-icon { transition: transform 0.4s ease; }
        .accordion-header.active .accordion-icon { transform: rotate(180deg); }
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
                        <div class="mb-6 border-b pb-4">
                            <h2 class="text-2xl font-bold text-gray-800">Resource Library</h2>
                            <p class="text-gray-500 mt-1">Find textbooks, notes, and question banks for your subjects.</p>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if (!empty($grouped_resources)): ?>
                                <?php foreach ($grouped_resources as $subject => $resources): ?>
                                    <div class="accordion-item border rounded-lg overflow-hidden bg-white">
                                        <div class="accordion-header bg-gray-50 hover:bg-gray-100 p-4 flex justify-between items-center">
                                            <h3 class="text-lg font-bold text-theme-black"><?php echo htmlspecialchars($subject); ?></h3>
                                            <i class="fas fa-chevron-down accordion-icon text-theme-red"></i>
                                        </div>
                                        <div class="accordion-content">
                                            <ul class="p-4 space-y-3">
                                                <?php foreach ($resources as $resource): ?>
                                                    <?php
                                                        $icon_class = 'fa-file-alt'; // Default icon
                                                        if (isset($resource['type'])) {
                                                            switch (strtolower($resource['type'])) {
                                                                case 'textbook': $icon_class = 'fa-book-open'; break;
                                                                case 'notes': $icon_class = 'fa-file-signature'; break;
                                                                case 'question bank': $icon_class = 'fa-list-ol'; break;
                                                                case 'video': $icon_class = 'fa-video'; break;
                                                                case 'audio': $icon_class = 'fa-volume-up'; break;
                                                                case 'link': $icon_class = 'fa-link'; break;
                                                            }
                                                        }
                                                    ?>
                                                    <li class="flex items-start md:items-center flex-col md:flex-row p-3 border-l-4 border-gray-200 hover:bg-gray-50 rounded-r-lg">
                                                        <div class="flex items-center flex-grow w-full md:w-auto">
                                                            <i class="fas <?php echo $icon_class; ?> text-theme-red text-2xl w-8 text-center"></i>
                                                            <div class="ml-4">
                                                                <p class="font-semibold text-theme-black"><?php echo htmlspecialchars($resource['title']); ?> <span class="text-xs font-normal text-white bg-theme-red px-2 py-0.5 rounded-full ml-2 align-middle"><?php echo htmlspecialchars(ucfirst($resource['type'])); ?></span></p>
                                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($resource['description']); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center mt-3 md:mt-0 ml-auto md:ml-4 pl-12 md:pl-0 w-full md:w-auto justify-end">
                                                            <span class="text-sm text-gray-500 mr-4 flex-shrink-0"><?php echo htmlspecialchars($resource['file_size']); ?></span>
                                                            <a href="<?php echo htmlspecialchars($resource['file_path_full']); ?>" download class="bg-theme-red hover:bg-theme-dark-red text-white font-semibold py-2 px-4 rounded-lg transition duration-300 whitespace-nowrap flex items-center">
                                                                <i class="fas fa-download mr-2"></i>Download
                                                            </a>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center p-8 bg-gray-50 rounded-lg">
                                    <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
                                    <p class="text-gray-500">No resources have been uploaded yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Accordion Functionality ---
    const accordionItems = document.querySelectorAll('.accordion-item');
    accordionItems.forEach(item => {
        const header = item.querySelector('.accordion-header');
        const content = item.querySelector('.accordion-content');
        header.addEventListener('click', () => {
            // Close other open accordions
            accordionItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.querySelector('.accordion-header').classList.remove('active');
                    otherItem.querySelector('.accordion-content').style.maxHeight = '0';
                }
            });

            // Toggle the clicked accordion
            header.classList.toggle('active');
            if (header.classList.contains('active')) {
                content.style.maxHeight = content.scrollHeight + 'px';
            } else {
                content.style.maxHeight = '0';
            }
        });
    });

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