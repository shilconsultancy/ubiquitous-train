<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_config.php';

if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    error_log("Database connection failed in add_invoice.php: " . $conn->connect_error);
    header('Location: ../index.php');
    exit;
}
$active_page = 'invoicing';
$error_message = '';
$students_list = [];
$fee_types_list = [];
$fees_subject_list = [];

// Fetch necessary data for the form
$result_fee_types = $conn->query("SELECT id, type_name, price, needs_subject, is_custom_amount FROM fee_types ORDER BY type_name ASC");
if ($result_fee_types) {
    while ($row = $result_fee_types->fetch_assoc()) {
        $fee_types_list[] = $row;
    }
}
$result_fees_subject = $conn->query("SELECT id, subject_code, price, category FROM fee_subjects ORDER BY category, subject_code ASC");
if ($result_fees_subject) {
    while ($row = $result_fees_subject->fetch_assoc()) {
        $fees_subject_list[] = $row;
    }
}
$result_students = $conn->query("SELECT id, username, acca_id FROM users WHERE role = 'student' ORDER BY username ASC");
if ($result_students) {
    while ($row = $result_students->fetch_assoc()) {
        $students_list[] = $row;
    }
}

// --- FORM SUBMISSION LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- 1. GATHER AND VALIDATE FORM DATA ---
    $student_id = (int)($_POST['student_id'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');
    $discount_applied = (float)($_POST['discount'] ?? 0);
    $cart_items_json = $_POST['cart_items'] ?? '[]';
    $cart_items = json_decode($cart_items_json, true);

    // Initial Payment Data
    $initial_payment_amount = (float)($_POST['initial_payment_amount'] ?? 0);
    $initial_payment_method = trim($_POST['initial_payment_method'] ?? 'Cash');
    $initial_payment_notes = trim($_POST['initial_payment_notes'] ?? '');

    // Basic validation
    if (empty($student_id) || empty($due_date) || empty($cart_items) || !is_array($cart_items)) {
        $error_message = "Student, Due Date, and at least one invoice item are required.";
    }

    $total_original_amount = array_reduce($cart_items, fn($sum, $item) => $sum + (float)$item['amount'], 0);
    $final_amount_after_discount = $total_original_amount - $discount_applied;

    if (empty($error_message) && $final_amount_after_discount < 0) {
        $error_message = "Final amount cannot be negative after discount.";
    }
    if (empty($error_message) && $initial_payment_amount > $final_amount_after_discount) {
        $error_message = "Initial payment cannot be greater than the total amount due.";
    }

    // Discount Authorization
    if (empty($error_message) && $discount_applied > 0) {
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
            $error_message = "You do not have permission to apply discounts.";
        } else {
            $admin_password = $_POST['admin_password'] ?? '';
            if (empty($admin_password)) {
                $error_message = "Password is required for discount authorization.";
            } else {
                $stmt_admins = $conn->prepare("SELECT password_hash FROM users WHERE role IN ('admin', 'super_admin')");
                $stmt_admins->execute();
                $result_admins = $stmt_admins->get_result();
                $admin_hashes = $result_admins->fetch_all(MYSQLI_ASSOC);
                $stmt_admins->close();
                $password_verified = false;
                foreach ($admin_hashes as $hash_row) {
                    if (password_verify($admin_password, $hash_row['password_hash'])) {
                        $password_verified = true;
                        break;
                    }
                }
                if (!$password_verified) {
                    $error_message = "Invalid Password. Discount authorization failed.";
                }
            }
        }
    }

    // --- 2. PROCESS TRANSACTION IF VALIDATION PASSES ---
    if (empty($error_message)) {
        $conn->begin_transaction();
        try {
            // --- INVOICE CREATION ---
            $issue_date = date('Y-m-d');
            $temp_invoice_number = 'TEMP-' . uniqid();
            $initial_status = 'Pending';

            $sql_invoice = "INSERT INTO invoices (student_id, invoice_number, issue_date, due_date, total_amount, amount_paid, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_invoice = $conn->prepare($sql_invoice);
            $stmt_invoice->bind_param("isssdds", $student_id, $temp_invoice_number, $issue_date, $due_date, $final_amount_after_discount, $initial_payment_amount, $initial_status);
            $stmt_invoice->execute();
            $new_invoice_id = $conn->insert_id;
            $stmt_invoice->close();

            $invoice_display_number = 'INV-' . date('Y') . '-' . str_pad($new_invoice_id, 4, '0', STR_PAD_LEFT);
            $stmt_update_inv_num = $conn->prepare("UPDATE invoices SET invoice_number = ? WHERE id = ?");
            $stmt_update_inv_num->bind_param("si", $invoice_display_number, $new_invoice_id);
            $stmt_update_inv_num->execute();
            $stmt_update_inv_num->close();

            // --- FEE ITEMS INSERTION ---
            foreach ($cart_items as $item) {
                $sql_fee = "INSERT INTO fees (student_id, fee_type, subject, amount, original_amount, due_date, payment_status, invoice_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_fee = $conn->prepare($sql_fee);
                $fee_payment_status = ($initial_payment_amount >= $final_amount_after_discount) ? 'Paid' : 'Pending';
                $stmt_fee->bind_param("issddsss", $student_id, $item['fee_type'], $item['subject_code'], $item['amount'], $item['amount'], $due_date, $fee_payment_status, $invoice_display_number);
                $stmt_fee->execute();
                $stmt_fee->close();
            }

            // --- INITIAL PAYMENT RECORDING ---
            if ($initial_payment_amount > 0) {
                $sql_payment = "INSERT INTO payments (invoice_id, amount, payment_date, payment_method, notes, processed_by) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_payment = $conn->prepare($sql_payment);
                $stmt_payment->bind_param("idssss", $new_invoice_id, $initial_payment_amount, $issue_date, $initial_payment_method, $initial_payment_notes, $_SESSION['username']);
                $stmt_payment->execute();
                $stmt_payment->close();
            }

            // --- FINAL INVOICE STATUS UPDATE ---
            $final_balance = $final_amount_after_discount - $initial_payment_amount;
            $final_status = 'Pending';
            if ($final_balance <= 0) {
                $final_status = 'Paid';
            } elseif ($initial_payment_amount > 0) {
                $final_status = 'Partially Paid';
            }

            $stmt_status_update = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
            $stmt_status_update->bind_param("si", $final_status, $new_invoice_id);
            $stmt_status_update->execute();
            $stmt_status_update->close();

            $conn->commit();

            // --- **FIX:** Redirect to the new invoice's view page ---
            header('Location: view_invoice.php?invoice_id=' . $new_invoice_id . '&message_type=success&message=' . urlencode('Invoice ' . $invoice_display_number . ' created successfully!'));
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Transaction failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Invoice - PSB Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="image.png" type="image/png">
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
        html, body { height: 100%; }
        body { background-color: #F8FAFC; display: flex; flex-direction: column; }
        ::-webkit-scrollbar { width: 8px; } ::-webkit-scrollbar-track { background: #f1f1f1; } ::-webkit-scrollbar-thumb { background: #c5c5c5; border-radius: 4px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } } .fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .active-tab { background: linear-gradient(90deg, rgba(79,70,229,0.1) 0%, rgba(14,165,233,0.05) 100%); border-left: 4px solid #4F46E5; color: #4F46E5; }
        .shadow-custom { box-shadow: 0 4px 20px rgba(0,0,0,0.08); } .sidebar-link:hover { background: linear-gradient(90deg, rgba(79,70,229,0.1) 0%, rgba(14,165,233,0.05) 100%); }
        .pos-section-title { @apply text-lg font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-200; }
        .pos-total-display { @apply bg-gray-800 text-white p-6 rounded-lg text-right; }
        .fee-type-btn.selected { @apply ring-2 ring-offset-2 ring-primary border-primary bg-primary text-white; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; backdrop-filter: blur(8px); animation: fadeIn 0.3s ease-out forwards; }
        .modal-content { background-color: #fefefe; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1); width: 90%; max-width: 650px; position: relative; animation: slideIn 0.3s ease-out forwards; display: flex; flex-direction: column; max-height: 90vh; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .close-button { position: absolute; top: 15px; right: 15px; font-size: 30px; cursor: pointer; color: #9CA3AF; transition: color 0.2s ease-in-out; }
        .close-button:hover { color: #4B5563; }
        .subject-grid-container { flex-grow: 1; overflow-y: auto; padding-right: 1rem; margin-bottom: 1.5rem; }
        .subject-list { display: flex; flex-direction: column; gap: 8px; }
        .subject-list-item { @apply p-3 border border-gray-200 rounded-lg cursor-pointer bg-white shadow-sm transition-all duration-200 ease-in-out; display: flex; align-items: center; justify-content: space-between; width: 100%; }
        .subject-list-item:hover { @apply border-blue-400 bg-blue-50; }
        .subject-list-item .subject-checkbox { @apply w-5 h-5 text-primary bg-gray-100 border-gray-300 rounded-md focus:ring-primary focus:ring-offset-0; flex-shrink: 0; margin-right: 0.75rem; }
        .subject-list-item .subject-code-display { @apply font-bold text-base text-gray-800 flex-grow; }
        .subject-list-item .default-subject-price-display { @apply text-base font-semibold text-gray-700; flex-shrink: 0; }
        .subject-list-item.selected-subject { @apply bg-primary text-white border-primary ring-2 ring-offset-2 ring-primary-500; }
        .subject-list-item.selected-subject .subject-code-display, .subject-list-item.selected-subject .default-subject-price-display { @apply text-white; }
        .subject-list-item.selected-subject .subject-checkbox { @apply bg-white border-white; }
        .subject-list-item.selected-subject .subject-checkbox:checked { @apply text-blue-600; }
        .subject-custom-input-group { display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0; margin-left: 1rem; }
        .subject-custom-input-group label { @apply text-sm text-gray-700 font-semibold; flex-shrink: 0; }
        .subject-list-item.selected-subject .subject-custom-input-group label { @apply text-white; }
        .subject-custom-price-input { @apply w-28 p-1.5 border-2 border-blue-400 rounded-md text-base font-bold text-right text-gray-800; transition: all 0.2s ease-in-out; }
        .subject-custom-price-input:focus { @apply outline-none ring-2 ring-blue-300; }
        .subject-list-item.selected-subject .subject-custom-price-input { @apply bg-white text-primary border-primary; }
        #modal_selected_subjects_summary { @apply p-4 bg-blue-100 border border-blue-300 rounded-lg text-blue-800; }
        #modal_summary_fee_type { @apply text-xl font-semibold mb-2; }
        #modal_total_selected_amount { @apply text-2xl font-extrabold; }
        @media (max-width: 1024px) { main { padding: 1.5rem; } }
        @media (max-width: 767px) {
            main { padding: 1rem; padding-top: 80px; padding-bottom: 100px; }
            .flex-col.sm\:flex-row { flex-direction: column; align-items: stretch; }
            .w-full.sm\:w-auto { width: 100%; }
            .sm\:text-left { text-align: center; }
            .grid.grid-cols-1.md\:grid-cols-2.gap-6, .grid.grid-cols-1.md\:grid-cols-3.gap-6 { grid-template-columns: 1fr; }
            .pos-grid { grid-template-columns: 1fr; }
            .order-md-last { order: initial; }
        }
        @media (min-width: 768px) {
            main { padding-top: 1.5rem; padding-bottom: 1.5rem; }
            .pos-grid { grid-template-columns: 2fr 1fr; }
            .order-md-last { order: 99; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">
    <?php require_once 'header.php'; ?>
    <div class="flex flex-1">
        <?php require_once 'sidebar.php'; ?>
        <main class="flex-1 p-4 sm:p-6 pb-24 md:pb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
                <div>
                    <h2 class="text-3xl font-extrabold text-gray-900 leading-tight">Create New Invoice</h2>
                    <p class="text-gray-500 mt-2">Process payments and generate invoices for students.</p>
                </div>
                <a href="invoicing.php" class="w-full sm:w-auto mt-4 sm:mt-0 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center justify-center transition duration-200 ease-in-out">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Invoices
                </a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md fade-in" role="alert">
                    <p class="font-bold">Error:</p>
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form action="add_invoice.php" method="POST" class="grid pos-grid gap-6">
                <div class="bg-white rounded-xl shadow-custom p-6 sm:p-8 fade-in space-y-6">
                    <h3 class="pos-section-title"><i class="fas fa-user-graduate mr-2"></i>Student & Invoice Details</h3>
                    <div>
                        <label for="student_id" class="block text-sm font-medium text-gray-700 mb-2">Select Student:</label>
                        <select id="student_id" name="student_id" required class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary text-base">
                            <option value="">-- Search or Select a Student --</option>
                            <?php foreach ($students_list as $student_item): ?>
                                <option value="<?php echo htmlspecialchars($student_item['id']); ?>"><?php echo htmlspecialchars($student_item['username']); ?> (ID: <?php echo htmlspecialchars($student_item['acca_id'] ?? 'N/A'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date:</label>
                        <input type="date" id="due_date" name="due_date" required value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Choose Invoice Items:</label>
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3" id="fee_type_buttons">
                            <?php foreach ($fee_types_list as $fee_type_data): ?>
                                <button type="button" data-fee-type-name="<?= htmlspecialchars($fee_type_data['type_name']); ?>" data-fee-price="<?= htmlspecialchars($fee_type_data['price'] ?? '0.00'); ?>" data-needs-subject="<?= htmlspecialchars($fee_type_data['needs_subject']); ?>" data-is-custom-amount="<?= htmlspecialchars($fee_type_data['is_custom_amount']); ?>" class="fee-type-btn p-4 rounded-lg border border-gray-300 bg-white text-gray-700 hover:border-primary hover:bg-primary-50 transition-all duration-200 ease-in-out text-center">
                                    <i class="fas <?php
                                        switch($fee_type_data['type_name']) {
                                            case 'tuition': echo 'fa-book-open'; break;
                                            case 'admission': echo 'fa-user-plus'; break;
                                            case 'exam fees': echo 'fa-pencil-alt'; break;
                                            case 'event fees': echo 'fa-calendar-alt'; break;
                                            case 'fine': echo 'fa-exclamation-triangle'; break;
                                            case 'other': echo 'fa-tags'; break;
                                            default: echo 'fa-money-bill-wave'; break;
                                        }
                                    ?> mb-1 text-lg"></i>
                                    <span class="block text-sm font-medium"><?= ucfirst(str_replace('_', ' ', $fee_type_data['type_name'])); ?></span>
                                    <?php if ($fee_type_data['price'] !== null && $fee_type_data['price'] > 0): ?>
                                        <span class="block text-xs text-gray-500">BDT <?= number_format($fee_type_data['price'], 2); ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="custom_amount_area_main" class="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-2 hidden">
                        <label for="custom_fee_name_input" class="block text-sm font-medium text-gray-700">Item Name/Description:</label>
                        <input type="text" id="custom_fee_name_input" placeholder="e.g., Lab Equipment Fee" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <label for="current_item_amount" class="block text-sm font-medium text-gray-700">Custom Item Amount (BDT):</label>
                        <input type="number" id="current_item_amount" step="0.01" min="0" value="0.00" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm text-lg font-semibold">
                        <button type="button" id="add_custom_item_to_cart_btn" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-cart-plus mr-2"></i> Add Item to Invoice
                        </button>
                    </div>
                </div>

                <div class="order-md-last bg-white rounded-xl shadow-custom p-6 sm:p-8 fade-in flex flex-col justify-between">
                    <div class="flex-grow">
                        <h3 class="pos-section-title"><i class="fas fa-receipt mr-2"></i>Invoice Summary</h3>
                        <div id="cart_items_display" class="space-y-3 mb-6 max-h-40 overflow-y-auto pr-2">
                            <p class="text-gray-500 text-center py-4" id="empty_cart_message">No items in invoice.</p>
                        </div>
                        <div class="space-y-3 mt-6">
                            <div class="flex justify-between items-center text-gray-700">
                                <span>Subtotal:</span>
                                <span class="font-medium text-lg" id="summary_original_amount">BDT 0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-700">
                                <label for="discount" class="text-sm font-medium text-gray-700 block">Discount (BDT):</label>
                                <input type="number" id="discount" name="discount" step="0.01" min="0" value="0" class="w-28 p-1 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary text-right font-semibold">
                            </div>
                            <hr class="border-gray-200 my-4">
                            <div class="flex justify-between items-center pos-total-display">
                                <span class="text-2xl font-bold">Total Due:</span>
                                <span class="text-4xl font-extrabold" id="summary_final_amount">BDT 0.00</span>
                            </div>
                        </div>

                        <div id="initial_payment_section" class="mt-6 space-y-4 pt-4 border-t">
                             <h3 class="text-lg font-semibold text-gray-700">Initial Payment (Optional)</h3>
                             <div>
                                <label for="initial_payment_amount" class="block text-sm font-medium text-gray-700">Amount Received (BDT):</label>
                                <input type="number" id="initial_payment_amount" name="initial_payment_amount" step="0.01" min="0" value="0" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm text-lg font-semibold">
                            </div>
                            <div>
                                <label for="initial_payment_method" class="block text-sm font-medium text-gray-700">Payment Method:</label>
                                <select id="initial_payment_method" name="initial_payment_method" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                                    <option>Cash</option>
                                    <option>Bank Transfer</option>
                                    <option>Mobile Banking</option>
                                    <option>Card</option>
                                </select>
                            </div>
                             <div>
                                <label for="initial_payment_notes" class="block text-sm font-medium text-gray-700">Payment Notes:</label>
                                <input type="text" id="initial_payment_notes" name="initial_payment_notes" placeholder="e.g., Transaction ID, reference" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>


                        <div id="superadmin_auth" class="hidden bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md space-y-4 fade-in mt-6">
                            <p class="font-bold text-yellow-800"><i class="fas fa-shield-alt mr-2"></i>Admin Authorization Required</p>
                            <div>
                                <label for="admin_password" class="block text-sm font-medium text-gray-700">Your Password:</label>
                                <input type="password" id="admin_password" name="admin_password" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="w-full flex justify-center py-4 px-4 border border-transparent rounded-lg shadow-lg text-lg font-semibold text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-200 ease-in-out">
                            <i class="fas fa-cash-register mr-3"></i> Finalize & Create Invoice
                        </button>
                    </div>
                </div>
                <input type="hidden" name="cart_items" id="cart_items_hidden_input">
            </form>
        </main>
    </div>

    <div id="subjectModal" class="modal">
        <div class="modal-content">
            <span class="close-button" id="modalCloseButton">&times;</span>
            <h3 class="text-2xl font-bold mb-4" id="modal_fee_type_title">Select Subject(s)</h3>
            <p class="text-gray-600 mb-6" id="modal_subject_instruction">Check the subjects you wish to add:</p>
            <div class="subject-grid-container">
                 <?php
                $grouped_subjects = [];
                foreach ($fees_subject_list as $subject_data) { $grouped_subjects[$subject_data['category']][] = $subject_data; }
                ?>
                <?php if (empty($fees_subject_list)): ?>
                    <p class="text-center text-gray-500">No subjects available.</p>
                <?php else: ?>
                    <?php foreach ($grouped_subjects as $category => $subjects): ?>
                        <div class="category-group mb-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-3 pb-1 border-b border-gray-200"><?= htmlspecialchars($category); ?></h4>
                            <div class="subject-list"> <?php foreach ($subjects as $subject): ?>
                                    <div class="subject-list-item"> <div class="flex items-center flex-grow"> <input type="checkbox" class="subject-checkbox" data-subject-id="<?= htmlspecialchars($subject['id']); ?>" data-subject-code="<?= htmlspecialchars($subject['subject_code']); ?>" data-subject-price="<?= htmlspecialchars(floatval($subject['price'])); ?>">
                                            <p class="subject-code-display"><?= htmlspecialchars($subject['subject_code']); ?></p>
                                        </div>
                                        <div class="flex items-center justify-end flex-shrink-0"> <span class="default-subject-price-display">BDT <?= number_format($subject['price'], 2); ?></span>
                                            <div class="subject-custom-input-group hidden">
                                                <label for="subject_custom_price_<?= $subject['id'] ?>">BDT</label>
                                                <input type="number" step="0.01" min="0" id="subject_custom_price_<?= $subject['id'] ?>" class="subject-custom-price-input" value="<?= htmlspecialchars(number_format($subject['price'], 2, '.', '')) ?>">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div id="modal_selected_subjects_summary" class="p-4 bg-blue-100 border border-blue-300 rounded-lg text-blue-800 mt-6 hidden">
                <p class="text-lg font-bold mt-1">Total Amount: <span id="modal_total_selected_amount">BDT 0.00</span></p>
            </div>
            <button type="button" id="add_subjects_to_cart_btn" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed mt-4">
                <i class="fas fa-check-circle mr-2"></i> Add Selected Items
            </button>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const feeTypesList = <?php echo json_encode($fee_types_list); ?>;
            const currentLoggedInUserRole = "<?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'guest'; ?>";
            const feeTypeButtonsContainer = document.getElementById('fee_type_buttons');
            const customAmountAreaMain = document.getElementById('custom_amount_area_main');
            const customFeeNameInput = document.getElementById('custom_fee_name_input');
            const currentItemAmountInput = document.getElementById('current_item_amount');
            const addCustomItemToCartBtn = document.getElementById('add_custom_item_to_cart_btn');
            const discountInput = document.getElementById('discount');
            const superadminAuthSection = document.getElementById('superadmin_auth');
            const cartItemsDisplay = document.getElementById('cart_items_display');
            const emptyCartMessage = document.getElementById('empty_cart_message');
            const summaryOriginalAmount = document.getElementById('summary_original_amount');
            const summaryFinalAmount = document.getElementById('summary_final_amount');
            const cartItemsHiddenInput = document.getElementById('cart_items_hidden_input');
            const subjectModal = document.getElementById('subjectModal');
            const modalCloseButton = document.getElementById('modalCloseButton');
            const modalFeeTypeTitle = document.getElementById('modal_fee_type_title');
            const modalSubjectInstruction = document.getElementById('modal_subject_instruction');
            const subjectsByCategoryContainer = subjectModal.querySelector('.subject-grid-container');
            const modalSelectedSubjectsSummary = document.getElementById('modal_selected_subjects_summary');
            const modalSummaryFeeType = document.getElementById('modal_summary_fee_type');
            const modalSelectedSubjectsList = document.getElementById('modal_selected_subjects_list');
            const modalTotalSelectedAmount = document.getElementById('modal_total_selected_amount');
            const addSubjectsToCartBtn = document.getElementById('add_subjects_to_cart_btn');
            let cart = [];
            let currentlySelectedFeeTypeData = null;
            let allowCustomSubjectPrice = false;
            function ucfirst(str) { return str.charAt(0).toUpperCase() + str.slice(1); }
            function updateCartDisplay() {
                cartItemsDisplay.innerHTML = '';
                if (cart.length === 0) { emptyCartMessage.classList.remove('hidden'); }
                else {
                    emptyCartMessage.classList.add('hidden');
                    cart.forEach((item, index) => {
                        const itemDiv = document.createElement('div');
                        itemDiv.classList.add('flex', 'justify-between', 'items-center', 'bg-blue-50', 'border', 'border-blue-200', 'rounded-md', 'p-3', 'text-blue-800', 'fade-in');
                        const displayAmount = (parseFloat(item.amount) || 0).toFixed(2);
                        let itemName = ucfirst(item.fee_type.replace('_', ' '));
                        if (item.custom_fee_name) { itemName = item.custom_fee_name; }
                        else if (item.subject_code) { itemName += ` - ${item.subject_code}`; }
                        itemDiv.innerHTML = `<div><p class="font-semibold">${itemName}</p><p class="text-sm">BDT ${displayAmount}</p></div><button type="button" data-index="${index}" class="remove-item-btn text-red-500 hover:text-red-700 ml-4"><i class="fas fa-times-circle"></i></button>`;
                        cartItemsDisplay.appendChild(itemDiv);
                    });
                }
                calculateTotals();
            }
            function calculateTotals() {
                let totalOriginal = cart.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
                let discount = parseFloat(discountInput.value) || 0;
                if (discount > totalOriginal) { discount = totalOriginal; discountInput.value = totalOriginal.toFixed(2); }
                let finalTotal = totalOriginal - discount;
                summaryOriginalAmount.textContent = `BDT ${totalOriginal.toFixed(2)}`;
                summaryFinalAmount.textContent = `BDT ${finalTotal.toFixed(2)}`;
                cartItemsHiddenInput.value = JSON.stringify(cart);
                if (discount > 0 && ['admin', 'super_admin'].includes(currentLoggedInUserRole)) {
                    superadminAuthSection.classList.remove('hidden');
                } else {
                    superadminAuthSection.classList.add('hidden');
                }
            }
            function openModal() {
                subjectModal.style.display = 'flex';
                document.querySelectorAll('.subject-checkbox').forEach(checkbox => { checkbox.checked = false; const customPriceInput = checkbox.closest('.subject-list-item').querySelector('.subject-custom-price-input'); if (customPriceInput) { const defaultPrice = parseFloat(checkbox.dataset.subjectPrice).toFixed(2); customPriceInput.value = defaultPrice; } });
                document.querySelectorAll('.subject-list-item').forEach(item => { item.classList.remove('selected-subject'); });
                modalSelectedSubjectsSummary.classList.add('hidden');
                modalSelectedSubjectsList.innerHTML = '';
                modalTotalSelectedAmount.textContent = 'BDT 0.00';
                addSubjectsToCartBtn.disabled = true;
                if (currentlySelectedFeeTypeData && currentlySelectedFeeTypeData.type_name === 'exam fees') {
                    allowCustomSubjectPrice = true;
                    modalSubjectInstruction.textContent = 'Select subjects and customize their prices:';
                    document.querySelectorAll('.subject-custom-input-group').forEach(group => group.classList.remove('hidden'));
                    document.querySelectorAll('.default-subject-price-display').forEach(span => span.classList.add('hidden'));
                } else {
                    allowCustomSubjectPrice = false;
                    modalSubjectInstruction.textContent = 'Check the subjects you wish to add:';
                    document.querySelectorAll('.subject-custom-input-group').forEach(group => group.classList.add('hidden'));
                    document.querySelectorAll('.default-subject-price-display').forEach(span => span.classList.remove('hidden'));
                }
                updateModalPreview();
            }
            function closeModal() {
                subjectModal.style.display = 'none';
                currentlySelectedFeeTypeData = null;
                allowCustomSubjectPrice = false;
                document.querySelectorAll('.fee-type-btn').forEach(btn => btn.classList.remove('selected'));
            }
            function updateModalPreview() {
                const checkedSubjects = Array.from(subjectsByCategoryContainer.querySelectorAll('.subject-checkbox:checked'));
                let totalSelectedAmount = 0;
                let allPricesValid = true;
                if (checkedSubjects.length === 0) { modalSelectedSubjectsSummary.classList.add('hidden'); addSubjectsToCartBtn.disabled = true; }
                else {
                    modalSelectedSubjectsSummary.classList.remove('hidden');
                    checkedSubjects.forEach(checkbox => {
                        const subjectCode = checkbox.dataset.subjectCode || 'Unknown Subject';
                        let subjectPrice = 0;
                        if (allowCustomSubjectPrice) { const customPriceInput = checkbox.closest('.subject-list-item').querySelector('.subject-custom-price-input'); subjectPrice = parseFloat(customPriceInput.value) || 0; if (isNaN(subjectPrice) || subjectPrice < 0) { allPricesValid = false; } }
                        else { subjectPrice = parseFloat(checkbox.dataset.subjectPrice) || 0; }
                        totalSelectedAmount += subjectPrice;
                    });
                    addSubjectsToCartBtn.disabled = !(checkedSubjects.length > 0 && allPricesValid);
                }
                modalTotalSelectedAmount.textContent = `BDT ${totalSelectedAmount.toFixed(2)}`;
            }
            feeTypeButtonsContainer.addEventListener('click', function(event) {
                const button = event.target.closest('.fee-type-btn');
                if (button) {
                    document.querySelectorAll('.fee-type-btn').forEach(btn => { btn.classList.remove('selected'); });
                    button.classList.add('selected');
                    currentlySelectedFeeTypeData = feeTypesList.find(type => type.type_name === button.dataset.feeTypeName);
                    customAmountAreaMain.classList.add('hidden');
                    currentItemAmountInput.value = '0.00';
                    customFeeNameInput.value = '';
                    if (currentlySelectedFeeTypeData.needs_subject == 1) { modalFeeTypeTitle.textContent = `Select Subject(s) for ${ucfirst(currentlySelectedFeeTypeData.type_name.replace('_', ' '))}`; openModal(); }
                    else if (currentlySelectedFeeTypeData.is_custom_amount == 1) { customAmountAreaMain.classList.remove('hidden'); customFeeNameInput.focus(); addCustomItemToCartBtn.disabled = true; updateCustomFeeAddButtonState(); }
                    else { cart.push({ fee_type: currentlySelectedFeeTypeData.type_name, amount: parseFloat(currentlySelectedFeeTypeData.price) || 0, subject_code: null, custom_fee_name: null }); updateCartDisplay(); button.classList.remove('selected'); currentlySelectedFeeTypeData = null; }
                }
            });
            subjectsByCategoryContainer.addEventListener('click', function(event) { const subjectItem = event.target.closest('.subject-list-item'); if (subjectItem) { const checkbox = subjectItem.querySelector('.subject-checkbox'); if (event.target !== checkbox && !event.target.closest('label') && !event.target.classList.contains('subject-custom-price-input')) { checkbox.checked = !checkbox.checked; } checkbox.dispatchEvent(new Event('change')); } });
            subjectsByCategoryContainer.addEventListener('change', function(event) { if (event.target.classList.contains('subject-checkbox')) { const subjectItem = event.target.closest('.subject-list-item'); if (event.target.checked) { subjectItem.classList.add('selected-subject'); } else { subjectItem.classList.remove('selected-subject'); } updateModalPreview(); } });
            subjectsByCategoryContainer.addEventListener('input', function(event) { if (event.target.classList.contains('subject-custom-price-input')) { const checkbox = event.target.closest('.subject-list-item').querySelector('.subject-checkbox'); if (!checkbox.checked) { checkbox.checked = true; checkbox.closest('.subject-list-item').classList.add('selected-subject'); } updateModalPreview(); } });
            if (addSubjectsToCartBtn) { addSubjectsToCartBtn.addEventListener('click', function() { const checkedSubjects = Array.from(subjectsByCategoryContainer.querySelectorAll('.subject-checkbox:checked')); if (checkedSubjects.length === 0) { alert('Please select at least one subject.'); return; } if (!currentlySelectedFeeTypeData) { alert("Error: Fee type not selected. Please close and re-open the modal."); closeModal(); return; } let hasInvalidPrice = false; checkedSubjects.forEach(checkbox => { let subjectPrice = 0; if (allowCustomSubjectPrice) { const customPriceInput = checkbox.closest('.subject-list-item').querySelector('.subject-custom-price-input'); subjectPrice = parseFloat(customPriceInput.value) || 0; if (isNaN(subjectPrice) || subjectPrice < 0) { hasInvalidPrice = true; } } else { subjectPrice = parseFloat(checkbox.dataset.subjectPrice) || 0; } cart.push({ fee_type: currentlySelectedFeeTypeData.type_name, amount: subjectPrice, subject_code: checkbox.dataset.subjectCode, custom_fee_name: null }); }); if (hasInvalidPrice) { alert("Please ensure all selected subjects have a valid, non-negative price."); return; } updateCartDisplay(); closeModal(); }); }
            if (modalCloseButton) { modalCloseButton.addEventListener('click', function(event) { event.stopPropagation(); closeModal(); }); }
            subjectModal.addEventListener('click', function(event) { if (event.target === subjectModal) { closeModal(); } });
            function updateCustomFeeAddButtonState() { const amount = parseFloat(currentItemAmountInput.value) || 0; const customName = customFeeNameInput.value.trim(); addCustomItemToCartBtn.disabled = !(amount > 0 && customName.length > 0); }
            currentItemAmountInput.addEventListener('input', updateCustomFeeAddButtonState);
            customFeeNameInput.addEventListener('input', updateCustomFeeAddButtonState);
            addCustomItemToCartBtn.addEventListener('click', function() { const amount = parseFloat(currentItemAmountInput.value) || 0; const customName = customFeeNameInput.value.trim(); if (currentlySelectedFeeTypeData && currentlySelectedFeeTypeData.is_custom_amount == 1 && amount > 0 && customName.length > 0) { cart.push({ fee_type: currentlySelectedFeeTypeData.type_name, amount: amount, subject_code: null, custom_fee_name: customName }); updateCartDisplay(); customAmountAreaMain.classList.add('hidden'); currentlySelectedFeeTypeData = null; document.querySelectorAll('.fee-type-btn').forEach(btn => btn.classList.remove('selected')); } else { alert("Please enter a positive amount and a name for the custom fee."); } });
            discountInput.addEventListener('input', calculateTotals);
            cartItemsDisplay.addEventListener('click', function(event) { const removeButton = event.target.closest('.remove-item-btn'); if (removeButton) { const indexToRemove = parseInt(removeButton.dataset.index); cart.splice(indexToRemove, 1); updateCartDisplay(); } });
            updateCartDisplay();
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            if (userMenuButton && userMenu) { userMenuButton.addEventListener('click', function() { userMenu.classList.toggle('hidden'); }); document.addEventListener('click', function(event) { if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) { userMenu.classList.add('hidden'); } }); document.addEventListener('keydown', function(event) { if (event.key === 'Escape') { userMenu.classList.add('hidden'); } }); }
            const mobileMoreBtn = document.getElementById('mobile-more-btn');
            const mobileMoreMenu = document.getElementById('mobile-more-menu');
            if(mobileMoreBtn){ mobileMoreBtn.addEventListener('click', (e) => { e.preventDefault(); mobileMoreMenu.classList.toggle('hidden'); }); }
            document.addEventListener('click', (e) => { if (mobileMoreMenu && !mobileMoreMenu.classList.contains('hidden') && !mobileMoreBtn.contains(e.target) && !mobileMoreMenu.contains(e.target)) { mobileMoreMenu.classList.add('hidden'); } });
        });
    </script>
</body>
</html>