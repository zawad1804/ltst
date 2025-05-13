<?php
session_start();
include 'db_connect.php';

// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create logs directory if it doesn't exist
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$log_file = $log_dir . '/payment_logs.txt';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_SANITIZE_STRING);
    
    // Validate input
    if (!$amount || $amount <= 0) {
        $_SESSION['error'] = "Invalid payment amount";
        header("Location: payments.php");
        exit();
    }

    // Set initial payment status to pending
    $status = 'pending';
    
    // Start transaction
    $conn->begin_transaction();

    // Log payment process start
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Payment process started for user: $user_id\n", FILE_APPEND);
    
    try {
        // Log payment details
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Payment details: Amount=$amount, Method=$payment_method, TransactionID=$transaction_id\n", FILE_APPEND);

        // Log the SQL query for debugging
        $insert_query = "INSERT INTO payments (user_id, amount, payment_method, transaction_id, status, payment_date) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Query: $insert_query\n", FILE_APPEND);
        
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error . "\nQuery: " . $insert_query);
        }

        // Log parameters
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Parameters: user_id=$user_id, amount=$amount, method=$payment_method, transaction=$transaction_id, status=$status\n", FILE_APPEND);
        
        $stmt->bind_param("idsss", $user_id, $amount, $payment_method, $transaction_id, $status);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error . "\nQuery: " . $insert_query);
        }

        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Payment record inserted successfully\n", FILE_APPEND);

        $conn->commit();
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Transaction committed successfully\n", FILE_APPEND);
        
        $_SESSION['success'] = "Payment submitted successfully. Waiting for admin approval.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERROR: $error_message\n", FILE_APPEND);
        $_SESSION['error'] = "Error processing payment: " . $error_message;
        error_log("Payment Error: " . $error_message);
    }

    header("Location: payments.php");
    exit();
} else {
    header("Location: payments.php");
    exit();
}

// Additional logic for approving payments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['payment_id'])) {
    $action = $_POST['action'];
    $payment_id = $_POST['payment_id'];
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

    try {
        if ($action === 'approve') {
            // Update payment status
            $update_payment = "UPDATE payments SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($update_payment);
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();

            // Update user balance - only reduce if balance is positive
            $update_balance = "UPDATE users SET balance = GREATEST(0, balance - ?) WHERE id = ?";
            $stmt = $conn->prepare($update_balance);
            $stmt->bind_param("di", $amount, $user_id);
            $stmt->execute();

            $_SESSION['success'] = "Payment approved successfully";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERROR: $error_message\n", FILE_APPEND);
        $_SESSION['error'] = "Error processing payment: " . $error_message;
        error_log("Payment Error: " . $error_message);
    }

    header("Location: payments.php");
    exit();
}
?>
