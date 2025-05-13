<?php
session_start();
include 'db_connect.php';

// For debugging
// file_put_contents('rating_debug.log', print_r($_POST, true), FILE_APPEND);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to rate meals";
    header("Location: meals.php");
    exit;
}

// Check for required parameters
if (!isset($_POST['meal_id']) || !isset($_POST['rating'])) {
    $_SESSION['error'] = "Missing required parameters: " . 
                          (isset($_POST['meal_id']) ? "" : "meal_id ") . 
                          (isset($_POST['rating']) ? "" : "rating");
    header("Location: meals.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$meal_id = intval($_POST['meal_id']);
$rating = intval($_POST['rating']);

// Validate rating (1-5 stars)
if ($rating < 1 || $rating > 5) {
    $_SESSION['error'] = "Rating must be between 1 and 5. Received: $rating";
    header("Location: meals.php");
    exit;
}

// First check if this is the user's meal
$check_query = "SELECT meal_id FROM scheduled_meals WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $meal_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "You can only rate meals you have ordered";
    header("Location: meals.php");
    exit;
}

$meal_data = $result->fetch_assoc();
$real_meal_id = $meal_data['meal_id'];

// Update the rating in scheduled_meals
$update_query = "UPDATE scheduled_meals SET rating = ? WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("iii", $rating, $meal_id, $user_id);

if ($stmt->execute()) {
    // Update the avg_rating and order_count in meals table
    $avg_query = "UPDATE meals SET 
                  avg_rating = (
                      SELECT COALESCE(AVG(rating), 0)
                      FROM scheduled_meals 
                      WHERE meal_id = ? AND rating IS NOT NULL
                  ),
                  order_count = (
                      SELECT COUNT(*)
                      FROM scheduled_meals
                      WHERE meal_id = ?
                  )
                  WHERE id = ?";
    $stmt = $conn->prepare($avg_query);
    $stmt->bind_param("iii", $real_meal_id, $real_meal_id, $real_meal_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Rating updated successfully to $rating stars";
    } else {
        $_SESSION['error'] = "Failed to update average rating";
    }
} else {
    $_SESSION['error'] = "Failed to update rating";
}

$conn->close();
header("Location: meals.php");
exit;
?>
