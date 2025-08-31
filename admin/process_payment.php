<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_config.php';

// --- Authentication & Connection Validation ---
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    die("DB connection error.");
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../index.php');
    exit;
}

// --- Main Payment Processing Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $amount_paid = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $payment_date = trim($_POST['payment_date']);
    $payment_method = trim($_POST['payment_method']);
    $notes = trim($_POST['notes']);
    $processed_by = $_SESSION['username']; // Log which admin processed the payment

    // --- Validation ---
    if ($invoice_id <= 0) {
        header('Location: invoicing.php?message_type=error&message=Invalid invoice ID.');
        exit;
    }
    if ($amount_paid <= 0) {
        header('Location: view_invoice.php?invoice_id=' . $invoice_id . '&message_type=error&message=Payment amount must be positive.');
        exit;
    }

    // Begin transaction for data integrity
    $conn->begin_transaction();

    try {
        // 1. Fetch the current invoice details to validate payment amount
        $stmt_invoice = $conn->prepare("SELECT total_amount, amount_paid, balance_due FROM invoices WHERE id = ? FOR UPDATE");
        $stmt_invoice->bind_param("i", $invoice_id);
        $stmt_invoice->execute();
        $invoice = $stmt_invoice->get_result()->fetch_assoc();
        $stmt_invoice->close();

        if (!$invoice) {
            throw new Exception("Invoice not found.");
        }

        if ($amount_paid > $invoice['balance_due']) {
            throw new Exception("Payment amount cannot exceed the balance due of BDT " . number_format($invoice['balance_due'], 2));
        }

        // 2. Insert the new payment record into the 'payments' table
        $stmt_payment = $conn->prepare("INSERT INTO payments (invoice_id, amount, payment_date, payment_method, notes, processed_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_payment->bind_param("idssss", $invoice_id, $amount_paid, $payment_date, $payment_method, $notes, $processed_by);
        if (!$stmt_payment->execute()) {
            throw new Exception("Failed to record payment: " . $stmt_payment->error);
        }
        $stmt_payment->close();

        // 3. Update the 'amount_paid' in the 'invoices' table
        $new_amount_paid = $invoice['amount_paid'] + $amount_paid;
        $stmt_update_invoice = $conn->prepare("UPDATE invoices SET amount_paid = ? WHERE id = ?");
        $stmt_update_invoice->bind_param("di", $new_amount_paid, $invoice_id);
        if (!$stmt_update_invoice->execute()) {
            throw new Exception("Failed to update invoice total: " . $stmt_update_invoice->error);
        }
        $stmt_update_invoice->close();

        // 4. Update the invoice status based on the new balance
        $new_balance_due = $invoice['total_amount'] - $new_amount_paid;
        $new_status = 'Pending'; // Default status
        if ($new_balance_due <= 0) {
            $new_status = 'Paid';
        } elseif ($new_amount_paid > 0) {
            $new_status = 'Partially Paid';
        }

        $stmt_status = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $stmt_status->bind_param("si", $new_status, $invoice_id);
        if (!$stmt_status->execute()) {
            throw new Exception("Failed to update invoice status: " . $stmt_status->error);
        }
        $stmt_status->close();


        // If everything is successful, commit the transaction
        $conn->commit();
        header('Location: view_invoice.php?invoice_id=' . $invoice_id . '&message_type=success&message=Payment recorded successfully!');
        exit;

    } catch (Exception $e) {
        // If any step fails, roll back the entire transaction
        $conn->rollback();
        header('Location: view_invoice.php?invoice_id=' . $invoice_id . '&message_type=error&message=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Redirect if accessed directly without POST data
    header('Location: invoicing.php');
    exit;
}
?>