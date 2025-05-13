<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $meal_date = $conn->real_escape_string($_POST['meal_date']);
    $meal_name = $conn->real_escape_string($_POST['meal_name']);
    $meal_price = (float)$_POST['meal_price'];
    $meal_notes = $conn->real_escape_string($_POST['meal_notes']);
    $items = isset($_POST['items']) ? $_POST['items'] : array();
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : array();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Debug log
        error_log("Creating meal: " . $meal_name);

        // Updated meal insert query to include user_id
        $meal_sql = "INSERT INTO meals (name, price, date, status, user_id) VALUES (?, ?, ?, 'active', ?)";
        $stmt = $conn->prepare($meal_sql);
        $stmt->bind_param("sdsi", $meal_name, $meal_price, $meal_date, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating meal: " . $stmt->error);
        }

        $meal_id = $conn->insert_id;
        error_log("Created meal with ID: " . $meal_id);

        // Add meal items table entries only
        foreach ($items as $i => $item) {
            $item_name = $conn->real_escape_string($item);
            $quantity = (float)$quantities[$i];
            
            $meal_item_sql = "INSERT INTO meal_items (meal_id, item_name, quantity) 
                             VALUES ($meal_id, '$item_name', $quantity)";
            $conn->query($meal_item_sql);
        }

        // Create notification for all members
        $notification_sql = "INSERT INTO notifications (user_id, type, title, message, created_at)
                           SELECT id, 'meal', 'New Meal Added', 
                           'A new meal \"$meal_name\" has been added for $meal_date', NOW()
                           FROM users WHERE role = 'member'";
        $conn->query($notification_sql);

        $conn->commit();
        header("Location: admin-dashboard.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Meal creation failed: " . $e->getMessage());
        header("Location: admin-dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: admin-dashboard.php");
exit();
?>
