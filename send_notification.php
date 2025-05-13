<?php
session_start();
include 'db_connect.php';

// Add error logging setup
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$error_log = $log_dir . '/notification_errors.log';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_STRING);

    // Log notification attempt
    error_log(date('Y-m-d H:i:s') . " - Attempting to send notification: $title\n", 3, $error_log);

    $conn->begin_transaction();

    try {
        // Get all members
        $members_query = "SELECT id FROM users WHERE role = 'member'";
        $members_result = $conn->query($members_query);
        
        if (!$members_result) {
            throw new Exception("Failed to fetch members: " . $conn->error);
        }

        $notification_count = 0;
        // Insert notification for each member
        while ($member = $members_result->fetch_assoc()) {
            $insert_query = "INSERT INTO notifications (user_id, type, title, message) 
                           VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("isss", $member['id'], $priority, $title, $message);
            if (!$stmt->execute()) {
                throw new Exception("Failed to send notification to user " . $member['id']);
            }
            $notification_count++;
        }

        $conn->commit();
        error_log(date('Y-m-d H:i:s') . " - Successfully sent $notification_count notifications\n", 3, $error_log);
        $_SESSION['success'] = "Notification sent successfully to $notification_count members";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        error_log(date('Y-m-d H:i:s') . " - Error sending notifications: $error_message\n" .
                 "Stack trace: " . $e->getTraceAsString() . "\n", 3, $error_log);
        $_SESSION['error'] = "Error sending notification: " . $error_message;
    }

    header("Location: admin-dashboard.php");
    exit();
}

header("Location: admin-dashboard.php");
exit();
