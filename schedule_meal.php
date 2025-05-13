<?php
session_start();
include 'db_connect.php';

// Setup error logging
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$error_log = $log_dir . '/schedule_meal_errors.log';

if (!isset($_SESSION['user_id']) || !isset($_POST['meal_id'])) {
    header("Location: meals.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$meal_id = $_POST['meal_id'];
$meal_date = $_POST['meal_date'];
$meal_time = $_POST['meal_time'];
$quantity = $_POST['quantity'];

$conn->begin_transaction();

try {
    // Log attempt
    error_log(date('Y-m-d H:i:s') . " - Scheduling meal ID: $meal_id for user: $user_id\n", 3, $error_log);

    // 1. Get meal price
    $price_query = "SELECT price FROM meals WHERE id = ? AND status = 'active'";
    $stmt = $conn->prepare($price_query);
    $stmt->bind_param("i", $meal_id);
    $stmt->execute();
    $meal_price = $stmt->get_result()->fetch_assoc()['price'];
    $total_price = $meal_price * $quantity;

    // 2. Get meal items - update query to match your schema
    $items_query = "SELECT item_name, quantity FROM meal_items WHERE meal_id = ?";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $meal_id);
    $stmt->execute();
    $meal_items = $stmt->get_result();

    // Log items found
    error_log(date('Y-m-d H:i:s') . " - Found " . $meal_items->num_rows . " items for meal\n", 3, $error_log);

    // 3. Update inventory for each item
    while ($item = $meal_items->fetch_assoc()) {
        $total_required = $item['quantity'] * $quantity;
        
        // Update inventory by item_name
        $update_inventory = "UPDATE inventory 
                           SET quantity = quantity - ? 
                           WHERE item_name = ? 
                           AND quantity >= ?";
        $stmt = $conn->prepare($update_inventory);
        $stmt->bind_param("dsd", $total_required, $item['item_name'], $total_required);
        
        if (!$stmt->execute()) {
            throw new Exception("Insufficient quantity for: " . $item['item_name']);
        }
        error_log(date('Y-m-d H:i:s') . " - Updated inventory for: " . $item['item_name'] . "\n", 3, $error_log);
    }

    // 4. Insert scheduled meal
    $schedule_query = "INSERT INTO scheduled_meals 
                      (user_id, meal_id, scheduled_date, meal_time, quantity, total_price, status) 
                      VALUES (?, ?, ?, ?, ?, ?, 'active')";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("iissdd", $user_id, $meal_id, $meal_date, $meal_time, $quantity, $total_price);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to schedule meal");
    }

    // 5. Update user balance
    $update_balance = "UPDATE users SET balance = balance + ? WHERE id = ?";
    $stmt = $conn->prepare($update_balance);
    $stmt->bind_param("di", $total_price, $user_id);
    $stmt->execute();

    $conn->commit();
    $_SESSION['success'] = "Meal scheduled successfully!";
    error_log(date('Y-m-d H:i:s') . " - Meal scheduled successfully for user: $user_id\n", 3, $error_log);

} catch (Exception $e) {
    $conn->rollback();
    error_log(date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", 3, $error_log);
    $_SESSION['error'] = "Error scheduling meal: " . $e->getMessage();
}

header("Location: meals.php");
exit();
