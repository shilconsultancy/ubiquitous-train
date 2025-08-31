<?php
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adjust the path to db_config.php if needed.
// Assuming db_config.php is in the same directory as support.php
require_once 'db_config.php';

// --- Database Connection Validation ---
// FIX: Using $conn as defined in db_config.php
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established: " . (isset($conn) ? $conn->connect_error : "Connection object not found."));
}

// --- PHPMailer Integration ---
// Import the PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- CORRECTED PATHS ---
// Require the PHPMailer files from the 'phpmailer/src' directory.
// This assumes PHPMailer is located in a 'phpmailer' folder relative to your project root.
// So, from 'student/support.php', it's '../../phpmailer/src/Exception.php'
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';


// --- Authorization Check ---
// Redirect user to login page if they are not logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) { // FIX: Check for 'user_id'
    header('Location: ../index.php'); // Adjust path to your login page
    exit;
}

// Initialize variables
$current_user_id = $_SESSION['user_id']; // FIX: Use 'user_id'
$username = '';
$email = '';
$form_message = ''; // To display success or error messages

// --- Fetch User Details to Pre-fill Form ---
// We need the user's email for the form
$sql_user_details = "SELECT username, email FROM users WHERE id = ?";
// FIX: Use $conn for database operations
if ($stmt_details = $conn->prepare($sql_user_details)) {
    $stmt_details->bind_param("i", $current_user_id);
    if ($stmt_details->execute()) {
        $result_details = $stmt_details->get_result();
        if ($user_data = $result_details->fetch_assoc()) {
            $username = htmlspecialchars($user_data['username']);
            $email = htmlspecialchars($user_data['email']);
        } else {
            // User data not found, possibly a corrupted session or deleted user
            $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p>Error: Your user data could not be retrieved. Please try logging in again.</p></div>';
            // Optionally, destroy session and redirect to login
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
    // --- Sanitize and Validate Inputs ---
    $support_subject = filter_input(INPUT_POST, 'support_subject', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $support_message = filter_input(INPUT_POST, 'support_message', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $from_email = filter_var($email, FILTER_SANITIZE_EMAIL); // Use the logged-in user's email
    $is_valid = true;

    // Basic validation
    if (empty($support_subject) || empty($support_message)) {
        $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p>Please fill out all fields.</p></div>';
        $is_valid = false;
    } elseif (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        $form_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p>Invalid email format.</p></div>';
        $is_valid = false;
    }

    // --- File Upload Handling & Sanitization ---
    $attachment_path = null;
    $attachment_name = null; // Initialize to null
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
        // --- Send Email using PHPMailer ---
        $mail = new PHPMailer(true); // Passing `true` enables exceptions

        try {
            // --- Server Settings (Updated with your details) ---
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'support@shilconsultancy.com';
            $mail->Password   = '[cvgc|Hf7'; // IMPORTANT: Consider using environment variables for passwords
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // --- Recipients ---
            $mail->setFrom('support@shilconsultancy.com', 'PSB Learning Hub Support');
            $mail->addAddress('support@shilconsultancy.com', 'Support Team');
            $mail->addReplyTo($from_email, $username);

            // --- Attachments ---
            if ($attachment_path) {
                $mail->addAttachment($attachment_path, $attachment_name);
            }

            // --- Content ---
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
// FIX: Close the database connection at the end of the script
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - PSB Learning Hub</title>
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
            <div class="max-w-3xl mx-auto bg-white p-8 rounded-xl shadow-md">
                <div class="text-center mb-8">
                    <i class="fas fa-headset text-5xl text-theme-red mb-4"></i>
                    <h2 class="text-3xl font-bold text-theme-black">Get Support</h2>
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
        </main>

        <!-- Footer -->
        <footer class="bg-theme-black text-white py-6 mt-10">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; <?php echo date('Y'); ?> Learning Hub. All rights reserved.</p>
            </div>
        </footer>
    </div>
</body>
</html>
