<?php
// To highlight the active link, define a variable `$currentPage` on the page that includes this file.
if (!isset($currentPage)) {
    $currentPage = ''; // Set a default value to avoid errors
}
// Get the user's role from the session to display the admin link conditionally.
$user_role = $_SESSION['role'] ?? 'student'; 
?>
<!-- The sidebar is fixed on mobile (slides in/out) and static on desktop -->
<aside id="sidebar" class="w-64 bg-white shadow-lg flex-shrink-0 flex flex-col fixed md:static h-full z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    
    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        
        <?php // --- ADMIN-ONLY LINK --- 
              // This link will only be displayed if the user's role is 'admin' or 'super_admin'.
        ?>
        <?php if ($user_role === 'admin' || $user_role === 'super_admin'): ?>
        <a href="../admin/dashboard.php" class="flex items-center px-4 py-3 text-white bg-gray-800 hover:bg-gray-900 rounded-lg mb-4 transition-colors">
            <i class="fas fa-user-shield w-6 text-center text-lg"></i>
            <span class="ml-3 font-semibold">Admin Dashboard</span>
        </a>
        <div class="border-t border-gray-200 my-4"></div>
        <?php endif; ?>
        <?php // --- END ADMIN-ONLY LINK --- ?>

        <a href="dashboard.php" class="sidebar-link <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?> flex items-center px-4 py-3 text-gray-700 rounded-lg">
            <i class="fas fa-tachometer-alt w-6 text-center text-lg sidebar-icon text-gray-500"></i>
            <span class="ml-3 font-semibold">Dashboard</span>
        </a>
        <a href="full-history.php" class="sidebar-link <?php echo ($currentPage === 'history') ? 'active' : ''; ?> flex items-center px-4 py-3 text-gray-700 rounded-lg">
            <i class="fas fa-chart-line w-6 text-center text-lg sidebar-icon text-gray-500"></i>
            <span class="ml-3 font-semibold">Mock History</span>
        </a>
        <a href="exam-selection.php" class="sidebar-link <?php echo ($currentPage === 'new_mock') ? 'active' : ''; ?> flex items-center px-4 py-3 text-gray-700 rounded-lg">
            <i class="fas fa-pen w-6 text-center text-lg sidebar-icon text-gray-500"></i>
            <span class="ml-3 font-semibold">New Mock</span>
        </a>
        <a href="resources.php" class="sidebar-link <?php echo ($currentPage === 'resources') ? 'active' : ''; ?> flex items-center px-4 py-3 text-gray-700 rounded-lg">
            <i class="fas fa-book w-6 text-center text-lg sidebar-icon text-gray-500"></i>
            <span class="ml-3 font-semibold">Resources</span>
        </a>
        <a href="tutors.php" class="sidebar-link <?php echo ($currentPage === 'tutors') ? 'active' : ''; ?> flex items-center px-4 py-3 text-gray-700 rounded-lg">
            <i class="fas fa-comments w-6 text-center text-lg sidebar-icon text-gray-500"></i>
            <span class="ml-3 font-semibold">Tutors</span>
        </a>
        <a href="support.php" class="sidebar-link <?php echo ($currentPage === 'support') ? 'active' : ''; ?> flex items-center px-4 py-3 text-gray-700 rounded-lg">
            <i class="fas fa-headset w-6 text-center text-lg sidebar-icon text-gray-500"></i>
            <span class="ml-3 font-semibold">Support</span>
        </a>
    </nav>
    
    <!-- User Profile & Logout -->
    <div class="px-4 py-6 border-t">
        <a href="view_profile.php" class="sidebar-link <?php echo ($currentPage === 'profile') ? 'active' : ''; ?> flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2">
            <i class="fas fa-user-circle w-6 text-center text-lg sidebar-icon text-gray-500"></i>
            <span class="ml-3 font-semibold">My Profile</span>
        </a>
        <a href="../logout.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 rounded-lg">
            <i class="fas fa-sign-out-alt w-6 text-center text-lg sidebar-icon text-gray-500"></i>
            <span class="ml-3 font-semibold">Logout</span>
        </a>
    </div>

    <!-- Developer Credit -->
    <div class="px-4 py-4 text-center text-xs text-gray-400 border-t">
        Developed by <br> 
        <a href="https://shilconsultancy.com/" target="_blank" class="font-semibold text-gray-500 hover:text-theme-red transition-colors">
            Shil Consultancy
        </a>
    </div>
</aside>