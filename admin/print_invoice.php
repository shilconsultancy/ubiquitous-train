<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_config.php';

// --- Authentication & Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) { die("DB connection error."); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access Denied.");
}

$invoice = null;
$fee_items = [];
$error_message = '';

$invoice_number_param = isset($_GET['invoice_num']) ? trim($_GET['invoice_num']) : '';

if (!empty($invoice_number_param)) {
    // 1. Fetch main invoice details
    $stmt = $conn->prepare("
        SELECT
            inv.id, inv.student_id, u.username AS student_username, u.email AS student_email,
            u.acca_id AS student_acca_id, inv.invoice_number, inv.issue_date,
            inv.due_date, inv.total_amount, inv.amount_paid, inv.balance_due, inv.status
        FROM invoices inv
        JOIN users u ON inv.student_id = u.id
        WHERE inv.invoice_number = ?
    ");
    $stmt->bind_param("s", $invoice_number_param);
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

    } else {
        $error_message = "Invoice not found.";
    }
    $stmt->close();
} else {
    $error_message = "No invoice number provided.";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice #<?= htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #F3F4F6; }
        .invoice-container { max-width: 800px; margin: 2rem auto; background: white; padding: 2.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .invoice-table th, .invoice-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #E5E7EB; }
        .invoice-table thead th { background-color: #F9FAFB; font-weight: 600; color: #374151; }
        @media print {
            body { background-color: white; margin: 0; padding: 0; }
            .no-print { display: none; }
            .invoice-container { box-shadow: none; border: none; margin: 0; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

    <?php if ($invoice): ?>
    <div class="invoice-container">
        <div class="no-print mb-6 flex justify-end gap-x-2">
             <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-print mr-2"></i> Print
            </button>
            <button onclick="window.close()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center">
                 <i class="fas fa-times-circle mr-2"></i> Close
            </button>
        </div>

        <div class="flex justify-between items-start mb-8 border-b pb-6">
            <div>
                <img src="PSB_LOGO.png" alt="PSB Logo" class="h-12 mb-4">
                <p class="font-semibold text-gray-800">Professional School of Business</p>
                <p class="text-sm text-gray-500">First floor, Bashshah Mia Building, 1419,<br> Nasirabad, Chittagong, Bangladesh</p>
                <p class="text-sm text-gray-500">Phone: 01978-003029</p>
                <p class="text-sm text-gray-500">Email: info@psbctg.com</p>
                <p class="text-sm text-gray-500">Website: psbctg.com</p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-bold text-gray-800">INVOICE</h2>
                <p class="text-gray-600">#<?= htmlspecialchars($invoice['invoice_number']); ?></p>
                <!-- **FIX:** Moved Status to here -->
                <div class="mt-2">
                    <span class="px-3 py-1 rounded-full text-sm font-semibold
                        <?= match ($invoice['status']) {
                            'Paid' => 'bg-green-100 text-green-800',
                            'Partially Paid' => 'bg-blue-100 text-blue-800',
                            'Overdue' => 'bg-red-100 text-red-800',
                            default => 'bg-yellow-100 text-yellow-800'
                        }; ?>">
                        <?= htmlspecialchars($invoice['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-10">
            <div>
                <p class="text-sm font-semibold text-gray-500">BILLED TO</p>
                <p class="font-bold text-gray-800 mt-1"><?= htmlspecialchars($invoice['student_username']); ?></p>
                <p class="text-gray-600">ACCA ID: <?= htmlspecialchars($invoice['student_acca_id'] ?? 'N/A'); ?></p>
                <p class="text-gray-600"><?= htmlspecialchars($invoice['student_email'] ?? ''); ?></p>
            </div>
            <div class="text-right">
                <p><span class="font-semibold text-gray-600">Issue Date:</span> <?= date('M d, Y', strtotime($invoice['issue_date'])); ?></p>
                <p><span class="font-semibold text-gray-600">Due Date:</span> <?= date('M d, Y', strtotime($invoice['due_date'])); ?></p>
            </div>
        </div>

        <h3 class="text-lg font-bold text-dark mb-2">Invoice Items</h3>
        <table class="w-full invoice-table mb-8">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount (BDT)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fee_items as $item): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['fee_type']))); ?>
                            <?php if (!empty($item['subject'])): ?>
                                <span class="block text-xs text-gray-500"><?= htmlspecialchars($item['subject']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= number_format($item['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="flex justify-end mb-8">
            <div class="w-full max-w-xs text-gray-700">
                <div class="flex justify-between py-2">
                    <span>Subtotal</span>
                    <span>BDT <?= number_format(array_sum(array_column($fee_items, 'amount')), 2); ?></span>
                </div>
                 <div class="flex justify-between py-2">
                    <span>Discount</span>
                    <span>- BDT <?= number_format(array_sum(array_column($fee_items, 'amount')) - $invoice['total_amount'], 2); ?></span>
                </div>
                <div class="flex justify-between py-2 border-t font-bold">
                    <span>Invoice Total</span>
                    <span>BDT <?= number_format($invoice['total_amount'], 2); ?></span>
                </div>
                <div class="flex justify-between py-2 text-green-600">
                    <span>Amount Paid</span>
                    <span>- BDT <?= number_format($invoice['amount_paid'], 2); ?></span>
                </div>
                <div class="flex justify-between py-2 border-t-2 border-gray-800">
                    <span class="font-bold text-lg">Balance Due</span>
                    <span class="font-bold text-lg">BDT <?= number_format($invoice['balance_due'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="text-center border-t pt-6 mt-8">
            <p class="text-sm text-gray-500">Thank you for your business!</p>
        </div>

    </div>
    <?php else: ?>
        <div class="max-w-md mx-auto mt-10 bg-white rounded-xl shadow-custom p-8 text-center">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Error!</p>
                <p><?= htmlspecialchars($error_message); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <script>
        window.onload = function() {
            // Automatically trigger the browser's print function
            // window.print();
        };
    </script>
</body>
</html>