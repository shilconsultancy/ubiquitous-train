<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet Our Tutors - Student Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Standard theme configuration
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'theme-red': '#c51a1d',
                        'theme-dark-red': '#a81013',
                        'theme-black': '#1a1a1a',
                        'light-gray': '#f5f7fa'
                    }
                }
            }
        }
    </script>
    <style>
        /* Accordion styles for a consistent look */
        .accordion-header {
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
            background-color: #fafafa;
        }
        .accordion-header .accordion-icon {
            transition: transform 0.4s ease;
        }
        .accordion-header.active .accordion-icon {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-light-gray font-sans">
    <div class="min-h-screen flex flex-col">
        <header class="bg-theme-red text-white shadow-md">
            <div class="container mx-auto px-4 py-5"><h1 class="text-2xl font-bold">PSB Learning Hub</h1></div>
        </header>

        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="bg-white rounded-xl shadow-md p-6 lg:p-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b pb-4">
                    <h2 class="text-2xl font-bold text-theme-black mb-4 sm:mb-0">Meet Our Tutors</h2>
                    <a href="dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-theme-black font-semibold py-2 px-4 rounded-lg transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
                
                <div class="space-y-4">

                    <div class="accordion-item border rounded-lg overflow-hidden">
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

                    <div class="accordion-item border rounded-lg overflow-hidden">
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

                    <div class="accordion-item border rounded-lg overflow-hidden">
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
        </main>

        <footer class="bg-theme-black text-white py-6">
            <div class="container mx-auto px-4 text-center"><p>&copy; <?php echo date('Y'); ?> Learning Hub. All rights reserved.</p></div>
        </footer>
    </div>

    <script>
        // This JavaScript makes the accordion expand and collapse.
        document.addEventListener('DOMContentLoaded', function() {
            const accordionItems = document.querySelectorAll('.accordion-item');
            accordionItems.forEach(item => {
                const header = item.querySelector('.accordion-header');
                const content = item.querySelector('.accordion-content');
                header.addEventListener('click', () => {
                    header.classList.toggle('active');
                    if (header.classList.contains('active')) {
                        content.style.maxHeight = content.scrollHeight + 'px';
                    } else {
                        content.style.maxHeight = '0';
                    }
                });
            });
        });
    </script>
</body>
</html>