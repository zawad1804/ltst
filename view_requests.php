<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$requests_query = "SELECT * FROM inventory_requests WHERE requested_by = $user_id ORDER BY requested_at DESC";
$requests_result = $conn->query($requests_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Requests | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .request-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
        }
        .request-table th {
            background: rgba(255, 255, 255, 0.3);
            text-align: center;
            padding: 1rem;
            position: sticky;
            top: 0;
        }
        .request-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending {
            background: rgba(255, 165, 0, 0.1);
            color: #ffa500;
        }
        .status-approved {
            background: rgba(0, 255, 0, 0.1);
            color: #00b894;
        }
        .status-rejected {
            background: rgba(255, 0, 0, 0.1);
            color: #ff3838;
        }
        .back-btn {
            display: inline-block;
            margin: 1rem 0;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: var(--dark-primary);
            transform: translateY(-2px);
        }
        .glass-card {
            background: #F3F3E0;
            backdrop-filter: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="user-dashboard.php">Dashboard</a>
                <a href="meals.html">My Meals</a>
                <a href="inventory.php">Inventory</a>
                <a href="payments.php">Payments</a>
                <a href="profile.html">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="glass-card">
            <h2>My Inventory Requests</h2>
            <a href="inventory.php" class="back-btn">Back to Inventory</a>

            <table class="request-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Price</th>
                        <th>Requested Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                        <?php while ($request = $requests_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                                <td><?php echo $request['quantity']; ?></td>
                                <td><?php echo $request['unit_type']; ?></td>
                                <td>৳<?php echo $request['price']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['requested_at'])); ?></td>
                                <td>
                                    <span class="status status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
