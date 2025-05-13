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
    m.rating
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
    sm.total_price as price,
    m.rating
    FROM scheduled_meals sm
    JOIN meals m ON sm.meal_id = m.id
    WHERE sm.user_id = $user_id 
    AND sm.status = 'active'
    ORDER BY sm.scheduled_date DESC";
$scheduled_meals = $conn->query($scheduled_meals_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Meals | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="user-dashboard.php">Dashboard</a>
                <a href="meals.php" class="active">My Meals</a>
                <a href="inventory.php">Inventory</a>
                <a href="payments.php">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <main class="meals-container">
        <header class="meals-header">
            <h1>My Meal History</h1>
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
        </header>

        <section class="glass-card meal-scheduler">
            <h2 class="section-heading">Schedule Your Meal</h2>
            <form action="schedule_meal.php" method="POST" class="scheduler-form" onsubmit="return validateForm()">
                <div class="meal-grid">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="meal_date" id="meal_date" class="form-control" required min="<?php echo $today; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Meal Time</label>
                        <select name="meal_time" id="meal_time" class="form-control" required>
                            <option value="">Select Time</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Meal</label>
                        <select name="meal_id" id="meal_id" class="form-control" required onchange="updatePrice()">
                            <option value="">Choose a meal</option>
                            <?php while($meal = $available_meals->fetch_assoc()): ?>
                                <option value="<?php echo $meal['id']; ?>" data-price="<?php echo $meal['price']; ?>">
                                    <?php echo htmlspecialchars($meal['name']); ?> (৳<?php echo number_format($meal['price'], 2); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="1" required onchange="updatePrice()">
                    </div>
                </div>
                
                <div class="total-price-display">
                    Total Price: <span id="total_price">৳0.00</span>
                </div>
                
                <button type="submit" class="schedule-btn">Schedule Meal</button>
            </form>
        </section>

        <section class="glass-card meals-list">
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
                        <th>Action</th>
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
                                    <div class="rating-options">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <input type="radio" 
                                                id="meal<?php echo $meal['schedule_id']; ?>-rating<?php echo $i; ?>" 
                                                name="meal<?php echo $meal['schedule_id']; ?>-rating" 
                                                value="<?php echo $i; ?>"
                                                <?php echo ($meal['rating'] == $i) ? 'checked' : ''; ?>>
                                            <label for="meal<?php echo $meal['schedule_id']; ?>-rating<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="update-btn" 
                                            onclick="updateRating(<?php echo $meal['schedule_id']; ?>)">
                                        Update
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No meal history found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <script>
        // Price calculation
        document.getElementById('meal-name').addEventListener('change', calculateTotal);
        document.getElementById('meal-quantity').addEventListener('input', calculateTotal);

        function calculateTotal() {
            const mealSelect = document.getElementById('meal-name');
            const quantity = document.getElementById('meal-quantity').value;
            const price = mealSelect.options[mealSelect.selectedIndex].dataset.price || 0;
            const total = (price * quantity).toFixed(2);
            document.getElementById('total-price').textContent = '৳' + total;
        }

        // Rating update
        function updateRating(mealId) {
            const rating = document.querySelector(`input[name="meal${mealId}-rating"]:checked`)?.value;
            if (!rating) return;

            fetch('update_rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `meal_id=${mealId}&rating=${rating}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Rating updated successfully!');
                }
            });
        }

        // Meal filtering
        function filterMeals() {
            const month = document.getElementById('month-filter').value;
            const mealType = document.getElementById('meal-type-filter').value;
            window.location.href = `meals.php?month=${month}&type=${mealType}`;
        }

        // Add JavaScript for dynamic meal loading
        function updateAvailableMeals() {
            const selectedDate = document.getElementById('meal-date').value;
            fetch('get_available_meals.php?date=' + selectedDate)
                .then(response => response.json())
                .then(meals => {
                    const mealSelect = document.getElementById('meal-name');
                    mealSelect.innerHTML = '<option value="">Select Meal</option>';
                    meals.forEach(meal => {
                        mealSelect.innerHTML += `<option value="\${meal.id}" data-price="\${meal.price}">
                            \${meal.name} (৳\${meal.price})
                        </option>`;
                    });
                });
        }

        function validateForm() {
            console.log('Form submitted');
            return true;
        }

        function updatePrice() {
            const mealSelect = document.getElementById('meal_id');
            const quantity = document.getElementById('quantity').value;
            const price = mealSelect.options[mealSelect.selectedIndex].dataset.price || 0;
            const total = (price * quantity).toFixed(2);
            document.getElementById('total_price').textContent = '৳' + total;
        }
    </script>
</body>
</html>