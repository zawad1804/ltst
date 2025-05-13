<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $item_name = $conn->real_escape_string($_POST['item_name']);
        $quantity = (float)$_POST['quantity'];
        $unit = $conn->real_escape_string($_POST['unit']);
        $price = (float)$_POST['price'];
        $threshold = (float)$_POST['threshold'];
        
        $sql = "INSERT INTO inventory_requests (user_id, item_name, quantity, unit, price, threshold, status) 
                VALUES ($user_id, '$item_name', $quantity, '$unit', $price, $threshold, 'pending')";
        if ($conn->query($sql)) {
            $message = "Inventory request submitted successfully!";
        } else {
            $message = "Error submitting request: " . $conn->error;
        }
    }
}

// Fetch inventory items
$inventory_query = "SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) as purchased_by 
                   FROM inventory i 
                   LEFT JOIN users u ON i.user_id = u.id 
                   WHERE i.user_id = $user_id 
                   ORDER BY i.item_name";
$inventory_result = $conn->query($inventory_query);

// Fetch pending requests
$requests_query = "SELECT * FROM inventory_requests 
                  WHERE requested_by = $user_id 
                  ORDER BY requested_at DESC";
$requests_result = $conn->query($requests_query);

// Fetch low stock alerts
$alerts_query = "SELECT * FROM inventory 
                WHERE user_id = $user_id 
                AND quantity <= threshold";
$alerts_result = $conn->query($alerts_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .bazar-container {
            width: 100%;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .bazar-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
        }
        .inventory-table th {
            background: rgba(255, 255, 255, 0.3);
            text-align: center;
            padding: 1rem;
            position: sticky;
            top: 0;
        }
        .inventory-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        .action-btns {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            padding: 1rem;
        }
        .btn-primary,
        .btn-secondary {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 150px;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-primary:hover,
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary:hover {
            background: var(--dark-primary);
        }
        
        .btn-secondary:hover {
            background: rgba(var(--primary-rgb), 0.1);
        }
        
        .status-low,
        .status-ok {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-low {
            background: rgba(255, 0, 0, 0.1);
            color: #ff3838;
        }
        
        .status-ok {
            background: rgba(0, 255, 0, 0.1);
            color: #00b894;
        }
    </style>
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="user-dashboard.php">Dashboard</a>
                <a href="meals.php">My Meals</a>
                <a href="inventory.php" class="active">Inventory</a>
                <a href="payments.php">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="login.html">Logout</a>
            </div>
        </div>
    </nav>

    <main class="bazar-container">
        <header class="bazar-header">
            <h1>Bazar Inventory</h1>
            <p>Track all grocery items, their current quantities, and purchase history. Update when new items are purchased or when quantities change.</p>
        </header>

        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity Left</th>
                        <th>Unit</th>
                        <th>Price</th>
                        <th>Last Updated</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($inventory_result && $inventory_result->num_rows > 0): ?>
                        <?php while ($item = $inventory_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo $item['unit']; ?></td>
                                <td>৳<?php echo $item['price']; ?></td>
                                <td><?php echo date('M d', strtotime($item['updated_at'])); ?></td>
                                <td>
                                    <?php if ($item['quantity'] <= $item['threshold']): ?>
                                        <span class="status-low">Low Stock</span>
                                    <?php else: ?>
                                        <span class="status-ok">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No inventory items found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="action-btns"></div>
                <button class="btn btn-primary" onclick="location.href='add_inventory_request.php'">Request New Item</button>
                <button class="btn btn-secondary" onclick="location.href='view_requests.php'">View My Requests</button>
            </div>
        </section>
    </main>
</body>
</html>
<?php $conn->close(); ?>