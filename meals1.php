<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Update available meals query to show only active meals
$available_meals_query = "SELECT id, name, price 
                         FROM meals 
                         WHERE status = 'active' 
                         ORDER BY name";
$stmt = $conn->prepare($available_meals_query);
$stmt->execute();
$available_meals = $stmt->get_result();

// Get months for filter (last 12 months)
$months_query = "SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') as month_year,
                DATE_FORMAT(date, '%M %Y') as month_display
                FROM meals 
                WHERE user_id = $user_id 
                ORDER BY date DESC 
                LIMIT 12";
$months_result = $conn->query($months_query);

// Fetch user's scheduled meals with proper JOIN
$history_query = "SELECT 
    sm.id as schedule_id,
    sm.scheduled_date as date,
    sm.meal_time,
    m.name,
    sm.quantity,
    sm.total_price as price,
    sm.rating
    FROM scheduled_meals sm
    JOIN meals m ON sm.meal_id = m.id
    WHERE sm.user_id = ? 
    AND sm.status = 'active'
    ORDER BY sm.scheduled_date DESC";

$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$meal_history = $stmt->get_result();

// Get user's scheduled meals
$scheduled_meals_query = "SELECT 
    sm.id as schedule_id,
    sm.scheduled_date as date,
    sm.meal_time,
    m.name,
    sm.quantity,
    COALESCE(sm.total_price, m.price * sm.quantity) as price,  /* Calculate price if total_price is null */
    m.rating
    FROM scheduled_meals sm
    JOIN meals m ON sm.meal_id = m.id
    WHERE sm.user_id = ? 
    AND sm.status = 'active'
    ORDER BY sm.scheduled_date DESC";

$stmt = $conn->prepare($scheduled_meals_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$scheduled_meals = $stmt->get_result();

// Query to get popular meals of the month with the popularity score
$popular_meals_query = "SELECT 
    m.*,
    (
        -- Rating component (45%)
        (COALESCE(m.avg_rating, 0) / 5 * 45) + 
        -- Order count component (35%)
        (m.order_count / (SELECT MAX(order_count) + 0.1 FROM meals WHERE MONTH(date) = MONTH(CURRENT_DATE())) * 35) +
        -- Price component (20%) - lower price gets higher score
        ((1 - (m.price / (SELECT MAX(price) + 0.1 FROM meals WHERE MONTH(date) = MONTH(CURRENT_DATE())))) * 20)
    ) as popularity_score
FROM 
    meals m
WHERE 
    m.status = 'active' AND
    MONTH(m.date) = MONTH(CURRENT_DATE()) AND
    YEAR(m.date) = YEAR(CURRENT_DATE())
ORDER BY 
    popularity_score DESC
LIMIT 5";

$popular_meals = $conn->query($popular_meals_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Meals | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --light-bg: #F3F3E0;
            --primary: #27548A;
            --dark-primary: #183B4E;
            --accent: #DDA853;
            --glass-bg: rgba(255, 255, 255, 0.15);
            --glass-border: rgba(255, 255, 255, 0.2);
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: var(--light-bg);
            color: var(--dark-primary);
        }
        .meals-container {
            width: 100%;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .meals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .meals-header h1 {
            color: var(--primary);
            margin: 0;
            text-align: center;
            width: 100%;
        }
        .glass-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            padding: 2rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
        }
        .section-heading {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            padding-bottom: 0.5rem;
        }
        .section-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--accent);
        }
        .scheduler-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--primary);
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--glass-border);
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.2);
            color: var(--dark-primary);
        }
        .total-price-display {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--accent);
            margin-top: 0.5rem;
        }
        .schedule-btn {
            grid-column: 1 / -1;
            padding: 0.75rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }
        .schedule-btn:hover {
            background: var(--dark-primary);
        }
        .meals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .meals-table th {
            background: rgba(255, 255, 255, 0.3);
            text-align: center;
            padding: 1rem;
            position: sticky;
            top: 0;
        }
        .meals-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
            text-align: center;
        }
        .meals-table tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .meal-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .meal-history-header h2 {
            color: var(--primary);
            margin: 0;
        }
        .filter-options {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .filter-options select {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            color: var(--dark-primary);
        }
        .rating-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .star-rating {
            display: inline-flex;
            direction: rtl;
            background: #F8F9FA;
            padding: 0.3rem 0.5rem;
            border-radius: 20px;
        }
        .star-rating label {
            cursor: pointer;
            color: #ddd;
            font-size: 1.2rem;
            padding: 0 0.1rem;
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #DDA853;
            transform: scale(1.2);
        }
        .rate-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .rate-btn:hover {
            background: var(--dark-primary);
            transform: translateY(-2px);
        }
        .not-available-msg {
            color: #6c757d;
            font-style: italic;
            padding: 0.25rem 0.5rem;
            background: #F8F9FA;
            border-radius: 4px;
            font-size: 0.85rem;
            display: inline-block;
        }
        @media (max-width: 768px) {
            .meals-table {
                display: block;
                overflow-x: auto;
            }
            .meal-history-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-options {
                width: 100%;
                justify-content: flex-start;
            }
            .scheduler-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="user-dashboard.php">Dashboard</a>
                <a href="meals.php" class="active">My Meals</a>
                <a href="inventory.php">Inventory</a>
                <a href="payments.php">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="login.html">Logout</a>
            </div>
        </div>
    </nav>

    <main class="meals-container">
        <header class="meals-header">
            <h1>My Meal Planner</h1>
        </header>

        <!-- Meal Scheduler Section -->
        <section class="glass-card meal-scheduler">
            <h2 class="section-heading">Schedule Your Meal</h2>
            <form action="schedule_meal.php" method="POST" class="scheduler-form">
                <div class="form-group">
                    <label for="meal_date">Date</label>
                    <input type="date" name="meal_date" id="meal_date" class="form-control" required min="<?php echo $today; ?>">
                </div>
                <div class="form-group">
                    <label for="meal_time">Meal Time</label>
                    <select name="meal_time" id="meal_time" class="form-control" required>
                        <option value="">Select Time</option>
                        <option value="breakfast">Breakfast</option>
                        <option value="lunch">Lunch</option>
                        <option value="dinner">Dinner</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="meal_id">Select Meal</label>
                    <select name="meal_id" id="meal_id" class="form-control" required onchange="updateTotalPrice()">
                        <option value="" data-price="0">Choose a meal</option>
                        <?php while($meal = $available_meals->fetch_assoc()): ?>
                            <option value="<?php echo $meal['id']; ?>" data-price="<?php echo $meal['price']; ?>">
                                <?php echo htmlspecialchars($meal['name']); ?> (৳<?php echo number_format($meal['price'], 2); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="1" required onchange="updateTotalPrice()">
                </div>
                <div class="form-group">
                    <label>Total Price</label>
                    <div class="total-price-display" id="total_price">৳0.00</div>
                </div>
                <button type="submit" class="schedule-btn">Schedule Meal</button>
            </form>
        </section>

        <!-- Popular Meals This Month Section -->
        <section class="glass-card" style="margin-bottom:2rem;">
            <h2 class="section-heading">Popular Meals This Month</h2>
            <table class="meals-table">
                <thead>
                    <tr>
                        <th>Meal Name</th>
                        <th>Price</th>
                        <th>Rating</th>
                        <th>Order Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($popular_meals && $popular_meals->num_rows > 0): ?>
                        <?php while($meal = $popular_meals->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($meal['name']); ?></td>
                                <td>৳<?php echo number_format($meal['price'], 2); ?></td>
                                <td><?php echo number_format($meal['avg_rating'], 1); ?> / 5</td>
                                <td><?php echo $meal['order_count']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No popular meals found for this month</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Meal History Section -->
        <section class="glass-card meal-history">
            <div class="meal-history-header">
                <h2>My Meal History</h2>
                <div class="filter-options">
                    <select id="month-filter" onchange="filterMeals()">
                        <option value="all">All Months</option>
                        <?php while($month = $months_result->fetch_assoc()): ?>
                            <option value="<?php echo $month['month_year']; ?>">
                                <?php echo $month['month_display']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <select id="meal-type-filter" onchange="filterMeals()">
                        <option value="all">All Meal Types</option>
                        <option value="breakfast">Breakfast</option>
                        <option value="lunch">Lunch</option>
                        <option value="dinner">Dinner</option>
                    </select>
                </div>
            </div>
            <table class="meals-table">
                <thead>
                    <tr>
                        <th>Meal ID</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Meal Description</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Rating (1-5)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($meal_history && $meal_history->num_rows > 0): ?>
                        <?php while($meal = $meal_history->fetch_assoc()): ?>
                            <tr>
                                <td>#M-<?php echo $meal['schedule_id']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($meal['date'])); ?></td>
                                <td><?php echo ucfirst($meal['meal_time']); ?></td>
                                <td><?php echo htmlspecialchars($meal['name']); ?></td>
                                <td><?php echo $meal['quantity']; ?></td>
                                <td>৳<?php echo number_format($meal['price'], 2); ?></td>
                                <td>
                                    <?php if (strtotime($meal['date']) <= strtotime('today')): ?>
                                        <form action="update_rating.php" method="POST" class="rating-form">
                                            <input type="hidden" name="meal_id" value="<?php echo $meal['schedule_id']; ?>">
                                            <div class="star-rating">
                                                <?php for($i = 5; $i >= 1; $i--): ?>
                                                    <label>
                                                        <input type="radio" name="rating" value="<?php echo $i; ?>" 
                                                               <?php echo ($meal['rating'] == $i) ? 'checked' : ''; ?>>
                                                        <span class="star"><?php echo ($meal['rating'] >= $i) ? '★' : '☆'; ?></span>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                            <button type="submit" class="rate-btn">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="not-available-msg" title="You can rate this meal after it's been delivered">Not yet available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No meal history found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <div class="glass-card">
            <h2 class="section-heading">My Scheduled Meals</h2>
            <table class="meals-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Meal</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($meal = $scheduled_meals->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($meal['date'])); ?></td>
                            <td><span class="meal-time"><?php echo ucfirst($meal['meal_time']); ?></span></td>
                            <td><?php echo htmlspecialchars($meal['name']); ?></td>
                            <td><?php echo $meal['quantity']; ?></td>
                            <td>৳<?php echo number_format($meal['price'], 2); ?></td>
                            <td>
                                <button class="schedule-btn">Modify</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function updateTotalPrice() {
            const mealSelect = document.getElementById('meal_id');
            const quantityInput = document.getElementById('quantity');
            const totalPriceDisplay = document.getElementById('total_price');
            if (!mealSelect || !quantityInput || !totalPriceDisplay) return;
            const selectedOption = mealSelect.options[mealSelect.selectedIndex];
            const price = parseFloat(selectedOption.dataset.price) || 0;
            const quantity = parseInt(quantityInput.value) || 1;
            const total = (price * quantity).toFixed(2);
            totalPriceDisplay.textContent = '৳' + total;
        }
        document.addEventListener('DOMContentLoaded', function() {
            updateTotalPrice();
        });
        document.getElementById('meal_id').addEventListener('change', updateTotalPrice);
        document.getElementById('quantity').addEventListener('input', updateTotalPrice);

        function filterMeals() {
            const month = document.getElementById('month-filter').value;
            const mealType = document.getElementById('meal-type-filter').value;
            window.location.href = `meals.php?month=${month}&type=${mealType}`;
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
