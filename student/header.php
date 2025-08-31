<?php
// This file assumes a session has already been started on the page that includes it.
?>
<header class="bg-white shadow-md sticky top-0 z-30 w-full">
    <!-- The container class has been removed and padding is now used for alignment -->
    <div class="px-8 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <!-- Mobile Menu Button (visible on small screens) -->
            <button id="menu-button" class="text-gray-600 focus:outline-none md:hidden -ml-4">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            
            <!-- Logo & Title -->
            <div class="flex items-center space-x-3">
                 <!-- You can replace this icon with your actual logo image, e.g., <img src="path/to/logo.png" class="h-8 w-auto"> -->
                <img src="PSBlogo-main.png" class="h-10 w-auto">
                <h1 class="text-2xl font-bold text-gray-800">PSB Learning Hub</h1>
            </div>
        </div>
    </div>
</header>