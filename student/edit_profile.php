<?php
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adjust path as necessary based on your project structure
require_once 'db_config.php';

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established.");
}

// --- Authentication Check ---
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
    $default_image_relative_path = 'admin/assets/images/default_avatar.png';
    $profile_image_path = !empty($user_data['profile_image_path']) ? $user_data['profile_image_path'] : $default_image_relative_path;
    $current_profile_pic_url = rtrim(BASE_URL, '/') . '/' . ltrim($profile_image_path, '/');
    $current_profile_pic_url = str_replace('//', '/', $current_profile_pic_url);

} else {
    $error_message = "User profile not found.";
    $conn->close();
    die($error_message);
}
$stmt_fetch->close();


// Handle form submission for profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_email = trim($_POST['email']);
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_email)) {
        $error_message = "Email cannot be empty.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error_message = "New password and confirm password do not match.";
    } else {
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
            call_user_func_array([$stmt_update, 'bind_param'], array_merge([$params], $param_values));

            if ($stmt_update->execute()) {
                $success_message = "Profile updated successfully!";
                $_SESSION['email'] = $new_email;
                
                $stmt_fetch_updated = $conn->prepare("SELECT username, email, acca_id, profile_image_path FROM users WHERE id = ?");
                $stmt_fetch_updated->bind_param("i", $current_user_id);
                $stmt_fetch_updated->execute();
                $result_fetch_updated = $stmt_fetch_updated->get_result();
                $user_data = $result_fetch_updated->fetch_assoc();
                $stmt_fetch_updated->close();

            } else {
                $error_message = "Error updating profile: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
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
    <title>Edit Profile - PSB Learning Hub</title>
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
                            <h2 class="text-2xl font-bold text-gray-800">Edit Profile</h2>
                            <p class="text-gray-500 mt-1">Update your email address and password.</p>
                        </div>
                        <div class="flex items-center space-x-3 mt-4 sm:mt-0 w-full sm:w-auto">
                            <a href="view_profile.php" class="w-full sm:w-auto bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-arrow-left mr-2"></i> Back to Profile</a>
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
                    <div class="bg-white rounded-xl shadow-custom p-8 fade-in">
                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                            <div class="form-group flex flex-col items-center mb-6">
                                <label class="block text-sm font-medium text-gray-700 text-center mb-2">Current Profile Picture</label>
                                <img src="<?= htmlspecialchars($current_profile_pic_url); ?>" alt="Current Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg mb-4">
                                <p class="text-sm text-gray-500">To change your profile picture, please contact an administrator.</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user_data['username']); ?>" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm p-3 cursor-not-allowed">
                                </div>
                                <div class="form-group">
                                    <label for="acca_id" class="block text-sm font-medium text-gray-700">ACCA ID</label>
                                    <input type="text" id="acca_id" name="acca_id" value="<?= htmlspecialchars($user_data['acca_id'] ?? ''); ?>" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm p-3 cursor-not-allowed">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_data['email']); ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-theme-red focus:border-theme-red p-3">
                            </div>

                            <div class="border-t border-gray-200 pt-6 mt-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Change Password (Optional)</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="form-group">
                                        <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                                        <input type="password" id="password" name="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-theme-red focus:border-theme-red p-3">
                                        <p class="mt-1 text-xs text-gray-500">Leave blank if you don't want to change your password.</p>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-theme-red focus:border-theme-red p-3">
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end mt-6">
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-black bg-theme-red hover:bg-theme-dark-red focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-theme-red transition-colors duration-200">
                                    <i class="fas fa-save mr-2"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                        <div class="bg-white rounded-xl shadow-custom p-8 text-center"><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p class="font-bold">Error!</p><p><?= htmlspecialchars($error_message); ?></p></div></div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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