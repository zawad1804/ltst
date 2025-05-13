<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['meal_id']) || !isset($_GET['quantity'])) {
    echo json_encode(['available' => false]);
    exit();
}

$meal_id = $_GET['meal_id'];
$quantity = $_GET['quantity'];

// Check if all ingredients are available in sufficient quantity
$check_query = "SELECT COUNT(*) as insufficient
                FROM meal_ingredients mi
                JOIN inventory i ON mi.ingredient_id = i.id
                WHERE mi.meal_id = ?
                AND i.quantity < (mi.quantity * ?)";

$stmt = $conn->prepare($check_query);
$stmt->bind_param("id", $meal_id, $quantity);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['available' => $result['insufficient'] == 0]);
