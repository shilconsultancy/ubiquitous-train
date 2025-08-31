<?php
// Include the database connection file
require_once 'db_connect.php'; // Make sure this file is in the same directory

// --- START OF CONFIGURATION ---
// **IMPORTANT**: Change these values to your desired admin credentials.
$admin_username = 'student123';        // Choose a username for your admin
$admin_email = 'student123@example.com';  // Choose an email for your admin
$admin_password = 'pass123'; // << CHOOSE A STRONG PASSWORD
// --- END OF CONFIGURATION ---

// Hash the password for security using PHP's built-in function
// This is the same method your login script will use to verify the password
$password_hash = password_hash($admin_password, PASSWORD_DEFAULT);

// The role for this user will be 'admin'
$role = 'student';

// The ACCA ID is not required for admins in your setup, so we can set it to NULL or an empty string.
$acca_id = null;

// Prepare an SQL statement to insert the new admin user
// This helps prevent SQL injection attacks.
$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, acca_id) VALUES (?, ?, ?, ?, ?)");

// Check if the statement was prepared successfully
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

// Bind the parameters to the SQL query
// 'sssss' denotes that all five parameters are strings.
$stmt->bind_param("sssss", $admin_username, $admin_email, $password_hash, $role, $acca_id);

// Execute the statement and check for success
if ($stmt->execute()) {
    echo "<h1>Admin User Created Successfully!</h1>";
    echo "<p>Your new admin account has been created with the following details:</p>";
    echo "<ul>";
    echo "<li><strong>Email (for login):</strong> " . htmlspecialchars($admin_email) . "</li>";
    echo "<li><strong>Password:</strong> " . htmlspecialchars($admin_password) . "</li>";
    echo "</ul>";
    echo "<p style='color:red; font-weight:bold;'>IMPORTANT: For security reasons, please delete this file (<code>create_admin.php</code>) immediately!</p>";
} else {
    echo "<h1>Error!</h1>";
    echo "<p>Could not create the admin user. The error was:</p>";
    echo "<pre>" . htmlspecialchars($stmt->error) . "</pre>";
    echo "<p>It's possible an admin with that username or email already exists.</p>";
}

// Close the statement and the connection
$stmt->close();
$conn->close();
?>