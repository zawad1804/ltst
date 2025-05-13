<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Fetch data for dashboard cards
$pending_payments_query = "SELECT COUNT(*) AS count FROM payments WHERE status = 'pending'";
$pending_payments_result = $conn->query($pending_payments_query);
$pending_payments = $pending_payments_result->fetch_assoc()['count'];

$upcoming_bazar_query = "SELECT COUNT(*) AS count FROM bazar_schedule WHERE date >= CURDATE()";
$upcoming_bazar_result = $conn->query($upcoming_bazar_query);
$upcoming_bazar = $upcoming_bazar_result->fetch_assoc()['count'];

$inventory_requests_query = "SELECT COUNT(*) AS count FROM inventory_requests WHERE status = 'pending'";
$inventory_requests_result = $conn->query($inventory_requests_query);
$inventory_requests = $inventory_requests_result ? $inventory_requests_result->fetch_assoc()['count'] : 0;

// Show only members (not admins)
$active_users_query = "SELECT COUNT(*) AS count FROM users WHERE role = 'member'";
$active_users_result = $conn->query($active_users_query);
$active_users = $active_users_result->fetch_assoc()['count'];

// Update recent payments query to show pending first
$recent_payments_query = "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as member_name 
                         FROM payments p 
                         JOIN users u ON p.user_id = u.id 
                         WHERE p.status = 'pending'
                         ORDER BY p.payment_date DESC";
$recent_payments_result = $conn->query($recent_payments_query);

// Fetch upcoming bazar schedule
$bazar_schedule_query = "SELECT bs.*, CONCAT(u.first_name, ' ', u.last_name) as member_name 
                        FROM bazar_schedule bs 
                        LEFT JOIN users u ON bs.user_id = u.id 
                        ORDER BY bs.date ASC LIMIT 5";
$bazar_schedule_result = $conn->query($bazar_schedule_query);

// Fetch inventory items for meal creation
$inventory_query = "SELECT item_name, quantity, price 
                   FROM inventory 
                   ORDER BY item_name";
$inventory_result = $conn->query($inventory_query);

// Fetch pending inventory requests
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
  <title>Admin Dashboard | Meal Manager</title>
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
      display: flex;
      flex-direction: column;
      align-items: center;
      min-height: 100vh;
    }

    /* Navigation Bar - Matching Other Pages */
    nav.glass-card {
      width: 100%;
      border-radius: 0;
      margin-top: 0;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .nav-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 2rem;
      max-width: 1200px;
      margin: 0 auto;
    }

    .nav-logo {
      font-weight: bold;
      font-size: 1.5rem;
      text-decoration: none;
      color: var(--primary);
    }

    .nav-links {
      display: flex;
      gap: 1.5rem;
    }

    .nav-links a {
      text-decoration: none;
      color: var(--dark-primary);
      font-weight: 500;
      transition: color 0.3s;
    }

    .nav-links a:hover, .nav-links a.active {
      color: var(--accent);
      text-decoration: none;
    }

    /* Glass Card - Matching Other Pages */
    .glass-card {
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      border-radius: 1rem;
      padding: 2rem;
      backdrop-filter: blur(10px);
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
      width: 90%;
      max-width: 1200px;
      margin: 1rem auto;
    }

    /* Admin Dashboard Container */
    .admin-container {
      width: 100%;
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 1rem;
    }

    /* Section Headings - Matching Other Pages */
    .section-heading {
      text-align: center;
      margin-bottom: 2rem;
      color: var(--primary);
      font-size: 2rem;
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

    /* Dashboard Grid */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-bottom: 3rem;
    }

    .dashboard-card {
      padding: 1.5rem;
      border-radius: 0.75rem;
      transition: transform 0.3s;
      background: rgba(255, 255, 255, 0.1);
      border-left: 4px solid var(--accent);
    }

    .dashboard-card:hover {
      transform: translateY(-5px);
    }

    .card-value {
      font-size: 2.5rem;
      font-weight: bold;
      margin: 0.5rem 0;
      color: var(--accent);
    }

    /* Tables */
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }

    .admin-table th {
      background: var(--primary);
      color: white;
      text-align: left;
      padding: 1rem;
    }

    .admin-table td {
      padding: 1rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .admin-table tr:nth-child(even) {
      background: rgba(255, 255, 255, 0.1);
    }

    .admin-table tr:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    /* Buttons - Matching Other Pages */
    .button {
      display: inline-block;
      padding: 0.75rem 1.5rem;
      border-radius: 25px;
      text-decoration: none;
      color: white;
      background-color: var(--primary);
      transition: all 0.3s ease;
      font-weight: 500;
      border: none;
      cursor: pointer;
    }

    .button:hover {
      background-color: var(--dark-primary);
      transform: translateY(-2px);
    }

    .btn-accent {
      background-color: var(--accent);
      color: var(--dark-primary);
    }

    .btn-accent:hover {
      background-color: #c49344;
    }

    /* Status Badges */
    .badge {
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 500;
      display: inline-block;
    }

    .badge-pending {
      background-color: rgba(255, 193, 7, 0.2);
      color: #ff9800;
    }

    .badge-approved {
      background-color: rgba(76, 175, 80, 0.2);
      color: #4caf50;
    }

    /* Forms */
    .notification-form {
      background: rgba(255, 255, 255, 0.1);
      padding: 2rem;
      border-radius: 0.75rem;
      margin-bottom: 2rem;
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
      padding: 0.75rem;
      border: 1px solid var(--glass-border);
      border-radius: 5px;
      background: rgba(255, 255, 255, 0.2);
      color: var(--dark-primary);
      max-width: 100%;
    }

    textarea.form-control {
      min-height: 120px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .nav-container {
        flex-direction: column;
        padding: 1rem;
      }

      .nav-links {
        margin-top: 1rem;
        flex-wrap: wrap;
        justify-content: center;
      }

      .dashboard-grid {
        grid-template-columns: 1fr;
      }

      .section-heading {
        font-size: 1.75rem;
      }
    }

    @media (max-width: 480px) {
      .section-heading {
        font-size: 1.5rem;
      }
      
      .admin-table {
        display: block;
        overflow-x: auto;
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

    /* Enhanced Container */
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

    /* Dashboard Cards Enhancement */
    .dashboard-card {
        position: relative;
        overflow: hidden;
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        border-radius: 15px;
        padding: 2rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .dashboard-card::before {
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

    .dashboard-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 20px 40px rgba(31, 38, 135, 0.15);
    }

    .card-value {
        font-size: 2.8rem;
        font-weight: bold;
        background: linear-gradient(45deg, var(--accent), var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin: 1rem 0;
    }

    /* Enhanced Table Styles */
    .meals-table, .inventory-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .meals-table th, .inventory-table th {
        padding: 1.5rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--accent);
    }

    .meals-table tr, .inventory-table tr {
        transition: all 0.3s ease;
    }

    .meals-table tr:hover, .inventory-table tr:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: scale(1.01);
    }

    /* Action Buttons */
    .button {
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

    .button::before {
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

    /* Status Badges */
    .badge {
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
  </style>
</head>
<body>
  <!-- Navigation Bar -->
  <nav class="glass-card">
    <div class="nav-container">
      <a href="index.html" class="nav-logo">Meal Manager</a>
      <div class="nav-links">
        <a href="admin-dashboard.php" class="active">Dashboard</a>
        <a href="admin-members.php">Members</a>
        <a href="admin-inventory.php">Inventory</a>
        <a href="admin-payments.php">Payments</a>
        <a href="login.html">Logout</a>
      </div>
    </div>
  </nav>

  <div class="admin-container">
    <!-- Dashboard Overview -->
    <h2 class="section-heading">Admin Dashboard</h2>
    
    <div class="dashboard-grid">
      <div class="glass-card dashboard-card">
        <h3>Pending Payments</h3>
        <div class="card-value"><?php echo $pending_payments; ?></div>
        <p>Require your approval</p>
        <a href="admin-payments.php" class="button">Review</a>
      </div>
      <div class="glass-card dashboard-card">
        <h3>Bazar Assignments</h3>
        <div class="card-value"><?php echo $upcoming_bazar; ?></div>
        <p>Upcoming assignments</p>
        <a href="admin-bazar.php" class="button btn-accent">Manage</a>
      </div>
      <div class="glass-card dashboard-card">
        <h3>Inventory Updates</h3>
        <div class="card-value"><?php echo $inventory_requests; ?></div>
        <p>Waiting for approval</p>
        <a href="admin-inventory.php" class="button">Review</a>
      </div>
      <div class="glass-card dashboard-card">
        <h3>Active Members</h3>
        <div class="card-value"><?php echo $active_users; ?></div>
        <p>Currently active</p>
        <a href="admin-members.php" class="button btn-accent">Manage</a>
      </div>
    </div>

    <!-- Meal Creation Section -->
    <div class="glass-card">
      <h2 class="section-heading">Create New Meal</h2>
      <div class="meal-form">
        <form id="mealCreationForm" method="POST" action="create_meal.php">
          <div class="form-group">
            <label for="meal-date">Date</label>
            <input type="date" id="meal-date" name="meal_date" class="form-control" required>
          </div>
          
          <div class="form-group">
            <label for="meal-name">Meal Name</label>
            <input type="text" id="meal-name" name="meal_name" class="form-control" placeholder="e.g., Lunch, Dinner, Special Meal" required>
          </div>
          
          <div class="form-group">
            <label for="meal-price">Price per Person ($)</label>
            <input type="number" id="meal-price" name="meal_price" class="form-control" min="0" step="0.01" required>
          </div>
          
          <div class="form-group">
            <label>Select Items for this Meal</label>
            <div class="item-selection-container">
              <select id="inventory-items" class="form-control item-select">
                <option value="">Select an item from inventory</option>
                <?php while($item = $inventory_result->fetch_assoc()): ?>
                  <option value="<?php echo $item['item_name']; ?>">
                    <?php echo $item['item_name'] . " (Available: " . $item['quantity'] . ")"; ?>
                  </option>
                <?php endwhile; ?>
              </select>
              <button type="button" id="add-item-btn" class="button add-item-btn">Add Item</button>
            </div>
            
            <div class="selected-items-list" id="selected-items-container">
              <p class="no-items-message">No items selected yet</p>
            </div>
          </div>
          
          <div class="form-group">
            <label for="meal-notes">Additional Notes (Optional)</label>
            <textarea id="meal-notes" name="meal_notes" class="form-control" rows="3"></textarea>
          </div>
          
          <button type="submit" class="button">Create Meal</button>
        </form>
      </div>
    </div>

    <!-- Notification System -->
    <div class="glass-card">
      <h2 class="section-heading">Send Notification</h2>
      <div class="notification-form">
        <form id="notificationForm" method="POST" action="send_notification.php">
          <div class="form-group">
            <label for="notification-title">Notification Title</label>
            <input type="text" id="notification-title" name="title" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="notification-message">Message</label>
            <textarea id="notification-message" name="message" class="form-control" required></textarea>
          </div>
          <div class="form-group">
            <label for="notification-priority">Priority</label>
            <select id="notification-priority" name="priority" class="form-control">
              <option value="normal">Normal</option>
              <option value="important">Important</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <button type="submit" class="button">Send to All Members</button>
        </form>
      </div>
    </div>

    <!-- Payment Approvals Section -->
    <div class="glass-card">
      <h2 class="section-heading">Pending Payment Requests</h2>
      <table class="admin-table">
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
          <?php while($payment = $recent_payments_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($payment['member_name']); ?></td>
              <td>৳<?php echo number_format($payment['amount'], 2); ?></td>
              <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
              <td><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></td>
              <td><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></td>
              <td><span class="badge badge-<?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status']); ?></span></td>
              <td>
                <?php if ($payment['status'] == 'pending'): ?>
                  <form method="POST" action="approve_payment.php" style="display: inline;">
                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $payment['user_id']; ?>">
                    <input type="hidden" name="amount" value="<?php echo $payment['amount']; ?>">
                    <button type="submit" name="action" value="approve" class="button">Approve</button>
                    <button type="submit" name="action" value="reject" class="button btn-accent">Reject</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- Bazar Turn Assignment -->
    <div class="glass-card">
      <h2 class="section-heading">Upcoming Bazar Schedule</h2>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Member</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while($bazar = $bazar_schedule_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo date('F j, Y', strtotime($bazar['date'])); ?></td>
              <td><?php echo htmlspecialchars($bazar['member_name'] ?? 'Not Assigned'); ?></td>
              <td><span class="badge badge-<?php echo $bazar['status']; ?>"><?php echo ucfirst($bazar['status']); ?></span></td>
              <td>
                <?php if ($bazar['status'] == 'pending'): ?>
                  <button class="button">Assign</button>
                <?php else: ?>
                  <button class="button btn-accent">Reassign</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    // Add JavaScript for handling meal item selection
    document.getElementById('add-item-btn').addEventListener('click', function() {
        const select = document.getElementById('inventory-items');
        const container = document.getElementById('selected-items-container');
        const noItemsMessage = container.querySelector('.no-items-message');
        
        if (select.value) {
            if (noItemsMessage) {
                noItemsMessage.remove();
            }
            
            const itemDiv = document.createElement('div');
            itemDiv.className = 'selected-item';
            itemDiv.innerHTML = `
                <span>${select.options[select.selectedIndex].text}</span>
                <div class="item-quantity">
                    <input type="number" class="quantity-input" name="quantities[]" value="1" min="1">
                    <input type="hidden" name="items[]" value="${select.value}">
                    <span class="remove-item">×</span>
                </div>
            `;
            
            container.appendChild(itemDiv);
            
            itemDiv.querySelector('.remove-item').addEventListener('click', function() {
                itemDiv.remove();
                if (container.children.length === 0) {
                    container.innerHTML = '<p class="no-items-message">No items selected yet</p>';
                }
            });
        }
    });
  </script>
</body>
</html>
<?php $conn->close(); ?>