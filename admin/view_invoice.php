<?php
session_start();

// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

// --- Database Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("Error: Database connection could not be established.");
}

// --- Admin Authentication Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../index.php');
    exit;
}

// --- Define active page for sidebar ---
$active_page = 'invoicing';

$invoice = null;
$fee_items = []; // To store individual fee items for this invoice
$error_message = '';

// Crucially, fetch by invoice_number, not invoice primary ID
// The URL should pass the invoice_number (e.g., view_invoice.php?invoice_num=INV-2025...)
$invoice_number_param = isset($_GET['invoice_num']) ? trim($_GET['invoice_num']) : '';

if (!empty($invoice_number_param)) {
    // 1. Fetch main invoice details along with student details using invoice_number
    $stmt = $conn->prepare("
        SELECT 
            inv.id, inv.student_id, u.username AS student_username, u.email AS student_email,
            u.acca_id AS student_acca_id, inv.invoice_number, inv.issue_date, 
            inv.due_date, inv.total_amount, inv.fee_type, inv.subject AS invoice_subject_summary, inv.status,
            inv.paid_date, inv.created_at, inv.updated_at
        FROM invoices inv
        JOIN users u ON inv.student_id = u.id
        WHERE inv.invoice_number = ?
    ");
    $stmt->bind_param("s", $invoice_number_param);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $invoice = $result->fetch_assoc();

        // 2. Fetch all individual fee items associated with this invoice_number
        $stmt_fees = $conn->prepare("
            SELECT 
                fee.fee_type, fee.subject, fee.amount, fee.original_amount, fee.discount_applied
            FROM fees fee
            WHERE fee.invoice_id = ?
        ");
        $stmt_fees->bind_param("s", $invoice_number_param);
        $stmt_fees->execute();
        $fees_result = $stmt_fees->get_result();
        while ($row = $fees_result->fetch_assoc()) {
            $fee_items[] = $row;
        }
        $stmt_fees->close();

    } else {
        $error_message = "Invoice not found or multiple invoices with the same number (should not happen for unique invoice_number).";
    }
    $stmt->close();
} else {
    $error_message = "No invoice number provided.";
}

// Calculate subtotal and total discount from fee_items for display coherence
// Note: invoice.total_amount already reflects final amount after discount.
// We need original subtotal for breakdown.
$calculated_subtotal = 0;
foreach ($fee_items as $item) {
    $calculated_subtotal += $item['original_amount']; // Sum original amounts of individual fees
}
// The total discount is stored in the main invoice record's total_amount.
// This means total_amount = calculated_subtotal - invoice.discount_applied (if you add a discount_applied to invoices table)
// For now, if invoice.total_amount is the final price, then:
$total_discount_for_display = $calculated_subtotal - ($invoice['total_amount'] ?? 0);
if ($total_discount_for_display < 0) $total_discount_for_display = 0; // Avoid negative if amounts are off

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice - PSB Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5', secondary: '#0EA5E9', dark: '#1E293B', light: '#F8FAFC'
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c5c5c5; border-radius: 4px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .active-tab {
            background: linear-gradient(90deg, rgba(79,70,229,0.1) 0%, rgba(14,165,233,0.05) 100%);
            border-left: 4px solid #4F46E5;
            color: #4F46E5;
        }
        .shadow-custom { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .sidebar-link:hover { background: linear-gradient(90deg, rgba(79,70,229,0.1) 0%, rgba(14,165,233,0.05) 100%); }
        
        .invoice-table th, .invoice-table td { padding: 0.75rem 1rem; text-align: left; }
        .invoice-table thead th { background-color: #f3f4f6; font-weight: 600; color: #374151; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <?php require_once 'header.php'; ?>

    <div class="flex flex-1">
        
        <?php require_once 'sidebar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 pb-24 md:pb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Invoice Details</h2>
                    <p class="text-gray-500 mt-1">Viewing record for Invoice #<?= htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></p>
                </div>
                <div class="flex items-center space-x-3 mt-4 sm:mt-0 w-full sm:w-auto">
                    <a href="invoicing.php" class="w-1/2 sm:w-auto bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-arrow-left mr-2"></i> Back</a>
                    <?php if ($invoice): ?>
                    <a href="print_invoice.php?invoice_num=<?= urlencode($invoice['invoice_number']); ?>" target="_blank" class="w-1/2 sm:w-auto bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-print mr-2"></i> Print</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($invoice): ?>
            <div class="bg-white rounded-xl shadow-custom p-6 sm:p-8 fade-in max-w-4xl mx-auto">
                <div class="flex flex-col sm:flex-row justify-between items-start mb-8 border-b pb-6">
                    <div>
                        <img src="PSB_LOGO.png" alt="PSB Logo" class="h-12 mb-4">
                        <p class="font-semibold text-gray-800">Professional School of Business</p>
                         <p class="text-sm text-gray-500">123 Learning Street, Dhaka, Bangladesh</p>
                        <p class="text-sm text-gray-500">contact@psb.com</p>
                        <p class="text-sm text-gray-500">+880 123 456 7890</p>
                    </div>
                    <div class="text-left sm:text-right mt-4 sm:mt-0">
                        <h2 class="text-3xl font-bold text-gray-800 uppercase">Invoice</h2>
                        <p class="text-gray-500 mt-1"><?= htmlspecialchars($invoice['invoice_number']); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Billed To</h3>
                        <p class="mt-2 font-bold text-lg text-dark"><?= htmlspecialchars($invoice['student_username']); ?></p>
                        <p class="text-gray-600"><?= htmlspecialchars($invoice['student_acca_id']); ?></p>
                        <p class="text-gray-600"><?= htmlspecialchars($invoice['student_email']); ?></p>
                    </div>
                    <div class="text-left md:text-right">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Invoice Details</h3>
                        <p class="mt-2 text-gray-600"><strong>Issue Date:</strong> <?= date('M d, Y', strtotime($invoice['issue_date'])); ?></p>
                        <p class="text-gray-600"><strong>Due Date:</strong> <?= date('M d, Y', strtotime($invoice['due_date'])); ?></p>
                        <p class="text-gray-600"><strong>Status:</strong> 
                            <span class="font-semibold px-2 py-1 rounded-full text-xs <?= match ($invoice['status']) { 'Paid' => 'bg-green-100 text-green-800', 'Overdue' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-gray-100 text-gray-800', default => 'bg-yellow-100 text-yellow-800' }; ?>">
                                <?= htmlspecialchars($invoice['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full invoice-table mb-8">
                        <thead>
                            <tr class="border-b border-gray-300">
                                <th class="px-4 py-2">Description</th>
                                <th class="px-4 py-2 text-right">Unit Price</th>
                                <th class="px-4 py-2 text-right">Discount</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($fee_items)): ?>
                                <?php foreach ($fee_items as $item): ?>
                                    <tr class="border-b border-gray-200">
                                        <td class="px-4 py-3">
                                            <?= ucwords(htmlspecialchars($item['fee_type'])); ?>
                                            <?php if (!empty($item['subject'])): ?>
                                                <span class="block text-xs text-gray-500">- Subject: <?= htmlspecialchars($item['subject']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right">BDT <?= number_format($item['original_amount'], 2); ?></td>
                                        <td class="px-4 py-3 text-right">BDT <?= number_format($item['discount_applied'], 2); ?></td>
                                        <td class="px-4 py-3 text-right">BDT <?= number_format($item['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-center text-gray-500">No fee items found for this invoice.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end mt-8">
                    <div class="w-full sm:w-1/2 md:w-1/3 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span>BDT <?= number_format($calculated_subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Item Discount:</span>
                            <span>BDT 0.00</span> </div>
                         <?php if (($invoice['total_amount'] < $calculated_subtotal) && ($total_discount_for_display > 0)): // Show overall transaction discount only if applied ?>
                            <div class="flex justify-between font-semibold text-red-600">
                                <span>Transaction Discount:</span>
                                <span>- BDT <?= number_format($total_discount_for_display, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (0%):</span>
                            <span>BDT 0.00</span>
                        </div>
                        <div class="flex justify-between border-t pt-3 mt-3">
                            <span class="font-bold text-xl text-dark">Total Due:</span>
                            <span class="font-bold text-xl text-dark">BDT <?= number_format($invoice['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-6 mt-8 text-center text-gray-500 text-sm">
                    <p>Thank you for your business. Please make payments by the due date.</p>
                </div>
            </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-custom p-8 text-center"><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p class="font-bold">Error!</p><p><?= htmlspecialchars($error_message); ?></p></div></div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            if(userMenuButton){ userMenuButton.addEventListener('click', (e) => { e.stopPropagation(); userMenu.classList.toggle('hidden'); }); }
            document.addEventListener('click', (e) => { if (userMenu && !userMenu.classList.contains('hidden') && !userMenuButton.contains(e.target)) { userMenu.classList.add('hidden'); } });

            const mobileMoreBtn = document.getElementById('mobile-more-btn');
            const mobileMoreMenu = document.getElementById('mobile-more-menu');
            if(mobileMoreBtn){ mobileMoreBtn.addEventListener('click', (e) => { e.preventDefault(); mobileMoreMenu.classList.toggle('hidden'); }); }
            document.addEventListener('click', (e) => { if (mobileMoreMenu && !mobileMoreMenu.classList.contains('hidden') && !mobileMoreBtn.contains(e.target)) { mobileMoreMenu.classList.add('hidden'); } });
        });
    </script>
</body>
</html>