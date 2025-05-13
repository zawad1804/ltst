<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $quantity = (float)$_POST['quantity'];
    $unit = $conn->real_escape_string($_POST['unit']);
    $price = (float)$_POST['price'];
    $threshold = (float)$_POST['threshold'];
    
    $sql = "INSERT INTO inventory_requests (item_name, quantity, unit_type, price, threshold, status, requested_by) 
            VALUES ('$item_name', $quantity, '$unit', $price, $threshold, 'pending', $user_id)";
    
    if ($conn->query($sql)) {
        $message = "Request submitted successfully!";
    } else {
        $message = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Inventory Request | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .glass-card {
            padding: 2rem;
            border-radius: 15px;
        }
        .glass-card h2 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-grid {
            display: grid;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .form-group {
            display: grid;
            gap: 0.5rem;
        }
        .form-group label {
            font-weight: 500;
            color: var(--dark-primary);
        }
        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
            font-size: 1rem;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
        }
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .btn-primary,
        .btn-secondary {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
            flex: 1;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }
        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            text-decoration: none;
        }
        .btn-primary:hover {
            background: var(--dark-primary);
            transform: translateY(-2px);
        }
        .btn-secondary:hover {
            background: rgba(var(--primary-rgb), 0.1);
            transform: translateY(-2px);
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            background: rgba(var(--primary-rgb), 0.1);
            border-left: 4px solid var(--primary);
            color: var(--text);
        }
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-group:last-child {
                grid-column: span 2;
            }
            .button-group {
                grid-column: span 2;
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
            <h2>Request New Inventory Item</h2>
            
            <?php if ($message): ?>
                <div class="alert"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST" class="form-grid">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="item_name" required>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit" required>
                        <option value="kg">Kilograms</option>
                        <option value="g">Grams</option>
                        <option value="l">Liters</option>
                        <option value="pcs">Pieces</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (৳)</label>
                    <input type="number" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Alert Threshold</label>
                    <input type="number" name="threshold" step="0.01" required>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn-primary">Submit Request</button>
                    <a href="inventory.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
