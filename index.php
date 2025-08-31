<?php
// ALWAYS start a session at the very top of the page.
session_start();

// If the user is already logged in, redirect them to their respective dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SESSION['role'] === 'student') {
        header('Location: student/dashboard.php');
        exit;
    } // MODIFIED: Reverted to 'super_admin' as per user request
    elseif ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') {
        header('Location: admin/dashboard.php');
        exit;
    }
}

// --- CRITICAL FIX: Check if the database connection file exists before including it ---
if (!file_exists('db_connect.php')) {
    die("FATAL ERROR: db_connect.php not found. Please ensure the database connection file exists in the correct directory.");
}
require_once 'db_connect.php';

$login_error = '';
$active_tab = 'student'; // Default active tab

// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username_input = trim($_POST['username_input']); // This will be either email or acca_id
    $password = $_POST['password'];
    $selected_role_tab = $_POST['role']; // Get the role from the hidden input field

    // Set the active tab based on the submitted role for persistence after an error
    $active_tab = ($selected_role_tab === 'admin') ? 'admin' : 'student';

    if (empty($username_input) || empty($password)) {
        $login_error = 'Please enter your credentials.';
    } else {
        $stmt = null;
        $query_field = '';
        // $sql_query_to_debug = ''; // Removed debugging line

        // Determine which field to query based on the selected role tab
        if ($selected_role_tab === 'student') {
            $query_field = 'acca_id';
            $sql_query_to_debug = "SELECT id, username, email, password_hash, role, acca_id FROM users WHERE acca_id = ? AND role = 'student'";
            $stmt = $conn->prepare($sql_query_to_debug);
            $stmt->bind_param("s", $username_input);
        } elseif ($selected_role_tab === 'admin') {
            $query_field = 'email';
            // MODIFIED: Reverted to 'super_admin' as per user request in SQL query
            // Prepare statement for admins AND super_admins
            $sql_query_to_debug = "SELECT id, username, email, password_hash, role, acca_id FROM users WHERE email = ? AND (role = 'admin' OR role = 'super_admin')";
            $stmt = $conn->prepare($sql_query_to_debug);
            $stmt->bind_param("s", $username_input);
        } else {
            $login_error = 'Invalid role selected.';
        }

        if ($stmt) {
            // echo "Attempting query: " . htmlspecialchars($sql_query_to_debug) . " with value '" . htmlspecialchars($username_input) . "'<br>"; // Removed debugging line

            $stmt->execute();
            $result = $stmt->get_result();

            // echo "Rows returned: " . $result->num_rows . "<br>"; // Removed debugging line
            // if ($result->num_rows > 0) { // Removed debugging block
            //     $user_data_fetched = $result->fetch_assoc();
            //     echo "Fetched User Data (before password check):<pre>";
            //     var_dump($user_data_fetched);
            //     echo "</pre>";
            //     $result->data_seek(0);
            // }

            // Check if a user with that identifier and role exists
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc(); // Fetch again as pointer might have moved from debug var_dump

                // Verify the password against the stored hash
                if (password_verify($password, $user['password_hash'])) {
                    // Password is correct, so start a new session
                    session_regenerate_id(); // Regenerate session ID for security
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id']; // IMPORTANT: Set user_id for other pages
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role']; // This will correctly be 'super_admin' if that's in DB

                    // Store acca_id in session for students if applicable
                    if ($user['role'] === 'student') {
                        $_SESSION['acca_id'] = $user['acca_id'];
                    }

                    // Redirect user based on their role
                    switch ($user['role']) {
                        case 'student':
                            header("Location: student/dashboard.php");
                            break;
                        case 'admin':
                        // MODIFIED: Reverted to 'super_admin' as per user request in switch case
                        case 'super_admin':
                            header("Location: admin/dashboard.php");
                            break;
                        default:
                            header("Location: index.php"); // Fallback
                    }
                    exit; // Important: stop the script after redirection

                } else {
                    // Incorrect password
                    $login_error = 'The password you entered is not valid.';
                }
            } else {
                // No user found with that identifier or incorrect role for identifier
                $login_error = 'No account found with that ' . $query_field . '.';
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PSB Exam Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* You can add custom fonts or other base styles here if needed */
    </style>
</head>
<body class="bg-gray-100">

    <div class="min-h-screen flex items-center justify-center p-4 lg:p-16">
        <div class="w-full max-w-6xl mx-auto">
            <div class="bg-white rounded-2xl shadow-2xl flex flex-col lg:flex-row overflow-hidden">
                
                <div class="w-full lg:w-1/2 hidden lg:block">
                    <img src="https://placehold.co/1000x1200/e0e7ff/3730a3?text=PSB+Portal\n\nWelcome+Back" 
                         alt="PSB Portal Welcome Image" 
                         class="w-full h-full object-cover">
                </div>

                <div class="w-full lg:w-1/2 p-8 sm:p-12 flex flex-col justify-center">
                    <div>
                        <div class="text-center mb-8">
                            <h1 class="text-3xl font-bold text-gray-900">Welcome Back!</h1>
                            <p class="text-gray-500 mt-2">Please log in to access your account.</p>
                        </div>

                        <?php if (!empty($login_error)): ?>
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                                <p><?= htmlspecialchars($login_error); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="mb-6">
                            <div class="flex border-b border-gray-200">
                                <button class="tab-button flex-1 py-3 text-sm font-semibold text-center transition-colors duration-300 <?= ($active_tab === 'student' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-500 hover:text-gray-700'); ?>" onclick="showTab('student', event)">
                                    <i class="fas fa-user-graduate mr-2"></i> Student
                                </button>
                                <button class="tab-button flex-1 py-3 text-sm font-semibold text-center transition-colors duration-300 <?= ($active_tab === 'admin' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-500 hover:text-gray-700'); ?>" onclick="showTab('admin', event)">
                                    <i class="fas fa-user-shield mr-2"></i> Admin
                                
                                </button>
                            </div>
                        </div>

                        <div id="studentLogin" class="tab-content <?= ($active_tab === 'student' ? '' : 'hidden'); ?>">
                            <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
                                <input type="hidden" name="role" value="student">
                                <div>
                                    <label for="student_acca_id" class="block text-sm font-medium text-gray-700">ACCA ID</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <i class="fas fa-id-card text-gray-400"></i>
                                        </div>
                                        <input type="text" id="student_acca_id" name="username_input" required class="block w-full rounded-md border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-3">
                                    </div>
                                </div>
                                <div>
                                    <label for="student_password" class="block text-sm font-medium text-gray-700">Password</label>
                                     <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                        <input type="password" id="student_password" name="password" required class="block w-full rounded-md border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-3">
                                    </div>
                                </div>
                                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus::ring-offset-2 focus:ring-indigo-500 transition-transform duration-200 hover:scale-105">
                                    Login as Student
                                </button>
                            </form>
                        </div>

                        <div id="adminLogin" class="tab-content <?= ($active_tab === 'admin' ? '' : 'hidden'); ?>">
                            <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
                                <input type="hidden" name="role" value="admin">
                                <div>
                                    <label for="admin_email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <i class="fas fa-envelope text-gray-400"></i>
                                        </div>
                                        <input type="email" id="admin_email" name="username_input" required class="block w-full rounded-md border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-3">
                                    </div>
                                </div>
                                <div>
                                    <label for="admin_password" class="block text-sm font-medium text-gray-700">Password</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                        <input type="password" id="admin_password" name="password" required class="block w-full rounded-md border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-3">
                                    </div>
                                </div>
                                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus::ring-offset-2 focus:ring-indigo-500 transition-transform duration-200 hover:scale-105">
                                    Login as Admin
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId, event) {
            // Hide all tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('text-indigo-600', 'border-indigo-600');
                button.classList.add('text-gray-500', 'hover:text-gray-700');
            });
            // Show the selected tab content
            document.getElementById(tabId + 'Login').classList.remove('hidden');
            // Activate the selected tab button
            event.currentTarget.classList.add('text-indigo-600', 'border-indigo-600');
            event.currentTarget.classList.remove('text-gray-500', 'hover:text-gray-700');
        }
    </script>
</body>
</html>