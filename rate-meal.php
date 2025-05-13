<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $meal_id = $_POST['scheduled_meal_id'];
    $rating = $_POST['rating'];
    $user_id = $_SESSION['user_id'];

    // Verify the meal belongs to the user and update rating
    $query = "UPDATE scheduled_meals 
              SET rating = ? 
              WHERE id = ? AND user_id = ? 
              AND scheduled_date <= CURRENT_DATE()";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $rating, $meal_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
