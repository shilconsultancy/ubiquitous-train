<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_config.php';

// --- Authentication & Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) { die("DB connection error."); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../index.php');
    exit;
}

$active_page = 'invoicing';
$invoice = null;
$fee_items = []; // To store the line items for the invoice
$payment_history = [];
$error_message = '';

// --- Message Display Logic ---
$message_html = '';
if (isset($_GET['message']) && isset($_GET['message_type'])) {
    $message_content = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['message_type']);
    $message_class = match ($message_type) {
        'success' => 'bg-green-100 border-l-4 border-green-500 text-green-700',
        'error' => 'bg-red-100 border-l-4 border-red-500 text-red-700',
        default => 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700',
    };
    $message_html = "<div class='{$message_class} p-4 mb-6 rounded-md fade-in' role='alert'><p class='font-bold'>" . ucfirst($message_type) . "!</p><p>{$message_content}</p></div>";
}

// --- Data Fetching by Invoice ID ---
$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

if ($invoice_id > 0) {
    // 1. Fetch main invoice details
    $stmt = $conn->prepare("
        SELECT
            inv.id, inv.student_id, u.username AS student_username, u.email AS student_email,
            u.acca_id AS student_acca_id, inv.invoice_number, inv.issue_date,
            inv.due_date, inv.total_amount, inv.amount_paid, inv.balance_due, inv.status
        FROM invoices inv
        JOIN users u ON inv.student_id = u.id
        WHERE inv.id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $invoice = $result->fetch_assoc();

        // 2. Fetch all individual fee items for this invoice
        $stmt_items = $conn->prepare("SELECT fee_type, subject, amount FROM fees WHERE invoice_id = ?");
        $stmt_items->bind_param("s", $invoice['invoice_number']);
        $stmt_items->execute();
        $items_result = $stmt_items->get_result();
        while ($row = $items_result->fetch_assoc()) {
            $fee_items[] = $row;
        }
        $stmt_items->close();

        // 3. Fetch all payment history for this invoice
        $stmt_payments = $conn->prepare("SELECT amount, payment_date, payment_method, notes, processed_by FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC, created_at DESC");
        $stmt_payments->bind_param("i", $invoice_id);
        $stmt_payments->execute();
        $payments_result = $stmt_payments->get_result();
        while ($row = $payments_result->fetch_assoc()) {
            $payment_history[] = $row;
        }
        $stmt_payments->close();
    } else {
        $error_message = "Invoice not found.";
    }
    $stmt->close();
} else {
    $error_message = "No invoice ID provided.";
}

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
    <link rel="icon" href="image.png" type="image/png">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5', secondary: '#0EA5E9', dark: '#1E293B', light: '#F8FAFC',
                        success: '#10B981', danger: '#EF4444', warning: '#F59E0B'
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
        .shadow-custom { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .responsive-table thead { display: none; }
        @media (min-width: 768px) {
            .responsive-table thead { display: table-header-group; }
        }
        @media (max-width: 767px) {
            .responsive-table tr { display: block; margin-bottom: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; }
            .responsive-table td { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px dashed #e5e7eb; }
            .responsive-table td:last-child { border-bottom: none; }
            .responsive-table td::before { content: attr(data-label); font-weight: 600; color: #4b5563; margin-right: 1rem; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <?php require_once 'header.php'; ?>

    <div class="flex flex-1">
        <?php require_once 'sidebar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 pb-24 md:pb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Invoice Details</h2>
                    <p class="text-gray-500 mt-1">Viewing record for Invoice #<?= htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></p>
                </div>
                <div class="flex items-center space-x-3 mt-4 sm:mt-0 w-full sm:w-auto">
                    <a href="invoicing.php" class="w-1/3 sm:w-auto bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-arrow-left mr-2"></i> Back</a>
                    <?php if ($invoice): ?>
                    <a href="edit_invoice.php?id=<?= $invoice['id']; ?>" class="w-1/3 sm:w-auto bg-primary hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-edit mr-2"></i> Edit</a>
                    <a href="print_invoice.php?invoice_num=<?= urlencode($invoice['invoice_number']); ?>" target="_blank" class="w-1/3 sm:w-auto bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center justify-center"><i class="fas fa-print mr-2"></i> Print</a>
                    <?php endif; ?>
                </div>
            </div>

            <?= $message_html ?>

            <?php if ($invoice): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Payment Form & History -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Record Payment Form -->
                    <div class="bg-white rounded-xl shadow-custom p-6 fade-in">
                        <h3 class="text-lg font-bold text-dark mb-4 border-b pb-2">Record a Payment</h3>
                        <form action="process_payment.php" method="POST" class="space-y-4">
                            <input type="hidden" name="invoice_id" value="<?= $invoice['id']; ?>">
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700">Amount (BDT)</label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" max="<?= htmlspecialchars($invoice['balance_due']); ?>" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
                                       <?= ($invoice['balance_due'] <= 0) ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date</label>
                                <input type="date" name="payment_date" id="payment_date" required value="<?= date('Y-m-d'); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
                                       <?= ($invoice['balance_due'] <= 0) ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                <select name="payment_method" id="payment_method" required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
                                        <?= ($invoice['balance_due'] <= 0) ? 'disabled' : '' ?>>
                                    <option>Cash</option>
                                    <option>Bank Transfer</option>
                                    <option>Mobile Banking</option>
                                    <option>Card</option>
                                </select>
                            </div>
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                                <textarea name="notes" id="notes" rows="2"
                                          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
                                          <?= ($invoice['balance_due'] <= 0) ? 'disabled' : '' ?>></textarea>
                            </div>
                            <button type="submit"
                                    class="w-full bg-success hover:bg-green-700 text-white font-bold py-3 px-4 rounded-md flex items-center justify-center transition disabled:bg-gray-400 disabled:cursor-not-allowed"
                                    <?= ($invoice['balance_due'] <= 0) ? 'disabled' : '' ?>>
                                <i class="fas fa-check-circle mr-2"></i> Submit Payment
                            </button>
                        </form>
                    </div>
                    <!-- Payment History -->
                    <div class="bg-white rounded-xl shadow-custom p-6 fade-in">
                        <h3 class="text-lg font-bold text-dark mb-4 border-b pb-2">Payment History</h3>
                        <div class="space-y-3 max-h-60 overflow-y-auto">
                            <?php if (!empty($payment_history)): ?>
                                <?php foreach ($payment_history as $payment): ?>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <p class="font-bold text-green-600">BDT <?= number_format($payment['amount'], 2); ?></p>
                                        <p class="text-xs text-gray-500"><?= date('M d, Y', strtotime($payment['payment_date'])); ?></p>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($payment['payment_method']); ?> - by <?= htmlspecialchars($payment['processed_by']); ?></p>
                                    <?php if(!empty($payment['notes'])): ?>
                                    <p class="text-xs text-gray-500 mt-1 italic">"<?= htmlspecialchars($payment['notes']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-gray-500 py-4">No payments recorded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Invoice Details -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-custom p-6 sm:p-8 fade-in">
                    <div class="flex flex-col sm:flex-row justify-between items-start mb-6 border-b pb-6">
                        <div>
                            <img src="PSB_LOGO.png" alt="PSB Logo" class="h-12 mb-4">
                            <p class="font-semibold text-gray-800">Professional School of Business</p>
                            <p class="text-sm text-gray-500">First floor, Bashshah Mia Building, 1419, Nasirabad, Chittagong</p>
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
                        </div>
                        <div class="text-left md:text-right">
                             <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Details</h3>
                            <p class="mt-2 text-gray-600"><strong>Issue Date:</strong> <?= date('M d, Y', strtotime($invoice['issue_date'])); ?></p>
                            <p class="text-gray-600"><strong>Due Date:</strong> <?= date('M d, Y', strtotime($invoice['due_date'])); ?></p>
                        </div>
                    </div>

                    <!-- Invoice Items Table -->
                    <div class="mb-8">
                        <h3 class="text-lg font-bold text-dark mb-4">Invoice Items</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="p-3 text-left font-medium text-gray-600">Description</th>
                                        <th class="p-3 text-right font-medium text-gray-600">Amount (BDT)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php if (!empty($fee_items)): ?>
                                        <?php foreach ($fee_items as $item): ?>
                                            <tr>
                                                <td class="p-3">
                                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['fee_type']))); ?>
                                                    <?php if (!empty($item['subject'])): ?>
                                                        <span class="text-xs text-gray-500 block"><?= htmlspecialchars($item['subject']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="p-3 text-right"><?= number_format($item['amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="p-3 text-center text-gray-500">No items found for this invoice.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Financial Summary -->
                    <div class="grid grid-cols-3 gap-4 text-center mb-8 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm text-gray-500">Total Amount</p>
                            <p class="text-xl font-bold text-dark">BDT <?= number_format($invoice['total_amount'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Amount Paid</p>
                            <p class="text-xl font-bold text-success">BDT <?= number_format($invoice['amount_paid'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Balance Due</p>
                            <p class="text-xl font-bold text-danger">BDT <?= number_format($invoice['balance_due'], 2); ?></p>
                        </div>
                    </div>

                    <div class="border-t pt-6 mt-8 text-center text-gray-500 text-sm">
                        <p>Thank you for your business. Please make payments by the due date.</p>
                    </div>
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