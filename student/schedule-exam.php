<?php
// --- DEVELOPMENT/DEBUGGING: Display all PHP errors ---
// This will help us see the actual error instead of a generic HTTP 500 page.
// IMPORTANT: REMOVE THESE TWO LINES IN A LIVE PRODUCTION ENVIRONMENT.
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
// Assuming db_config.php is in the same directory
require_once 'db_config.php'; 

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    // Stop the script and display a clear error message
    die("Database Connection Failed: " . (isset($conn) ? $conn->connect_error : "Connection object not found. Check db_config.php."));
}

// --- Authorization Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php'); // Adjust path to your login page
    exit;
}

// --- Data & Variable Initialization ---
$current_user_id = $_SESSION['user_id'];
$form_message = ''; // To display success or error messages
$username = 'Student'; // Default username
$submitted_exam_date = ''; // To retain selected date after submission

// --- Fetch current user's name for auto-generating the title ---
$sql_user_details = "SELECT username FROM users WHERE id = ?";
if ($stmt_user = $conn->prepare($sql_user_details)) {
    $stmt_user->bind_param("i", $current_user_id);
    if ($stmt_user->execute()) {
        $result_user = $stmt_user->get_result();
        if ($user_data = $result_user->fetch_assoc()) {
            $username = htmlspecialchars($user_data['username']);
        }
    } else {
        error_log("Failed to execute user details query in schedule-exam.php: " . $stmt_user->error);
    }
    $stmt_user->close();
} else {
    error_log("Failed to prepare user details query in schedule-exam.php: " . $conn->error);
}


// --- Define Allowed Subjects ---
$foundation_subjects = [ 'FA1' => 'Recording Financial Transactions', 'MA1' => 'Management Information', 'FA2' => 'Maintaining Financial Records', 'MA2' => 'Managing Costs and Finance' ];
$applied_knowledge_subjects = [ 'BT' => 'Business and Technology', 'MA' => 'Management Accounting', 'FA' => 'Financial Accounting' ];
$applied_skills_subjects = [ 'LW' => 'Corporate and Business Law', 'PM' => 'Performance Management', 'TX' => 'Taxation', 'FR' => 'Financial Reporting', 'AA' => 'Audit and Assurance', 'FM' => 'Financial Management' ];
$all_allowed_subjects = $foundation_subjects + $applied_knowledge_subjects + $applied_skills_subjects;
$allowed_time_slots = ['11:00-13:00', '14:00-16:00', '16:00-18:00'];
$time_slot_display_map = [
    '11:00-13:00' => '11:00 AM - 01:00 PM',
    '14:00-16:00' => '02:00 PM - 04:00 PM',
    '16:00-18:00' => '04:00 PM - 06:00 PM'
];


// Initialize slot availability for JavaScript (will be populated by AJAX)
$slot_availability_json = '[]';


// --- Handle Form Submission (Schedule a New Exam) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['schedule_exam'])) {
    // Sanitize and retrieve form data
    $course_name = filter_input(INPUT_POST, 'course_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $exam_date = filter_input(INPUT_POST, 'exam_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $time_slot = filter_input(INPUT_POST, 'time_slot', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $is_valid = true;

    $submitted_exam_date = $exam_date; // Retain date for displaying availability after submission

    // Auto-generate the title using the course code and username
    $title = $course_name . " Exam - " . $username;

    // --- Server-Side Validation ---
    if (empty($course_name) || empty($exam_date) || empty($time_slot)) {
        $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p>All fields are required.</p></div>';
        $is_valid = false;
    }
    elseif (!array_key_exists($course_name, $all_allowed_subjects)) {
        $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p>Invalid paper selected.</p></div>';
        $is_valid = false;
    }
    elseif (!in_array(date('w', strtotime($exam_date)), [1, 3])) {
        $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p>Exams can only be scheduled on Mondays and Wednesdays.</p></div>';
        $is_valid = false;
    }
    elseif (!in_array($time_slot, $allowed_time_slots)) {
        $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p>Invalid time slot selected.</p></div>';
        $is_valid = false;
    }

    // --- Check Slot Capacity (if all previous checks passed) ---
    if ($is_valid) {
        $sql_check = "SELECT COUNT(id) as count FROM exams WHERE exam_date = ? AND time_slot = ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("ss", $exam_date, $time_slot);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $count = $result_check->fetch_assoc()['count'];
            $stmt_check->close();

            if ($count >= 2) {
                // This message is for server-side validation if the slot fills up between page load and submission
                $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p>The selected slot is now fully booked. Please choose another.</p></div>';
                $is_valid = false;
            }
        } else {
            error_log("Failed to prepare slot check query in schedule-exam.php: " . $conn->error);
            $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p>System error: Could not check slot availability.</p></div>';
            $is_valid = false;
        }
    }

    // After form submission (whether valid or not), fetch current slot counts for the submitted date
    // This data will be passed to JS to update the dropdown if there was an error.
    $current_date_slot_counts = [];
    if (!empty($submitted_exam_date)) {
        foreach ($allowed_time_slots as $slot) {
            $sql_count_slot = "SELECT COUNT(id) as count FROM exams WHERE exam_date = ? AND time_slot = ?";
            if ($stmt_count_slot = $conn->prepare($sql_count_slot)) {
                $stmt_count_slot->bind_param("ss", $submitted_exam_date, $slot);
                $stmt_count_slot->execute();
                $result_count_slot = $stmt_count_slot->get_result();
                $current_date_slot_counts[$slot] = $result_count_slot->fetch_assoc()['count'];
                $stmt_count_slot->close();
            } else {
                error_log("Error preparing slot count for JS: " . $conn->error);
            }
        }
        $slot_availability_json = json_encode($current_date_slot_counts);
    }


    // --- Insert into Database (if still valid) ---
    if ($is_valid) {
        $sql_insert = "INSERT INTO exams (title, student_id, exam_date, time_slot, course_name) VALUES (?, ?, ?, ?, ?)";
        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("sisss", $title, $current_user_id, $exam_date, $time_slot, $course_name);
            if ($stmt_insert->execute()) {
                $form_message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><strong>Success!</strong> Your exam has been scheduled.</p></div>';
                // Clear submitted date so dropdown resets after successful booking
                $submitted_exam_date = '';
                $slot_availability_json = '[]'; // Clear availability data
            } else {
                error_log("Failed to insert exam: " . $stmt_insert->error);
                $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><strong>Error!</strong> Could not schedule the exam. Please try again.</p></div>';
            }
            $stmt_insert->close();
        } else {
            error_log("Failed to prepare insert query in schedule-exam.php: " . $conn->error);
            $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p>System error: Could not prepare exam scheduling.</p></div>';
        }
    }
}

// --- Fetch All Scheduled Exams for the Current User ---
$scheduled_exams = [];
$sql_fetch = "SELECT id, title, course_name, exam_date, time_slot, status FROM exams WHERE student_id = ? ORDER BY exam_date ASC, time_slot ASC";
if ($stmt_fetch = $conn->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("i", $current_user_id);
    if ($stmt_fetch->execute()) {
        $result = $stmt_fetch->get_result();
        $scheduled_exams = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Failed to fetch scheduled exams: " . $stmt_fetch->error);
    }
    $stmt_fetch->close();
} else {
    error_log("Failed to prepare fetch scheduled exams query in schedule-exam.php: " . $conn->error);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Exam - PSB Learning Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'theme-red': '#c51a1d',
                        'theme-dark-red': '#a81013',
                        'theme-black': '#1a1a1a',
                        'light-gray': '#f5f7fa'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .solid-header { background: linear-gradient(to right, #c51a1d, #a81013); }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange {
            background: #c51a1d;
            border-color: #c51a1d;
        }
        .flatpickr-day:hover {
            background: #ef4444;
            border-color: #ef4444;
        }
        /* New styles for time slot options */
        .time-slot-option.full {
            color: #dc2626; /* Red color for fully booked */
            text-decoration: line-through;
            opacity: 0.7;
        }
        .time-slot-option.available {
            color: #16a34a; /* Green color for available */
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
            /* Adjust padding for main content on small screens */
            main {
                padding: 1rem; /* Adjust as needed */
            }
        }
    </style>
</head>
<body class="bg-light-gray text-gray-800">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="solid-header text-white shadow-lg">
            <div class="container mx-auto px-6 py-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="text-center md:text-left mb-4 md:mb-0">
                        <h1 class="text-3xl font-bold tracking-tight">PSB Learning Hub</h1>
                        <p class="text-sm opacity-90">Your Pathway to Success</p>
                    </div>
                    <div class="mt-4 md:mt-0 flex items-center space-x-4">
                        <a href="dashboard.php" class="text-white hover:text-gray-200 transition-colors font-medium">
                           <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                        <a href="../logout.php" class="inline-block bg-white text-theme-red font-semibold py-2 px-5 rounded-full shadow-md hover:bg-gray-100 hover:shadow-lg transition-all duration-300 ease-in-out text-sm">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-10">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">

                <!-- Left Column: Form to Schedule Exam -->
                <div class="lg:col-span-2">
                    <div class="bg-white p-8 rounded-xl shadow-md">
                        <div class="text-left mb-6">
                            <i class="fas fa-calendar-plus text-4xl text-theme-red mb-3"></i>
                            <h2 class="text-2xl font-bold text-theme-black">Schedule a New Exam</h2>
                            <p class="text-gray-600 mt-1">Book your official ACCA exam session here.</p>
                        </div>
                        
                        <?php echo $form_message; ?>

                        <form action="schedule-exam.php" method="POST" class="space-y-5">
                            <div>
                                <label for="course_name" class="block text-sm font-medium text-gray-700">Select Paper</label>
                                <select id="course_name" name="course_name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-theme-red focus:border-theme-red p-3">
                                    <option value="" disabled selected>Choose a paper...</option>
                                    <optgroup label="Foundations in Accountancy">
                                        <?php foreach ($foundation_subjects as $code => $name): ?>
                                            <option value="<?php echo $code; ?>"><?php echo $code . ' - ' . $name; ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Applied Knowledge">
                                        <?php foreach ($applied_knowledge_subjects as $code => $name): ?>
                                            <option value="<?php echo $code; ?>"><?php echo $code . ' - ' . $name; ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Applied Skills">
                                        <?php foreach ($applied_skills_subjects as $code => $name): ?>
                                            <option value="<?php echo $code; ?>"><?php echo $code . ' - ' . $name; ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="exam_date" class="block text-sm font-medium text-gray-700">Exam Date</label>
                                    <input type="text" id="exam_date" name="exam_date" value="<?php echo htmlspecialchars($submitted_exam_date); ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-theme-red focus:border-theme-red p-3" placeholder="Select a date...">
                                    <p id="date_error_msg" class="text-red-500 text-xs mt-1 hidden">Exams are only on Mondays and Wednesdays.</p>
                                </div>
                                <div>
                                    <label for="time_slot" class="block text-sm font-medium text-gray-700">Time Slot</label>
                                    <select id="time_slot" name="time_slot" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-theme-red focus:border-theme-red p-3">
                                        <option value="" disabled selected>Select a time...</option>
                                        <?php foreach ($allowed_time_slots as $slot_value): ?>
                                            <option value="<?php echo htmlspecialchars($slot_value); ?>"><?php echo htmlspecialchars($time_slot_display_map[$slot_value]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="text-left pt-3">
                                <button type="submit" name="schedule_exam" class="bg-theme-red hover:bg-theme-dark-red text-white font-bold py-3 px-8 rounded-lg transition duration-300 ease-in-out transform hover:scale-105">
                                    <i class="fas fa-check-circle mr-2"></i> Confirm Booking
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column: List of Scheduled Exams -->
                <div class="lg:col-span-3">
                    <div class="bg-white p-8 rounded-xl shadow-md">
                        <h2 class="text-2xl font-bold text-theme-black mb-6">Your Scheduled Exams</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse responsive-table">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="p-3 text-left text-theme-black font-medium">Title</th>
                                        <th class="p-3 text-left text-theme-black font-medium">Paper</th>
                                        <th class="p-3 text-left text-theme-black font-medium">Date</th>
                                        <th class="p-3 text-left text-theme-black font-medium">Time</th>
                                        <th class="p-3 text-center text-theme-black font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($scheduled_exams)): ?>
                                        <?php foreach ($scheduled_exams as $exam): ?>
                                            <?php
                                                $status_class = 'bg-gray-200 text-gray-800'; // Default
                                                if ($exam['status'] === 'Scheduled') {
                                                    $status_class = 'bg-blue-100 text-blue-800';
                                                } elseif ($exam['status'] === 'Completed') {
                                                    $status_class = 'bg-green-100 text-green-800';
                                                } elseif ($exam['status'] === 'Cancelled') {
                                                    $status_class = 'bg-red-100 text-red-800';
                                                }
                                            ?>
                                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                <td class="p-3 font-medium text-theme-black" data-label="Title"><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td class="p-3 text-gray-600" data-label="Paper"><?php echo htmlspecialchars($exam['course_name']); ?></td>
                                                <td class="p-3 text-gray-600" data-label="Date"><?php echo date('D, d M Y', strtotime($exam['exam_date'])); ?></td>
                                                <td class="p-3 text-gray-600" data-label="Time"><?php echo htmlspecialchars($exam['time_slot']); ?></td>
                                                <td class="p-3 text-center" data-label="Status">
                                                    <span class="px-3 py-1 text-xs font-semibold <?php echo $status_class; ?> rounded-full"><?php echo htmlspecialchars($exam['status']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="p-4 text-center text-gray-500">You have no exams scheduled.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-theme-black text-white py-6 mt-10">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; <?php echo date('Y'); ?> Learning Hub. All rights reserved.</p>
            </div>
        </footer>
    </div>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const timeSlotSelect = document.getElementById('time_slot');
            const examDateInput = document.getElementById('exam_date');
            const dateErrorMsg = document.getElementById('date_error_msg');
            const submitButton = document.getElementById('submit_button');

            // PHP-generated data for initial slot availability (after form submission with errors)
            const initialSlotAvailability = <?php echo $slot_availability_json; ?>;

            // Function to update time slot options based on availability data
            function updateTimeSlotOptions(slotCounts) {
                // Get original text from the PHP loop to reconstruct options
                const phpAllowedTimeSlots = <?php echo json_encode($allowed_time_slots); ?>;
                const phpTimeSlotDisplayMap = <?php echo json_encode($time_slot_display_map); ?>;

                // Clear current options before re-populating
                timeSlotSelect.innerHTML = '<option value="" disabled selected>Select a time...</option>';

                // Re-add options with availability status
                phpAllowedTimeSlots.forEach(slotValue => {
                    const displayValue = phpTimeSlotDisplayMap[slotValue];
                    let optionText = displayValue;
                    let isDisabled = false;
                    
                    if (slotCounts[slotValue] !== undefined) {
                        const count = slotCounts[slotValue];
                        const remaining = 2 - count; // Assuming max 2 slots
                        if (remaining <= 0) {
                            optionText += ' (Fully Booked)';
                            isDisabled = true;
                        } else {
                            optionText += ` (${remaining} slots left)`;
                        }
                    } else {
                        // If no count data, assume 2 slots left
                        optionText += ' (2 slots left)';
                    }

                    const option = document.createElement('option');
                    option.value = slotValue;
                    option.textContent = optionText;
                    option.disabled = isDisabled;
                    // Add a class for styling based on availability
                    if (isDisabled) {
                        option.classList.add('time-slot-option', 'full');
                    } else {
                        option.classList.add('time-slot-option', 'available');
                    }
                    timeSlotSelect.appendChild(option);
                });
            }

            // Initialize Flatpickr
            const flatpickrInstance = flatpickr("#exam_date", {
                dateFormat: "Y-m-d", 
                minDate: "today",
                disable: [
                    function(date) {
                        return (date.getDay() !== 1 && date.getDay() !== 3); // Disable all days except Monday (1) and Wednesday (3)
                    }
                ],
                onChange: function(selectedDates, dateStr, instance) {
                    // When date changes, fetch new slot availability via AJAX
                    if (dateStr) {
                        fetchSlotAvailability(dateStr);
                        validateDate(); // Re-validate date on change
                    } else {
                        // If date is cleared, reset time slot options and validation
                        updateTimeSlotOptions({}); // Pass empty object to show all as available
                        timeSlotSelect.value = "";
                        dateErrorMsg.classList.add('hidden');
                        examDateInput.classList.remove('date-error');
                        submitButton.disabled = false;
                        timeSlotSelect.disabled = false;
                    }
                }
            });

            // Function to fetch slot availability via AJAX
            async function fetchSlotAvailability(date) {
                try {
                    const response = await fetch('fetch_slot_availability.php?date=' + date);
                    const slotCounts = await response.json();
                    updateTimeSlotOptions(slotCounts);
                } catch (error) {
                    console.error('Error fetching slot availability:', error);
                    // On error, revert to showing all slots as potentially available
                    updateTimeSlotOptions({}); 
                }
            }

            // Function to validate date (Monday/Wednesday only) and enable/disable elements
            function validateDate() {
                if (!examDateInput.value) {
                    dateErrorMsg.classList.add('hidden');
                    examDateInput.classList.remove('date-error');
                    submitButton.disabled = true; // Disable submit if no date
                    timeSlotSelect.disabled = true; // Disable time slot if no date
                    return;
                }
                const selectedDate = new Date(examDateInput.value);
                const day = selectedDate.getUTCDay(); // getUTCDay() returns 0 for Sunday, 1 for Monday...

                const isInvalid = day !== 1 && day !== 3; // Monday (1) and Wednesday (3)
                dateErrorMsg.classList.toggle('hidden', !isInvalid);
                examDateInput.classList.toggle('date-error', isInvalid);
                
                // Disable submit button and time slot if date is invalid
                submitButton.disabled = isInvalid;
                timeSlotSelect.disabled = isInvalid;

                if (!isInvalid) {
                    fetchSlotAvailability(examDateInput.value); // Fetch slots if date is valid
                } else {
                    updateTimeSlotOptions({}); // Reset time slots if date is invalid
                    timeSlotSelect.value = "";
                }
            }

            // Initial checks on page load
            validateDate(); // Call to set initial state based on default date or submitted date
            if (examDateInput.value) {
                fetchSlotAvailability(examDateInput.value);
            }
        });
    </script>
</body>
</html>
