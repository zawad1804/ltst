<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$message = '';

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        
        if ($_POST['action'] === 'approve') {
            // Fetch request details
            $request_query = "SELECT * FROM inventory_requests WHERE id = $request_id";
            $request_result = $conn->query($request_query);
            $request = $request_result->fetch_assoc();
            
            // Add/Update inventory
            $check_query = "SELECT id FROM inventory WHERE item_name = '{$request['item_name']}'";
            $check_result = $conn->query($check_query);
            
            if ($check_result->num_rows > 0) {
                // Update existing item
                $sql = "UPDATE inventory SET 
                        quantity = quantity + {$request['quantity']},
                        price = {$request['price']},
                        threshold = {$request['threshold']}
                        WHERE item_name = '{$request['item_name']}'";
            } else {
                // Add new item with user_id
                $sql = "INSERT INTO inventory (item_name, quantity, price, threshold, user_id) 
                        VALUES ('{$request['item_name']}', {$request['quantity']}, {$request['price']}, 
                                {$request['threshold']}, {$request['requested_by']})";
            }
            
            if ($conn->query($sql)) {
                $conn->query("UPDATE inventory_requests SET status = 'approved' WHERE id = $request_id");
                $message = "Request approved and inventory updated!";
            }
        } elseif ($_POST['action'] === 'reject') {
            $conn->query("UPDATE inventory_requests SET status = 'rejected' WHERE id = $request_id");
            $message = "Request rejected!";
        }
    }
}

// Fetch current inventory
$inventory_query = "SELECT * FROM inventory ORDER BY item_name";
$inventory_result = $conn->query($inventory_query);

// Fetch pending requests
$requests_query = "SELECT ir.*, CONCAT(u.first_name, ' ', u.last_name) as requester_name 
                  FROM inventory_requests ir
                  JOIN users u ON ir.requested_by = u.id
                  WHERE ir.status = 'pending'
                  ORDER BY ir.requested_at DESC";
$requests_result = $conn->query($requests_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management | Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .inventory-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            padding: 1.5rem;
            border-radius: 10px;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .btn-approve {
            background-color: #4caf50;
        }
        .btn-reject {
            background-color: #f44336;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            background: rgba(var(--primary-rgb), 0.1);
            border-left: 4px solid var(--primary);
        }
        .low-stock {
            border-left: 4px solid #f44336;
        }
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .inventory-table th {
            text-align: left;
            padding: 1rem;
            background: var(--primary);
            color: white;
            font-weight: 500;
        }
        
        .inventory-table td {
            padding: 1rem;
        }
        
        .inventory-table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .inventory-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .glass-card h2 {
            background: var(--primary);
            color: white;
            padding: 1rem;
            margin: -2rem -2rem 1rem -2rem;
            border-radius: 10px 10px 0 0;
            font-size: 1.5rem;
            text-align: center;
        }

        .status-low {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-ok {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="admin-members.php">Members</a>
                <a href="admin-inventory.php" class="active">Inventory</a>
                <a href="admin-payments.php">Payments</a>
                <a href="login.html">Logout</a>
            </div>
        </div>
    </nav>

    <main class="admin-container">
        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Pending Requests Section -->
        <section class="glass-card">
            <h2>Pending Inventory Requests</h2>
            <div class="inventory-grid">
                <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                    <?php while ($request = $requests_result->fetch_assoc()): ?>
                        <div class="inventory-card">
                            <h3><?php echo htmlspecialchars($request['item_name']); ?></h3>
                            <p>Quantity: <?php echo $request['quantity']; ?></p>
                            <p>Price: ৳<?php echo $request['price']; ?></p>
                            <p>Requested by: <?php echo $request['requester_name']; ?></p>
                            <p>Date: <?php echo date('M d, Y', strtotime($request['requested_at'])); ?></p>
                            <div class="action-buttons">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="action" value="approve" class="button btn-approve">Approve</button>
                                    <button type="submit" name="action" value="reject" class="button btn-reject">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No pending requests.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Current Inventory Section -->
        <section class="glass-card">
            <h2>Current Inventory</h2>
            <?php if ($inventory_result && $inventory_result->num_rows > 0): ?>
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Threshold</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $inventory_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>৳<?php echo $item['price']; ?></td>
                                <td><?php echo $item['threshold']; ?></td>
                                <td>
                                    <?php if ($item['quantity'] <= $item['threshold']): ?>
                                        <span class="status-low">Low Stock</span>
                                    <?php else: ?>
                                        <span class="status-ok">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No inventory items found.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
<?php $conn->close(); ?>
