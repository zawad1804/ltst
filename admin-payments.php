<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all payments with user details for current month
$payments_query = "SELECT p.*, 
                         CONCAT(u.first_name, ' ', u.last_name) as user_name,
                         u.balance as user_balance
                  FROM payments p 
                  JOIN users u ON p.user_id = u.id 
                  WHERE MONTH(p.payment_date) = MONTH(CURRENT_DATE())
                  AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())
                  ORDER BY p.payment_date DESC";
$payments_result = $conn->query($payments_query);

// Updated unpaid members query for current month
$unpaid_members_query = "SELECT DISTINCT u.id, u.first_name, u.last_name, 
                        (SELECT COALESCE(SUM(sm.total_price), 0)
                         FROM scheduled_meals sm
                         WHERE sm.user_id = u.id
                         AND MONTH(sm.scheduled_date) = MONTH(CURRENT_DATE())
                         AND YEAR(sm.scheduled_date) = YEAR(CURRENT_DATE())) as meal_cost,
                        (SELECT COALESCE(SUM(p.amount), 0)
                         FROM payments p
                         WHERE p.user_id = u.id
                         AND p.status = 'approved'
                         AND MONTH(p.payment_date) = MONTH(CURRENT_DATE())
                         AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())) as total_paid
                        FROM users u 
                        LEFT JOIN (
                            SELECT user_id 
                            FROM payments 
                            WHERE status = 'approved'
                            AND MONTH(payment_date) = MONTH(CURRENT_DATE())
                            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
                        ) current_month ON u.id = current_month.user_id
                        WHERE u.role = 'member' 
                        AND current_month.user_id IS NULL
                        ORDER BY u.first_name";

$unpaid_members_result = $conn->query($unpaid_members_query);

// Handle payment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = $_POST['payment_id'];
    $user_id = $_POST['user_id'];
    $amount = $_POST['amount'];
    $action = $_POST['action'];

    $conn->begin_transaction();
    
    try {
        if ($action === 'approve') {
            // Update payment status
            $update_payment = "UPDATE payments SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($update_payment);
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();

            // Update user balance
            $update_balance = "UPDATE users SET balance = balance - ? WHERE id = ?";
            $stmt = $conn->prepare($update_balance);
            $stmt->bind_param("di", $amount, $user_id);
            $stmt->execute();

            $_SESSION['success'] = "Payment approved successfully";
        } else {
            // Reject payment
            $update_payment = "UPDATE payments SET status = 'rejected' WHERE id = ?";
            $stmt = $conn->prepare($update_payment);
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();

            $_SESSION['success'] = "Payment rejected";
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
    }
    
    header("Location: admin-payments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management | Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Container Styling */
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Glass Card Enhancement */
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

        /* Table Styling Enhancement */
        .payments-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
        }

        .payments-table th {
            text-align: left;
            padding: 1.5rem 2rem;
            background: none;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-bottom: 2px solid var(--accent);
        }

        .payments-table tr {
            transition: all 0.3s ease;
        }

        .payments-table tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.03);
        }

        .payments-table tr:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.01);
        }

        .payments-table td {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        /* Status Badge Enhancements */
        .status-pending,
        .status-approved,
        .status-rejected {
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
        }

        .status-pending::before,
        .status-approved::before,
        .status-rejected::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            100% { left: 100%; }
        }

        /* Enhanced Button Styling */
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            padding: 0.5rem;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-approve,
        .btn-reject,
        .btn-remind {
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            min-width: 120px;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Amount Styling */
        td:nth-child(2) {
            font-family: 'Monaco', monospace;
            font-weight: 600;
            color: var(--accent);
        }

        /* Transaction ID Styling */
        td:nth-child(4) {
            font-family: 'Consolas', monospace;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Date Styling */
        td:nth-child(5) {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Empty State Styling */
        tr td[colspan] {
            text-align: center;
            padding: 3rem !important;
            color: var(--text-light);
            font-style: italic;
        }

        .status-pending { 
            background: rgba(255, 165, 0, 0.1);
            color: #ffa500;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.875rem;
        }
        .status-approved { color: #4caf50; }
        .status-rejected { color: #f44336; }
        .btn-approve,
        .btn-reject {
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            margin: 0 0.25rem;
            transition: transform 0.2s;
        }
        
        .btn-approve:hover,
        .btn-reject:hover {
            transform: translateY(-2px);
        }

        /* Action Buttons Container */
        .action-buttons {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(5px);
        }

        /* Action Buttons Styling */
        .btn-approve,
        .btn-reject {
            padding: 0.6rem 1.4rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            min-width: 110px;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-approve {
            background: linear-gradient(145deg, rgba(46, 204, 113, 0.1), rgba(46, 204, 113, 0.2));
            color: #2ecc71;
            border-color: #2ecc71;
        }

        .btn-reject {
            background: linear-gradient(145deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.2));
            color: #e74c3c;
            border-color: #e74c3c;
        }

        .btn-approve:hover {
            background: #2ecc71;
            color: white;
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
        }

        .btn-reject:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-approve:active,
        .btn-reject:active {
            transform: translateY(0) scale(0.98);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
        }

        /* Status Badge Enhancements */
        .status-pending,
        .status-approved,
        .status-rejected {
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .status-pending {
            background: linear-gradient(145deg, rgba(255, 165, 0, 0.1), rgba(255, 165, 0, 0.2));
            color: #ffa500;
            border: 2px solid #ffa500;
        }

        .status-approved {
            background: linear-gradient(145deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.2));
            color: #4caf50;
            border: 2px solid #4caf50;
        }

        .status-rejected {
            background: linear-gradient(145deg, rgba(244, 67, 54, 0.1), rgba(244, 67, 54, 0.2));
            color: #f44336;
            border: 2px solid #f44336;
        }

        /* Balance styling */
        .due {
            color: #e74c3c;
            font-weight: 600;
        }

        .paid {
            color: #2ecc71;
            font-weight: 600;
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
                <a href="admin-inventory.php">Inventory</a>
                <a href="admin-payments.php" class="active">Payments</a>
                <a href="login.html">Logout</a>
            </div>
        </div>
    </nav>

    <main class="admin-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <section class="glass-card">
            <h2>Payment Requests for <?php echo date('F Y'); ?></h2>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Transaction ID</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments_result && $payments_result->num_rows > 0): ?>
                        <?php while ($payment = $payments_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['user_name']); ?></td>
                                <td>৳<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <span class="status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $payment['user_id']; ?>">
                                                <input type="hidden" name="amount" value="<?php echo $payment['amount']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                                                <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No payment requests found for <?php echo date('F Y'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="glass-card">
            <h2>Members Without Payment for <?php echo date('F Y'); ?></h2>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Meal Cost</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Last Payment Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($unpaid_members_result && $unpaid_members_result->num_rows > 0): ?>
                        <?php while ($member = $unpaid_members_result->fetch_assoc()): 
                            // Get last payment date
                            $last_payment_query = "SELECT payment_date 
                                                 FROM payments 
                                                 WHERE user_id = ? AND status = 'approved'
                                                 ORDER BY payment_date DESC LIMIT 1";
                            $stmt = $conn->prepare($last_payment_query);
                            $stmt->bind_param("i", $member['id']);
                            $stmt->execute();
                            $last_payment = $stmt->get_result()->fetch_assoc();
                            
                            $balance = $member['meal_cost'] - $member['total_paid'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                <td>৳<?php echo number_format($member['meal_cost'], 2); ?></td>
                                <td>৳<?php echo number_format($member['total_paid'], 2); ?></td>
                                <td class="<?php echo $balance > 0 ? 'due' : 'paid'; ?>">
                                    ৳<?php echo number_format(abs($balance), 2); ?>
                                </td>
                                <td><?php echo $last_payment ? date('M d, Y', strtotime($last_payment['payment_date'])) : 'No payments'; ?></td>
                                <td>
                                    <button class="btn-remind" onclick="sendReminder(<?php echo $member['id']; ?>)">
                                        Send Reminder
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">All members have paid for <?php echo date('F Y'); ?>!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <script>
        function sendReminder(userId) {
            if (confirm('Send payment reminder to this member?')) {
                // In a real implementation, you would make an AJAX call here
                alert('Reminder sent to member with ID: ' + userId);
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>