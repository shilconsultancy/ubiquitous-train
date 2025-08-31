<?php
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adjust the path to db_config.php if needed.
require_once 'db_config.php';

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established: " . (isset($conn) ? $conn->connect_error : "Connection object not found."));
}

// --- PHPMailer Integration ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// This assumes the 'phpmailer' directory is inside the 'student' directory.
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';


// --- Authorization Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php'); // Adjust path to your login page
    exit;
}

// Initialize variables
$current_user_id = $_SESSION['user_id'];
$username = '';
$email = '';
$form_message = ''; // To display success or error messages

// --- Fetch User Details to Pre-fill Form ---
$sql_user_details = "SELECT username, email FROM users WHERE id = ?";
if ($stmt_details = $conn->prepare($sql_user_details)) {
    $stmt_details->bind_param("i", $current_user_id);
    if ($stmt_details->execute()) {
        $result_details = $stmt_details->get_result();
        if ($user_data = $result_details->fetch_assoc()) {
            $username = htmlspecialchars($user_data['username']);
            $email = htmlspecialchars($user_data['email']);
        } else {
            // User data not found
            session_destroy();
            header('Location: ../index.php');
            exit;
        }
    } else {
        error_log("Failed to execute user details query in support.php: " . $stmt_details->error);
        $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p>Error fetching your details. Please try again later.</p></div>';
    }
    $stmt_details->close();
} else {
    error_log("Failed to prepare user details query in support.php: " . $conn->error);
    $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p>System error: Could not prepare user data retrieval.</p></div>';
}


// --- Form Submission Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and Validate Inputs
    $support_subject = filter_input(INPUT_POST, 'support_subject', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $support_message = filter_input(INPUT_POST, 'support_message', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $from_email = filter_var($email, FILTER_SANITIZE_EMAIL); // Use the logged-in user's email
    $is_valid = true;

    if (empty($support_subject) || empty($support_message)) {
        $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p>Please fill out all fields.</p></div>';
        $is_valid = false;
    }

    // File Upload Handling
    $attachment_path = null;
    $attachment_name = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['attachment']['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p>Invalid file type. Only JPG, PNG, and GIF are allowed.</p></div>';
            $is_valid = false;
        } elseif ($_FILES['attachment']['size'] > 5 * 1024 * 1024) { // 5 MB limit
            $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p>File is too large. Maximum size is 5MB.</p></div>';
            $is_valid = false;
        } else {
            $attachment_path = $_FILES['attachment']['tmp_name'];
            $attachment_name = basename($_FILES['attachment']['name']);
        }
    }

    if ($is_valid) {
        $mail = new PHPMailer(true);

        try {
            // Server Settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'support@shilconsultancy.com';
            $mail->Password   = '5QY+!0#e[n'; // IMPORTANT: Use a secure method to store passwords
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // Recipients
            $mail->setFrom('support@shilconsultancy.com', 'PSB Learning Hub Support');
            $mail->addAddress('support@shilconsultancy.com', 'Support Team');
            $mail->addReplyTo($from_email, $username);

            // Attachments
            if ($attachment_path) {
                $mail->addAttachment($attachment_path, $attachment_name);
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Support Request: " . $support_subject;
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>New Support Request</h2>
                    <p>You have received a new support request from the PSB Learning Hub.</p>
                    <hr>
                    <p><strong>From:</strong> {$username}</p>
                    <p><strong>ACCA ID:</strong> " . ($_SESSION['acca_id'] ?? 'N/A') . "</p>
                    <p><strong>Email:</strong> {$from_email}</p>
                    <p><strong>Subject:</strong> {$support_subject}</p>
                    <h3>Message:</h3>
                    <div style='background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px;'>
                        " . nl2br(htmlspecialchars($support_message)) . "
                    </div>
                </div>";
            $mail->AltBody = "New Support Request\n\nFrom: {$username} ({$from_email})\nSubject: {$support_subject}\n\nMessage:\n{$support_message}";

            $mail->send();
            $form_message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert"><p><strong>Success!</strong> Your message has been sent. We will get back to you shortly.</p></div>';
        } catch (Exception $e) {
            $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p><strong>Error!</strong> Message could not be sent. Mailer Error: ' . htmlspecialchars($mail->ErrorInfo) . '</p></div>';
        }
    }
}
$conn->close();

// Set the current page for the sidebar active state
$currentPage = 'support';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - PSB Learning Hub</title>
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
                    <div class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-sm">
                        <div class="text-center mb-8">
                            <i class="fas fa-headset text-5xl text-theme-red mb-4"></i>
                            <h2 class="text-3xl font-bold text-gray-800">Get Support</h2>
                            <p class="text-gray-600 mt-2">Have a question or facing a technical issue? Fill out the form below, and our team will get back to you as soon as possible.</p>
                        </div>

                        <!-- Contact Form -->
                        <form action="support.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            
                            <!-- This div will display the success or error message from the PHP script -->
                            <?php if (!empty($form_message)): ?>
                                <div class="mb-6"><?php echo $form_message; ?></div>
                            <?php endif; ?>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Name Field (Read-only) -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700">Your Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo $username; ?>" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm p-3 cursor-not-allowed">
                                </div>
                                <!-- Email Field (Read-only) -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Your Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo $email; ?>" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm p-3 cursor-not-allowed">
                                </div>
                            </div>

                            <!-- Subject Field -->
                            <div>
                                <label for="support_subject" class="block text-sm font-medium text-gray-700">Subject</label>
                                <input type="text" id="support_subject" name="support_subject" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-theme-red focus:border-theme-red p-3" placeholder="e.g., Issue with exam review">
                            </div>

                            <!-- Message Field -->
                            <div>
                                <label for="support_message" class="block text-sm font-medium text-gray-700">Message</label>
                                <textarea id="support_message" name="support_message" rows="6" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-theme-red focus:border-theme-red p-3" placeholder="Please describe your issue in detail..."></textarea>
                            </div>
                            
                            <!-- Attachment Field -->
                            <div>
                                <label for="attachment" class="block text-sm font-medium text-gray-700">Attach an Image (Optional)</label>
                                <input type="file" id="attachment" name="attachment" accept="image/png, image/jpeg, image/gif" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-theme-red hover:file:bg-red-100">
                                <p class="mt-1 text-xs text-gray-500">Max file size: 5MB. Allowed types: JPG, PNG, GIF.</p>
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center pt-4">
                                <button type="submit" class="w-full md:w-auto bg-theme-red hover:bg-theme-dark-red text-white font-bold py-3 px-10 rounded-lg transition duration-300 ease-in-out transform hover:scale-105">
                                    <i class="fas fa-paper-plane mr-2"></i> Send Message
                                </button>
                            </div>
                        </form>
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