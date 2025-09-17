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

        // 2. Fetch all individual fee items for this invoice, including descriptions
        $stmt_items = $conn->prepare("SELECT f.fee_type, f.subject, f.amount, ft.description FROM fees f LEFT JOIN fee_types ft ON f.fee_type = ft.type_name WHERE f.invoice_id = ?");
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
    <link rel="icon" href="image.png" type="image/png">
   <style>
        body {
            background-color: #F3F4F6;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }
        .invoice-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            padding: 2.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 0.5rem;
        }
        .invoice-table th, .invoice-table td {
            padding: 0.5rem 1rem; /* Reduced vertical padding */
            text-align: left;
            font-size: 0.875rem; /* Smaller font size */
        }
        .invoice-table thead th {
            background-color: #F9FAFB;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #E5E7EB;
        }
        .invoice-table tbody tr:not(:last-child) {
            border-bottom: 1px solid #F3F4F6;
        }
        @media print {
            body {
                background-color: white;
                margin: 0;
                padding: 1rem;
            }
            .no-print {
                display: none !important;
            }
            .invoice-container {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="max-w-4xl mx-auto py-4 no-print flex justify-end gap-x-3">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-lg flex items-center shadow-md transition-transform transform hover:scale-105">
            <i class="fas fa-print mr-2"></i> Print
        </button>
        <button onclick="window.close()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold px-5 py-2 rounded-lg flex items-center shadow-md transition-transform transform hover:scale-105">
                <i class="fas fa-times-circle mr-2"></i> Close
        </button>
    </div>

    <?php if ($invoice): ?>
    <div class="invoice-container">
        
        <header class="flex justify-between items-start mb-10 pb-6 border-b">
            <div>
                <img src="PSBlogo.png" alt="PSB Logo" class="h-24 w-auto">
            </div>
            <div class="text-right">
                <h1 class="text-3xl font-bold text-gray-800 uppercase tracking-wider">Invoice</h1>
                <p class="text-gray-500 mt-1">#<?= htmlspecialchars($invoice['invoice_number']); ?></p>
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
        </header>

        <section class="grid grid-cols-2 gap-8 mb-10">
            <div>
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Billed To</h2>
                <p class="font-bold text-lg text-gray-800"><?= htmlspecialchars($invoice['student_username']); ?></p>
                <p class="text-gray-600">ACCA ID: <?= htmlspecialchars($invoice['student_acca_id'] ?? 'N/A'); ?></p>
                <p class="text-gray-600"><?= htmlspecialchars($invoice['student_email'] ?? ''); ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">From</h2>
                <p class="font-semibold text-gray-800">Professional School of Business</p>
                <p class="text-gray-600">Nasirabad, Chittagong, Bangladesh</p>
                 <p class="text-gray-600 mt-4"><span class="font-semibold">Issue Date:</span> <?= date('M d, Y', strtotime($invoice['issue_date'])); ?></p>
                <p class="text-gray-600"><span class="font-semibold">Due Date:</span> <?= date('M d, Y', strtotime($invoice['due_date'])); ?></p>
            </div>
        </section>

        <section class="mb-10">
            <table class="w-full invoice-table">
                <thead>
                    <tr>
                        <th class="w-full">Description</th>
                        <th class="text-right whitespace-nowrap">Amount (BDT)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fee_items as $item): ?>
                        <tr>
                            <td>
                                <span class="font-medium text-gray-800"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['fee_type']))); ?></span>
                                <?php if (!empty($item['subject'])): ?>
                                    <span class="text-xs text-gray-500">- <?= htmlspecialchars($item['subject']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['description'])): ?>
                                    <span class="text-xs text-gray-500">- <?= htmlspecialchars($item['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-mono"><?= number_format($item['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="flex justify-end mb-10">
            <div class="w-full max-w-sm text-gray-700 space-y-3">
                <div class="grid grid-cols-2">
                    <span>Subtotal</span>
                    <span class="font-mono text-right"><?= number_format(array_sum(array_column($fee_items, 'amount')), 2); ?></span>
                </div>
                 <div class="grid grid-cols-2">
                    <span>Discount</span>
                    <span class="font-mono text-right">- <?= number_format(array_sum(array_column($fee_items, 'amount')) - $invoice['total_amount'], 2); ?></span>
                </div>
                <div class="grid grid-cols-2 border-t pt-2">
                    <span class="font-semibold">Invoice Total</span>
                    <span class="font-semibold font-mono text-right">BDT <?= number_format($invoice['total_amount'], 2); ?></span>
                </div>
                <div class="grid grid-cols-2 text-green-600">
                    <span>Amount Paid</span>
                    <span class="font-mono text-right">- <?= number_format($invoice['amount_paid'], 2); ?></span>
                </div>
                <div class="grid grid-cols-2 border-t-2 border-gray-800 pt-2 mt-2">
                    <span class="font-bold text-lg">Balance Due</span>
                    <span class="font-bold text-lg font-mono text-right">BDT <?= number_format($invoice['balance_due'], 2); ?></span>
                </div>
            </div>
        </section>

        <footer class="text-center border-t pt-6 mt-8">
            <p class="text-sm text-gray-500">Thank you for your business!</p>
            <p class="text-xs text-gray-400 mt-2">01978-003029 | info@psbctg.com | psbctg.com</p>
        </footer>

    </div>
    <?php else: ?>
        <div class="max-w-md mx-auto mt-10 bg-white rounded-xl shadow-custom p-8 text-center">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Error!</p>
                <p><?= htmlspecialchars($error_message); ?></p>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>