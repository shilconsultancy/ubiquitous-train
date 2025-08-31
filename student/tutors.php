<?php
session_start(); // Start the session to make session variables available

// Set the current page for the sidebar active state
$currentPage = 'tutors';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet Our Tutors - PSB Learning Hub</title>
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

        .accordion-header { cursor: pointer; transition: background-color 0.3s ease; }
        .accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out; background-color: #fafafa; }
        .accordion-header .accordion-icon { transition: transform 0.4s ease; }
        .accordion-header.active .accordion-icon { transform: rotate(180deg); }
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
                    <div class="bg-white rounded-2xl shadow-sm p-6 lg:p-8">
                        <div class="mb-6 border-b pb-4">
                            <h2 class="text-2xl font-bold text-gray-800">Meet Our Tutors</h2>
                            <p class="text-gray-500 mt-1">Expert guidance for your ACCA journey.</p>
                        </div>
                        
                        <div class="space-y-4">

                            <!-- Tutor 1: John Doe -->
                            <div class="accordion-item border rounded-lg overflow-hidden bg-white">
                                <div class="accordion-header bg-gray-50 hover:bg-gray-100 p-4 flex justify-between items-center">
                                    <div class="flex items-center">
                                        <img src="https://placehold.co/60x60/c51a1d/white?text=JD" alt="Tutor John Doe" class="w-16 h-16 rounded-full object-cover">
                                        <div class="ml-4">
                                            <h3 class="text-lg font-bold text-theme-black">John Doe</h3>
                                            <p class="text-sm text-gray-600">Audit & Assurance, Financial Management</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-down accordion-icon text-theme-red text-xl"></i>
                                </div>
                                <div class="accordion-content">
                                    <div class="p-6">
                                        <h4 class="font-bold text-xl mb-4 text-theme-black">Tutor Profile</h4>
                                        <div class="flex flex-col md:flex-row gap-6">
                                            <div class="flex-grow">
                                                <h5 class="font-semibold text-theme-black mb-1">About Me</h5>
                                                <p class="text-gray-700 mb-4">With over 10 years of experience in public practice, I specialize in breaking down complex auditing standards and financial concepts into easy-to-understand lessons.</p>
                                                <h5 class="font-semibold text-theme-black mb-1">Qualifications</h5>
                                                <ul class="list-disc list-inside text-gray-700">
                                                    <li>ACCA Qualified Member</li>
                                                    <li>BSc in Accounting and Finance</li>
                                                </ul>
                                                <h5 class="font-semibold text-theme-black mt-4 mb-1">Subjects Taught</h5>
                                                <ul class="list-disc list-inside text-gray-700">
                                                    <li>Audit & Assurance (AA)</li>
                                                    <li>Financial Management (FM)</li>
                                                </ul>
                                            </div>
                                            <div class="flex-shrink-0 md:w-1/3 md:border-l md:pl-6">
                                                <h5 class="font-semibold text-theme-black mb-2">Get in Touch</h5>
                                                <div class="space-y-3">
                                                    <a href="https://wa.me/15551234567" target="_blank" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fab fa-whatsapp text-green-500 text-2xl w-6"></i><span class="ml-3 font-medium text-gray-800">Chat on WhatsApp</span></a>
                                                    <a href="tel:+1-555-123-4567" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fas fa-phone text-blue-500 text-xl w-6"></i><span class="ml-3 font-medium text-gray-800">Call Direct</span></a>
                                                    <a href="mailto:john.doe@example.com" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fas fa-envelope text-theme-red text-xl w-6"></i><span class="ml-3 font-medium text-gray-800">Send an Email</span></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tutor 2: Jane Smith -->
                            <div class="accordion-item border rounded-lg overflow-hidden bg-white">
                                <div class="accordion-header bg-gray-50 hover:bg-gray-100 p-4 flex justify-between items-center">
                                    <div class="flex items-center">
                                        <img src="https://placehold.co/60x60/1a1a1a/white?text=JS" alt="Tutor Jane Smith" class="w-16 h-16 rounded-full object-cover">
                                        <div class="ml-4">
                                            <h3 class="text-lg font-bold text-theme-black">Jane Smith</h3>
                                            <p class="text-sm text-gray-600">Financial Reporting, Taxation</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-down accordion-icon text-theme-red text-xl"></i>
                                </div>
                                <div class="accordion-content">
                                    <div class="p-6">
                                        <h4 class="font-bold text-xl mb-4 text-theme-black">Tutor Profile</h4>
                                        <div class="flex flex-col md:flex-row gap-6">
                                            <div class="flex-grow">
                                                <h5 class="font-semibold text-theme-black mb-1">About Me</h5>
                                                <p class="text-gray-700 mb-4">As a certified tax advisor, I focus on exam technique and time management to help students conquer the most challenging aspects of Financial Reporting and Taxation papers.</p>
                                                <h5 class="font-semibold text-theme-black mb-1">Qualifications</h5>
                                                <ul class="list-disc list-inside text-gray-700">
                                                    <li>Chartered Tax Advisor (CTA)</li>
                                                    <li>ACCA Fellowship (FCCA)</li>
                                                </ul>
                                                <h5 class="font-semibold text-theme-black mt-4 mb-1">Subjects Taught</h5>
                                                <ul class="list-disc list-inside text-gray-700">
                                                    <li>Financial Reporting (FR)</li>
                                                    <li>Taxation (TX-UK)</li>
                                                </ul>
                                            </div>
                                            <div class="flex-shrink-0 md:w-1/3 md:border-l md:pl-6">
                                                <h5 class="font-semibold text-theme-black mb-2">Get in Touch</h5>
                                                <div class="space-y-3">
                                                    <a href="https://wa.me/15557654321" target="_blank" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fab fa-whatsapp text-green-500 text-2xl w-6"></i><span class="ml-3 font-medium text-gray-800">Chat on WhatsApp</span></a>
                                                    <a href="tel:+1-555-765-4321" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fas fa-phone text-blue-500 text-xl w-6"></i><span class="ml-3 font-medium text-gray-800">Call Direct</span></a>
                                                    <a href="mailto:jane.smith@example.com" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fas fa-envelope text-theme-red text-xl w-6"></i><span class="ml-3 font-medium text-gray-800">Send an Email</span></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tutor 3: Ahmed Khan -->
                             <div class="accordion-item border rounded-lg overflow-hidden bg-white">
                                <div class="accordion-header bg-gray-50 hover:bg-gray-100 p-4 flex justify-between items-center">
                                    <div class="flex items-center">
                                        <img src="https://placehold.co/60x60/4A5568/white?text=AK" alt="Tutor Ahmed Khan" class="w-16 h-16 rounded-full object-cover">
                                        <div class="ml-4">
                                            <h3 class="text-lg font-bold text-theme-black">Ahmed Khan</h3>
                                            <p class="text-sm text-gray-600">Performance Management, SBR</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-down accordion-icon text-theme-red text-xl"></i>
                                </div>
                                <div class="accordion-content">
                                    <div class="p-6">
                                        <h4 class="font-bold text-xl mb-4 text-theme-black">Tutor Profile</h4>
                                        <div class="flex flex-col md:flex-row gap-6">
                                            <div class="flex-grow">
                                                <h5 class="font-semibold text-theme-black mb-1">About Me</h5>
                                                <p class="text-gray-700 mb-4">I bring real-world corporate finance experience to my teaching, focusing on strategic case studies and performance metrics that are crucial for the professional level papers.</p>
                                                <h5 class="font-semibold text-theme-black mb-1">Qualifications</h5>
                                                <ul class="list-disc list-inside text-gray-700">
                                                    <li>CIMA Qualified</li>
                                                    <li>Masters in Business Administration (MBA)</li>
                                                </ul>
                                                <h5 class="font-semibold text-theme-black mt-4 mb-1">Subjects Taught</h5>
                                                <ul class="list-disc list-inside text-gray-700">
                                                    <li>Performance Management (PM)</li>
                                                    <li>Strategic Business Reporting (SBR)</li>
                                                </ul>
                                            </div>
                                            <div class="flex-shrink-0 md:w-1/3 md:border-l md:pl-6">
                                                <h5 class="font-semibold text-theme-black mb-2">Get in Touch</h5>
                                                <div class="space-y-3">
                                                    <a href="https://wa.me/15559876543" target="_blank" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fab fa-whatsapp text-green-500 text-2xl w-6"></i><span class="ml-3 font-medium text-gray-800">Chat on WhatsApp</span></a>
                                                    <a href="tel:+1-555-987-6543" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fas fa-phone text-blue-500 text-xl w-6"></i><span class="ml-3 font-medium text-gray-800">Call Direct</span></a>
                                                    <a href="mailto:ahmed.khan@example.com" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"><i class="fas fa-envelope text-theme-red text-xl w-6"></i><span class="ml-3 font-medium text-gray-800">Send an Email</span></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Accordion Functionality ---
    const accordionItems = document.querySelectorAll('.accordion-item');
    accordionItems.forEach(item => {
        const header = item.querySelector('.accordion-header');
        const content = item.querySelector('.accordion-content');
        header.addEventListener('click', () => {
             // Close other open accordions
             accordionItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.querySelector('.accordion-header').classList.remove('active');
                    otherItem.querySelector('.accordion-content').style.maxHeight = '0';
                }
            });

            // Toggle the clicked accordion
            header.classList.toggle('active');
            if (header.classList.contains('active')) {
                content.style.maxHeight = content.scrollHeight + 'px';
            } else {
                content.style.maxHeight = '0';
            }
        });
    });

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