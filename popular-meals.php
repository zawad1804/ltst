<?php
session_start();
include 'db_connect.php';

// Fetch popular meals
$query = "SELECT * FROM popular_meals_view";
$result = $conn->query($query);

$popular_meals = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $popular_meals[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Popular Meals</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .meal-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .rating-stars {
            color: gold;
            font-size: 1.2rem;
        }
        .popularity-bar {
            height: 10px;
            background: linear-gradient(90deg, var(--accent) var(--score), transparent var(--score));
            border-radius: 5px;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <main class="container">
        <h1>Popular Meals This Month</h1>
        
        <div class="meals-grid">
            <?php foreach ($popular_meals as $meal): ?>
                <div class="meal-card">
                    <h3><?php echo htmlspecialchars($meal['name']); ?></h3>
                    <div class="rating-stars">
                        <?php echo str_repeat('★', round($meal['avg_rating'])) . str_repeat('☆', 5 - round($meal['avg_rating'])); ?>
                    </div>
                    <p>Orders this month: <?php echo $meal['order_count']; ?></p>
                    <p>Price: ৳<?php echo number_format($meal['price'], 2); ?></p>
                    <div class="popularity-bar" style="--score: <?php echo $meal['popularity_score']; ?>%"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
