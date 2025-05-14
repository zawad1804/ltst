<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch payment summary with balance and approved payments for current month
$summary_query = "SELECT 
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
    WHERE u.id = ?";
$stmt = $conn->prepare($summary_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// If no result, initialize with zeros
if (!$summary) {
    $summary = [
        'meal_cost' => 0,
        'total_paid' => 0
    ];
}

// Calculate remaining balance (total_paid - meal_cost)
$remaining = $summary['total_paid'] - $summary['meal_cost'];

// Fetch payment history for current month
$payment_query = "SELECT * FROM payments 
                 WHERE user_id = ? 
                 AND MONTH(payment_date) = MONTH(CURRENT_DATE())
                 AND YEAR(payment_date) = YEAR(CURRENT_DATE())
                 ORDER BY payment_date DESC";
$stmt = $conn->prepare($payment_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Payments Page Specific Styles */
        .payments-container {
            width: 100%;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .payments-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .payments-header h1 {
            color: var(--primary);
            margin-bottom: 1rem;
        }

        /* Payment Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }

        .summary-card h3 {
            margin-top: 0;
            color: var(--dark-primary);
        }

        .summary-card .amount {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .due {
            color: #e74c3c;
        }

        .paid {
            color: #2ecc71;
        }

        /* Payment Form */
        .payment-form {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 2rem;
            margin-top: 2rem;
        }

        .payment-form h2 {
            margin-top: 0;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 5px;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.1);
            color: var(--dark-primary);
        }

        .payment-methods {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .payment-method {
            flex: 1;
            min-width: 120px;
        }

        .payment-method input[type="radio"] {
            display: none;
        }

        .payment-method label {
            display: block;
            padding: 0.75rem;
            border: 1px solid var(--glass-border);
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method input[type="radio"]:checked + label {
            border-color: var(--primary);
            background: rgba(46, 204, 113, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--dark-primary);
        }

        /* Payment History Styles */
        .payment-history {
            margin-top: 3rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            padding: 2rem;
        }

        .payment-history h2 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
        }

        .history-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            font-weight: 500;
            text-align: left;
        }

        .history-table th:first-child {
            border-top-left-radius: 0.5rem;
        }

        .history-table th:last-child {
            border-top-right-radius: 0.5rem;
        }

        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.1);
        }

        .history-table tr:hover td {
            background: rgba(255, 255, 255, 0.2);
        }

        .history-table tr:last-child td:first-child {
            border-bottom-left-radius: 0.5rem;
        }

        .history-table tr:last-child td:last-child {
            border-bottom-right-radius: 0.5rem;
        }

        /* Status Badges */
        .status-pending,
        .status-approved,
        .status-rejected {
            padding: 0.4rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-align: center;
            display: inline-block;
        }

        .status-approved {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .status-pending {
            background: rgba(243, 156, 18, 0.2);
            color: #f39c12;
            border: 1px solid rgba(243, 156, 18, 0.3);
        }

        .status-rejected {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        /* Amount Column */
        .amount-cell {
            font-family: 'Monaco', monospace;
            font-weight: 500;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .payment-history {
                padding: 1rem;
            }
            
            .history-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="user-dashboard.php">Dashboard</a>
                <a href="meals.php">My Meals</a>
                <a href="inventory.php">Inventory</a>
                <a href="payments.php" class="active">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="login.html">Logout</a>
            </div>
        </div>
    </nav>

    <main class="payments-container">
        <header class="payments-header">
            <h1>Payment Management</h1>
            <p>View your payment status for <?php echo date('F Y'); ?>, make new payments, and track payment history</p>
        </header>

        <!-- Summary Cards -->
        <section class="summary-cards">
            <div class="summary-card glass-card">
                <h3>Total Meal Cost</h3>
                <div class="amount due">৳<?php echo number_format($summary['meal_cost'], 2); ?></div>
                <p>Meal cost for <?php echo date('F Y'); ?></p>
            </div>
            <div class="summary-card glass-card">
                <h3>Total Paid</h3>
                <div class="amount paid">৳<?php echo number_format($summary['total_paid'], 2); ?></div>
                <p>Approved payments for <?php echo date('F Y'); ?></p>
            </div>
            <div class="summary-card glass-card">
                <h3>Due / Credit Balance</h3>
                <div class="amount <?php echo $remaining < 0 ? 'due' : 'paid'; ?>">
                    ৳<?php echo number_format(abs($remaining), 2); ?>
                </div>
                <p><?php echo $remaining < 0 ? 'Amount to be paid' : 'Credit balance'; ?></p>
            </div>
        </section>

        <!-- Payment Form -->
        <section class="payment-form glass-card">
            <h2>Make a Payment</h2>
            <form action="process_payment.php" method="POST" id="paymentForm">
                <div class="form-group">
                    <label for="paymentAmount">Payment Amount (৳)</label>
                    <input type="number" id="paymentAmount" name="amount" class="form-control" min="1" step="0.01" required>
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-method">
                            <input type="radio" id="cash" name="payment_method" value="cash" checked>
                            <label for="cash">Cash</label>
                        </div>
                        <div class="payment-method">
                            <input type="radio" id="bkash" name="payment_method" value="bkash">
                            <label for="bkash">bKash</label>
                        </div>
                        <div class="payment-method">
                            <input type="radio" id="nagad" name="payment_method" value="nagad">
                            <label for="nagad">Nagad</label>
                        </div>
                        <div class="payment-method">
                            <input type="radio" id="card" name="payment_method" value="card">
                            <label for="card">Card</label>
                        </div>
                    </div>
                </div>

                <div class="form-group transaction-id-group" id="transactionIdGroup" style="display:none;">
                    <label for="transactionId">Transaction ID</label>
                    <input type="text" id="transactionId" name="transaction_id" class="form-control" placeholder="Enter transaction ID">
                </div>

                <button type="submit" class="btn btn-primary">Submit Payment</button>
            </form>
        </section>

        <!-- Payment History -->
        <section class="payment-history">
            <h2>Payment History for <?php echo date('F Y'); ?></h2>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Transaction ID</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments->num_rows > 0): ?>
                        <?php while($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                <td class="amount-cell">৳<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td><?php echo $payment['transaction_id'] ? $payment['transaction_id'] : 'N/A'; ?></td>
                                <td>
                                    <span class="status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No payments found for <?php echo date('F Y'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <script>
        // Show/hide transaction ID field based on payment method
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const transactionIdGroup = document.getElementById('transactionIdGroup');
                if (this.value === 'cash') {
                    transactionIdGroup.style.display = 'none';
                    document.getElementById('transactionId').required = false;
                } else {
                    transactionIdGroup.style.display = 'block';
                    document.getElementById('transactionId').required = true;
                }
            });
        });

        // Initialize form state
        document.addEventListener('DOMContentLoaded', function() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (selectedMethod && selectedMethod.value !== 'cash') {
                document.getElementById('transactionIdGroup').style.display = 'block';
                document.getElementById('transactionId').required = true;
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
