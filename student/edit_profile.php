<?php
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adjust path as necessary based on your project structure
// Correctly requiring db_config.php from the same directory
require_once 'db_config.php';

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established.");
}

// --- Authentication Check ---
// Ensure only logged-in users can access this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id']) || $_SESSION['user_id'] === 0 || !isset($_SESSION['role'])) {
    header('Location: ../index.php'); // Redirect to login page if not properly authenticated
    exit;
}

$current_user_id = $_SESSION['user_id'];
$user_data = null;
$error_message = '';
$success_message = '';

// Fetch user data for pre-filling the form and displaying read-only info
$stmt_fetch = $conn->prepare("SELECT username, email, acca_id, profile_image_path FROM users WHERE id = ?");
$stmt_fetch->bind_param("i", $current_user_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch->num_rows === 1) {
    $user_data = $result_fetch->fetch_assoc();
    // Default image path updated to admin/assets/images/default_avatar.png
    // The profile image path is fetched for display in the header/etc., but not editable here.
    $profile_image_path = !empty($user_data['profile_image_path']) ? $user_data['profile_image_path'] : 'admin/assets/images/default_avatar.png';
    $current_profile_pic_url = rtrim(BASE_URL, '/') . '/' . ltrim($profile_image_path, '/');
    $current_profile_pic_url = str_replace('//', '/', $current_profile_pic_url); // Remove any double slashes

} else {
    $error_message = "User profile not found.";
    // Close connection and exit if user data cannot be fetched
    $conn->close();
    die($error_message);
}
$stmt_fetch->close();


// Handle form submission for profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Only fetch editable fields: email and password
    $new_email = trim($_POST['email']);
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation for editable fields
    if (empty($new_email)) {
        $error_message = "Email cannot be empty.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error_message = "New password and confirm password do not match.";
    } else {
        // Check for duplicate email (excluding current user)
        $duplicate_email_found = false;
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $new_email, $current_user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $duplicate_email_found = true;
            $error_message = "This email address is already in use by another account.";
        }
        $check_stmt->close();

        if (!$duplicate_email_found) {
            // Prepare update query for only email and potentially password
            $sql_update = "UPDATE users SET email = ?";
            $params = "s";
            $param_values = [&$new_email];

            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql_update .= ", password_hash = ?";
                $params .= "s";
                $param_values[] = &$hashed_password;
            }

            $sql_update .= " WHERE id = ?";
            $params .= "i";
            $param_values[] = &$current_user_id;

            $stmt_update = $conn->prepare($sql_update);
            // Use call_user_func_array to bind parameters dynamically
            call_user_func_array([$stmt_update, 'bind_param'], array_merge([$params], $param_values));

            if ($stmt_update->execute()) {
                $success_message = "Profile updated successfully!";
                // Update session variables only for changed fields
                $_SESSION['email'] = $new_email;
                
                // Re-fetch user data to ensure all displayed fields are up-to-date
                // (Though only email is mutable by this form, it's good practice)
                $stmt_fetch_updated = $conn->prepare("SELECT username, email, acca_id, profile_image_path FROM users WHERE id = ?");
                $stmt_fetch_updated->bind_param("i", $current_user_id);
                $stmt_fetch_updated->execute();
                $result_fetch_updated = $stmt_fetch_updated->get_result();
                $user_data = $result_fetch_updated->fetch_assoc();
                $stmt_fetch_updated->close();

                // current_profile_pic_url remains as fetched, not changed by this form
            } else {
                $error_message = "Error updating profile: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - PSB Learning Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'theme-red': '#c51a1d', 'theme-dark-red': '#a81013', 'theme-black': '#1a1a1a', 'light-gray': '#f5f7fa',
                        primary: '#c51a1d', // Using theme-red as primary for consistency
                        secondary: '#a81013', // Using theme-dark-red as secondary
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
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .shadow-custom { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        /* New styles for a more professional look */
        .form-group {
            margin-bottom: 1.5rem; /* More spacing between form groups */
        }
        .form-label {
            display: block;
            font-weight: 600; /* Semi-bold labels */
            color: #334155; /* Slate-700 for labels */
            margin-bottom: 0.5rem;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem; /* Increased padding for better touch targets */
            border: 1px solid #d1d5db; /* Gray-300 border */
            border-radius: 0.5rem; /* More rounded corners */
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); /* Subtle inner shadow */
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #c51a1d; /* Primary color on focus */
            box-shadow: 0 0 0 3px rgba(197,26,29,0.2); /* Ring effect on focus */
        }
        /* Style for read-only/disabled inputs */
        .form-input[readonly], .form-input[disabled] {
            background-color: #e2e8f0; /* Gray-200 background */
            cursor: not-allowed;
            color: #64748b; /* Slate-500 text */
            box-shadow: none; /* Remove shadow */
        }

        .submit-button {
            padding: 0.75rem 1.5rem; /* Larger button */
            font-size: 1rem;
            border-radius: 0.5rem; /* More rounded */
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); /* Subtle button shadow */
            transition: all 0.2s ease-in-out;
        }
        .submit-button:hover {
            transform: translateY(-2px); /* Lift effect on hover */
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        /* Responsive adjustments */
        @media (max-width: 767px) {
            main { padding: 1rem; } 
            .flex-col.sm\:flex-row { flex-direction: column; }
            .sm\:w-auto { width: 100%; }
            .sm\:mt-0 { margin-top: 1rem; }
            .space-x-3 > *:not(:first-child) { margin-left: 0.75rem; }
            .grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
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
                        <p class="text-sm text-gray-200 mt-1">ACCA ID: <span class="font-medium"><?php echo htmlspecialchars($_SESSION['acca_id'] ?? 'N/A'); ?></span></p>
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
                    <p class="text-sm text-slate-500 mt-1">ACCA ID: <span class="font-medium"><?php echo htmlspecialchars($_SESSION['acca_id'] ?? 'N/A'); ?></span></p>
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
        <main class="flex-1 container mx-auto px-4 sm:px-6 py-6 pb-24 md:pb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Edit Profile</h2>
                    <p class="text-gray-500 mt-1">Update your email address and password.</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 sm:mt-0 w-full sm:w-auto">
                    <a href="view_profile.php" class="w-1/2 sm:w-auto bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-arrow-left mr-2"></i> Back to Profile</a>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                    <p><?= htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                    <p><?= htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($user_data): ?>
            <div class="bg-white rounded-xl shadow-custom p-8 fade-in"> <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="form-group flex flex-col items-center mb-6">
                        <label class="form-label text-center">Current Profile Picture</label>
                        <img src="<?= htmlspecialchars($current_profile_pic_url); ?>" alt="Current Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg mb-4">
                        <p class="text-sm text-gray-500">To change your profile picture, please contact an administrator.</p>
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($user_data['username']); ?>" readonly class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_data['email']); ?>" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="acca_id" class="form-label">ACCA ID</label>
                        <input type="text" id="acca_id" name="acca_id" value="<?= htmlspecialchars($user_data['acca_id'] ?? ''); ?>" readonly class="form-input">
                    </div>

                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Change Password (Optional)</h3>
                        <div class="form-group">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" id="password" name="password" class="form-input">
                            <p class="mt-1 text-xs text-gray-500">Leave blank if you don't want to change your password.</p>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input">
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" class="submit-button inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-custom p-8 text-center"><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p class="font-bold">Error!</p><p><?= htmlspecialchars($error_message); ?></p></div></div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Mobile Menu Script (re-used from dashboard) ---
            const menuButton = document.getElementById('mobile-menu-button');
            const closeButton = document.getElementById('mobile-menu-close-button');
            const mobileMenu = document.getElementById('mobile-menu');

            if (menuButton && closeButton && mobileMenu) {
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
            }

            // Removed custom file input trigger as the functionality is no longer needed.
        });
    </script>
</body>
</html>