<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Update notifications query to fetch all types of notifications
$notifications_query = "SELECT title, message, type, created_at 
                       FROM notifications 
                       WHERE user_id = $user_id 
                       ORDER BY created_at DESC LIMIT 5";
$notifications_result = $conn->query($notifications_query);

// Initialize stats array
$stats = [
    'meals_taken' => 0,
    'next_bazar' => null,
    'balance' => 0
];

// Update stats query to remove total_due
$stats_query = "
    SELECT 
        u.balance,
        bs.date AS next_bazar,
        COUNT(sm.id) as meals_taken
    FROM users u
    LEFT JOIN bazar_schedule bs ON bs.user_id = u.id 
        AND bs.date >= CURDATE()
    LEFT JOIN scheduled_meals sm ON sm.user_id = u.id 
        AND MONTH(sm.scheduled_date) = MONTH(CURDATE())
    WHERE u.id = $user_id
    GROUP BY u.id, bs.date
    ORDER BY bs.date ASC
    LIMIT 1";

$stats_result = $conn->query($stats_query);

if ($stats_result && $stats_result->num_rows > 0) {
    $stats_data = $stats_result->fetch_assoc();
    $stats['balance'] = $stats_data['balance'] ?? 0;
    $stats['meals_taken'] = $stats_data['meals_taken'] ?? 0;
    $stats['next_bazar'] = $stats_data['next_bazar'];
}

// Fetch user's balance and payment information
$balance_query = "SELECT 
    u.balance,
    COALESCE(SUM(CASE WHEN p.status = 'approved' THEN p.amount ELSE 0 END), 0) as total_paid,
    COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END), 0) as pending_payments
    FROM users u 
    LEFT JOIN payments p ON p.user_id = u.id
    WHERE u.id = ?
    GROUP BY u.id, u.balance";

$stmt = $conn->prepare($balance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance_info = $stmt->get_result()->fetch_assoc();

// Make sure balance is positive for display
$current_balance = abs($balance_info['balance']); // Convert negative to positive

// Fetch recent meals
$recent_meals_query = "SELECT 
    sm.id,
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
    ORDER BY sm.scheduled_date DESC
    LIMIT 5";

$stmt = $conn->prepare($recent_meals_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_meals = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .meals-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
        }
        
        .meals-table th {
            background: var(--primary);
            color: white;
            padding: 1.2rem;
            text-align: left;
            font-weight: 500;
            border-bottom: 2px solid var(--primary);
        }
        
        .meals-table td {
            padding: 1.2rem;
            color: var(--dark-primary);
        }
        
        .meals-table tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.05);
        }

        .meals-table tr:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }

        .view-all-container {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .view-all-button {
            display: inline-block;
            padding: 0.8rem 2rem;
            background: var(--accent);
            color: var(--dark-primary);
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .view-all-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background: #c49344;
        }
        
        .balance-positive {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .balance-negative {
            color: #e74c3c;
            font-weight: bold;
        }

        .stats-section {
            padding: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            padding: 1rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.3s ease;
            position: relative;
            border-left: 4px solid var(--accent);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--accent);
        }

        /* Only change the value color for balance, keep border accent consistent */
        .balance-positive .stat-value {
            color: #2ecc71;
        }

        .balance-negative .stat-value {
            color: #e74c3c;
        }

        /* Additional styles for responsive design */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* Animation Effects */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shimmer {
            100% { left: 100%; }
        }

        /* Container Enhancement */
        .dashboard-container {
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Enhanced Glass Card */
        .glass-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.25);
        }

        /* Enhanced Stats Cards */
        .stat-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            padding: 2rem;
            border-radius: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shimmer 2s infinite;
        }

        .stat-value {
            font-size: 2.8rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--accent), var(--primary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin: 1rem 0;
        }

        /* Enhanced Table Styling */
        .meals-table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .meals-table th {
            background: none;
            color: var(--primary);
            padding: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--accent);
        }

        .meals-table tr {
            transition: all 0.3s ease;
        }

        .meals-table tr:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.01);
        }

        /* Enhanced Notification Cards */
        .notification {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .notification::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 2s infinite;
        }

        .notification:hover {
            transform: translateX(5px);
        }

        /* Modern Status Badges */
        .status-badge {
            padding: 0.6rem 1.2rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 100px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced Button Styling */
        .view-all-button {
            position: relative;
            overflow: hidden;
        }

        .view-all-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shimmer 2s infinite;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="user-dashboard.php" class="active">Dashboard</a>
                <a href="meals.php">My Meals</a>
                <a href="inventory.php">Inventory</a>
                <a href="payments.php">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="login.html">Logout</a>
            </div>
        </div>
    </nav>

    <main class="dashboard-container">
        <!-- Dashboard Header -->
        <header class="dashboard-header">
            <h1>
                <span class="welcome-text" style="color: var(--primary);">Welcome,</span>
                <span class="user-name" style="color: var(--accent);"><?php echo htmlspecialchars($user_name); ?></span>
            </h1>
            <div class="current-date"><?php echo date('F j, Y'); ?></div>
        </header>

        <!-- Notifications Section -->
        <section class="glass-card notifications-section">
            <h2 class="section-heading">Notifications</h2>
            <?php if ($notifications_result->num_rows > 0): ?>
                <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                    <div class="notification <?php echo htmlspecialchars($notification['type']); ?>">
                        <div class="notification-icon">
                            <?php
                            switch($notification['type']) {
                                case 'emergency':
                                    echo '🚨';
                                    break;
                                case 'info':
                                    echo 'ℹ️';
                                    break;
                                case 'warning':
                                    echo '⚠️';
                                    break;
                                default:
                                    echo '📢';
                            }
                            ?>
                        </div>
                        <div class="notification-content">
                            <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-time"><?php echo date('M j, Y h:i A', strtotime($notification['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No notifications available.</p>
            <?php endif; ?>
        </section>

        <!-- Monthly Overview Section -->
        <section class="glass-card stats-section">
            <h2 class="section-heading">Monthly Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['meals_taken']; ?></div>
                    <div class="stat-label">Meals Taken</div>
                </div>
                <div class="stat-card <?php echo $stats['balance'] <= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                    <div class="stat-value">
                        ৳<?php echo number_format(abs($current_balance), 2); ?>
                    </div>
                    <div class="stat-label">
                        <?php echo $stats['balance'] <= 0 ? 'Credit Balance' : 'Due Balance'; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['next_bazar'] ? date('F j', strtotime($stats['next_bazar'])) : 'N/A'; ?></div>
                    <div class="stat-label">Next Bazar</div>
                </div>
            </div>
        </section>

        <!-- Recent Meals Section -->
        <section class="glass-card recent-meals-section">
            <h2 class="section-heading">Recent Meals</h2>
            <table class="meals-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Meal Type</th>
                        <th>Meal</th>
                        <th>Quantity</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($meal = $recent_meals->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($meal['date'])); ?></td>
                            <td><?php echo ucfirst($meal['meal_time']); ?></td>
                            <td><?php echo htmlspecialchars($meal['name']); ?></td>
                            <td><?php echo $meal['quantity']; ?></td>
                            <td>৳<?php echo number_format($meal['price'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="view-all-container">
                <a href="meals.php" class="view-all-button">View All Meals</a>
            </div>
        </section>
    </main>
</body>
</html>
<?php $conn->close(); ?>