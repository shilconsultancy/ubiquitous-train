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
    // Close connection and stop script if user not found
    $conn->close();
    die($error_message);
}
$stmt_fetch->close();


// Handle form submission for profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- 1. Image Upload Handling ---
    $profile_image_path = $user_data['profile_image_path']; // Start with the existing path
    $upload_error = false;

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profile_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $error_message = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            $upload_error = true;
        } elseif ($_FILES['profile_image']['size'] > $max_size) {
            $error_message = "File is too large. Maximum size is 5 MB.";
            $upload_error = true;
        } else {
            $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $current_user_id . '_' . uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                // If there's an old image and it's not the default one, delete it
                $default_avatar_path = 'admin/assets/images/default_avatar.png';
                if (!empty($profile_image_path) && $profile_image_path !== $default_avatar_path && file_exists('../' . $profile_image_path)) {
                    unlink('../' . $profile_image_path);
                }
                // The new path to be stored in the DB (relative to project root)
                $profile_image_path = 'uploads/profile_pictures/' . $new_filename;
            } else {
                $error_message = "Failed to move uploaded file.";
                $upload_error = true;
            }
        }
    }

    // --- 2. Text Fields and Password Update Logic ---
    if (!$upload_error) {
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
            // Check for duplicate email only if it has changed
            if ($new_email !== $user_data['email']) {
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_stmt->bind_param("si", $new_email, $current_user_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $error_message = "This email address is already in use by another account.";
                }
                $check_stmt->close();
            }

            // If no errors so far, proceed with database update
            if (empty($error_message)) {
                $params = [];
                $types = "";
                $sql_update = "UPDATE users SET email = ?, profile_image_path = ?";
                $params = [$new_email, $profile_image_path];
                $types = "ss";

                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql_update .= ", password_hash = ?";
                    $params[] = $hashed_password;
                    $types .= "s";
                }

                $sql_update .= " WHERE id = ?";
                $params[] = $current_user_id;
                $types .= "i";

                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param($types, ...$params);

                if ($stmt_update->execute()) {
                    $success_message = "Profile updated successfully!";
                    $_SESSION['email'] = $new_email; // Update session email
                    
                    // Re-fetch data to show the latest info on the page
                    $stmt_refetch = $conn->prepare("SELECT username, email, acca_id, profile_image_path FROM users WHERE id = ?");
                    $stmt_refetch->bind_param("i", $current_user_id);
                    $stmt_refetch->execute();
                    $user_data = $stmt_refetch->get_result()->fetch_assoc();
                    $stmt_refetch->close();

                    // Update the image URL after successful upload
                    $current_profile_pic_url = rtrim(BASE_URL, '/') . '/' . ltrim($user_data['profile_image_path'], '/');

                } else {
                    $error_message = "Error updating profile: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
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

        <div class="flex-1 flex flex-col overflow-hidden">
            <main class="flex-1 overflow-y-auto p-6">
                <div class="container mx-auto">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Edit Profile</h2>
                            <p class="text-gray-500 mt-1">Update your email, password, and profile picture.</p>
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
                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                            <div class="form-group flex flex-col items-center mb-6">
                                <label for="profile_image" class="block text-sm font-medium text-gray-700 text-center mb-2">Profile Picture</label>
                                <img id="imagePreview" src="<?= htmlspecialchars($current_profile_pic_url); ?>" alt="Profile Picture Preview" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg mb-4">
                                <input type="file" id="profile_image" name="profile_image" accept="image/png, image/jpeg, image/gif" class="block w-full max-w-xs text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-theme-red hover:file:bg-red-100">
                                <p class="text-xs text-gray-500 mt-2">Max 5MB. JPG, PNG, or GIF.</p>
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

                            <div class="form-group mt-6">
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
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-theme-red hover:bg-theme-dark-red focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-theme-red transition-colors duration-200">
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

            // Image preview script
            const imageInput = document.getElementById('profile_image');
            const imagePreview = document.getElementById('imagePreview');
            
            if (imageInput) {
                imageInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>
</body>
</html>