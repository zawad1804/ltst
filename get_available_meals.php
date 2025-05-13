<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$query = "SELECT id, name, price 
          FROM meals 
          WHERE status = 'active'
          ORDER BY name";

$result = $conn->query($query);
$meals = array();

while ($row = $result->fetch_assoc()) {
    $meals[] = $row;
}

echo json_encode($meals);
?>
