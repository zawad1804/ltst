<?php
session_start();
include 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Fetch current members with their meal counts and payment info
$current_members_query = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        COUNT(m.id) as meals_count,
        SUM(m.price) as total_due,
        (SELECT MIN(date) 
         FROM bazar_schedule bs 
         WHERE bs.user_id = u.id AND bs.date >= CURDATE()) as next_bazar
    FROM users u
    LEFT JOIN meals m ON u.id = m.user_id 
        AND MONTH(m.date) = MONTH(CURDATE()) 
        AND YEAR(m.date) = YEAR(CURDATE())
    WHERE u.role = 'member'
    GROUP BY u.id";

$current_members_result = $conn->query($current_members_query);

// Fetch members for bazar assignment dropdown
$members_query = "SELECT id, first_name, last_name FROM users WHERE role = 'member' ORDER BY first_name";
$members_result = $conn->query($members_query);

// Fetch upcoming bazar assignments
$bazar_schedule_query = "
    SELECT 
        bs.*,
        CONCAT(u.first_name, ' ', u.last_name) as member_name,
        n.id as notification_id
    FROM bazar_schedule bs
    LEFT JOIN users u ON bs.user_id = u.id
    LEFT JOIN notifications n ON n.user_id = bs.user_id 
        AND n.type = 'reminder' 
        AND n.message LIKE CONCAT('%', bs.date, '%')
    WHERE bs.date >= CURDATE()
    ORDER BY bs.date ASC";
$bazar_schedule_result = $conn->query($bazar_schedule_query);

// Fetch pending member approvals
$pending_members_query = "
    SELECT *
    FROM users 
    WHERE role = 'member'
    ORDER BY created_at DESC";
$pending_members_result = $conn->query($pending_members_query);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle bazar assignment
    if (isset($_POST['assign_bazar'])) {
        $user_id = $_POST['bazar_member'];
        $date = $_POST['bazar_date'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert bazar assignment
            $insert_bazar = "INSERT INTO bazar_schedule (user_id, date) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_bazar);
            $stmt->bind_param("is", $user_id, $date);
            $stmt->execute();
            
            // Create notification
            $notification_title = "Bazar Duty Assigned";
            $notification_message = "You have been assigned for bazar duty on " . date('Y-m-d', strtotime($date)) . ".";
            
            $insert_notification = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                                  VALUES (?, 'reminder', ?, ?, NOW())";
            $stmt = $conn->prepare($insert_notification);
            $stmt->bind_param("iss", $user_id, $notification_title, $notification_message);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            // $success_message = "Bazar turn assigned successfully!";
            
            // Refresh the bazar schedule query
            $bazar_schedule_result = $conn->query($bazar_schedule_query);
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            // $error_message = "Error assigning bazar turn: " . $e->getMessage();
        }
    }
    
    // Handle member approval/rejection
    if (isset($_POST['action'])) {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            $update_query = "UPDATE users SET role = 'member' WHERE id = ?";
        } else if ($action === 'reject') {
            $update_query = "DELETE FROM users WHERE id = ?";
        }
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $status_message = ucfirst($action) . " successful!";
        } else {
            $error_message = "Error processing request.";
        }
    }
    
    // Handle bazar cancellation
    if (isset($_POST['cancel_bazar'])) {
        $user_id = $_POST['user_id'];
        $bazar_date = $_POST['bazar_date'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete from bazar_schedule using prepared statements
            $delete_bazar = "DELETE FROM bazar_schedule 
                           WHERE user_id = ? 
                           AND date = ?";
            $stmt = $conn->prepare($delete_bazar);
            $stmt->bind_param("is", $user_id, $bazar_date);
            $stmt->execute();
            
            // Check if rows were affected
            if ($stmt->affected_rows === 0) {
                // throw new Exception("No bazar assignment found to delete");
            }
            
            // Delete from notifications using prepared statements
            $delete_notification = "DELETE FROM notifications 
                               WHERE user_id = ? 
                               AND type = 'reminder'
                               AND message LIKE CONCAT('%', ?, '%')";
            $stmt = $conn->prepare($delete_notification);
            $stmt->bind_param("is", $user_id, $bazar_date);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            // $success_message = "Bazar assignment cancelled successfully!";
            
            // Refresh the bazar schedule query
            $bazar_schedule_result = $conn->query($bazar_schedule_query);
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            // $error_message = "Error cancelling bazar assignment: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members | Meal Manager</title>
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
  
      /* Navigation Bar */
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
  
      /* Glass Card */
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
  
      /* Section Headings */
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
  
      /* Members Table */
      .members-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
      }
  
      .members-table th {
        background: var(--primary);
        color: white;
        text-align: left;
        padding: 1rem;
      }
  
      .members-table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      }
  
      .members-table tr:nth-child(even) {
        background: rgba(255, 255, 255, 0.1);
      }
  
      .members-table tr:hover {
        background: rgba(255, 255, 255, 0.2);
      }
  
      /* Buttons */
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
  
      .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
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
      .bazar-assign-form {
        display: grid;
        grid-template-columns: 1fr 1fr 2fr auto;
        gap: 1rem;
        align-items: end;
        margin-bottom: 2rem;
      }
  
      .form-group {
        margin-bottom: 0;
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
  
        .section-heading {
          font-size: 1.75rem;
        }
        
        .bazar-assign-form {
          grid-template-columns: 1fr;
        }
      }
  
      @media (max-width: 480px) {
        .section-heading {
          font-size: 1.5rem;
        }
        
        .members-table {
          display: block;
          overflow-x: auto;
        }
      }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="admin-members.php" class="active">Members</a>
                <a href="admin-inventory.php">Inventory</a>
                <a href="admin-payments.php">Payments</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Current Members Section -->
    <div class="glass-card">
        <h2 class="section-heading">Current Members</h2>
        <table class="members-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Phone</th>
                    <th>Meals This Month</th>
                    <th>Payment Due</th>
                    <th>Next Bazar</th>
                </tr>
            </thead>
            <tbody>
                <?php while($member = $current_members_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?><br>
                            <small><?php echo htmlspecialchars($member['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($member['phone']); ?></td>
                        <td><?php echo $member['meals_count']; ?></td>
                        <td>$<?php echo number_format($member['total_due'], 2); ?></td>
                        <td><?php echo $member['next_bazar'] ? date('F j, Y', strtotime($member['next_bazar'])) : 'Not Assigned'; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Bazar Assignment Section -->
    <div class="glass-card">
        <h2 class="section-heading">Assign Bazar Turn</h2>
        <div class="bazar-assign-form">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="bazar-member">Member</label>
                    <select id="bazar-member" name="bazar_member" class="form-control" required>
                        <option value="">Select Member</option>
                        <?php 
                        // Reset the members result pointer since we used it earlier
                        $members_result->data_seek(0);
                        while($member = $members_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bazar-date">Date</label>
                    <input type="date" id="bazar-date" name="bazar_date" class="form-control" required>
                </div>
                
                <button type="submit" name="assign_bazar" class="button">Assign</button>
            </form>
        </div>
        
        <h3>Upcoming Bazar Assignments</h3>
        <table class="members-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($bazar = $bazar_schedule_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bazar['member_name']); ?></td>
                        <td><?php echo date('F j, Y', strtotime($bazar['date'])); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $bazar['user_id']; ?>">
                                <input type="hidden" name="bazar_date" value="<?php echo $bazar['date']; ?>">
                                <button type="submit" name="cancel_bazar" class="button btn-sm btn-accent"
                                    onclick="return confirm('Are you sure you want to cancel this bazar assignment?');">
                                    Cancel
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pending Member Approvals Section -->
    <div class="glass-card">
        <h2 class="section-heading">Pending Member Approvals</h2>
        <table class="members-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Signup Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($pending = $pending_members_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pending['first_name'] . ' ' . $pending['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($pending['email']); ?></td>
                        <td><?php echo htmlspecialchars($pending['phone']); ?></td>
                        <td><?php echo date('F j, Y', strtotime($pending['created_at'])); ?></td>
                        <td><span class="badge badge-pending">Pending</span></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $pending['id']; ?>">
                                <button type="submit" name="action" value="approve" class="button btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject" class="button btn-sm btn-accent">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Remove the existing bazar assignment event listener and replace with this
        document.querySelector('.bazar-assign-form form').addEventListener('submit', function(e) {
            const memberSelect = document.getElementById('bazar-member');
            const dateInput = document.getElementById('bazar-date');
            
            if (!memberSelect.value || !dateInput.value) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            // Form will submit normally if validation passes
            return true;
        });

        // Approve/Reject Member buttons
        document.querySelectorAll('.button').forEach(btn => {
            if (btn.textContent === 'Approve') {
                btn.addEventListener('click', function() {
                    const row = this.closest('tr');
                    const memberName = row.querySelector('td:first-child').textContent;
                    const statusCell = row.querySelector('td:nth-child(5)');
                    
                    statusCell.innerHTML = '<span class="badge badge-approved">Approved</span>';
                    row.querySelectorAll('button').forEach(b => b.disabled = true);
                    
                    alert(`${memberName} has been approved as a new member!`);
                });
            }
            
            if (btn.textContent === 'Reject') {
                btn.addEventListener('click', function() {
                    const row = this.closest('tr');
                    const memberName = row.querySelector('td:first-child').textContent;
                    
                    if (confirm(`Are you sure you want to reject ${memberName}'s membership request?`)) {
                        row.remove();
                        alert(`${memberName}'s request has been rejected`);
                    }
                });
            }
        });

        // Assign Bazar Turn
        document.querySelector('.bazar-assign-form .button').addEventListener('click', function() {
            const memberSelect = document.getElementById('bazar-member');
            const dateInput = document.getElementById('bazar-date');
            const messageInput = document.getElementById('bazar-message');
            
            if (!memberSelect.value) {
                alert('Please select a member');
                return;
            }
            
            if (!dateInput.value) {
                alert('Please select a date');
                return;
            }
            
            const memberName = memberSelect.options[memberSelect.selectedIndex].text;
            const formattedDate = new Date(dateInput.value).toLocaleDateString();
            const message = messageInput.value || 'No message';
            
            alert(`Bazar assigned to ${memberName} on ${formattedDate}\nMessage: ${message}`);
            
            // In real app, would add to upcoming assignments table
            memberSelect.value = '';
            dateInput.value = '';
            messageInput.value = '';
        });

        // Cancel Bazar Assignment
        document.querySelectorAll('.btn-accent').forEach(btn => {
        if (btn.textContent === 'Cancel') {
            btn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent immediate form submission
                
                const row = this.closest('tr');
                const memberName = row.querySelector('td:first-child').textContent;
                
                if (confirm(`Cancel bazar assignment for ${memberName}?`)) {
                    // Submit the form normally - the page will refresh on success
                    this.closest('form').submit();
                }
            });
        }
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>