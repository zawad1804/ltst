<?php
session_start();
include 'db_connect.php';

// Add status column if it doesn't exist
$alter_table = "ALTER TABLE bazar_schedule ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'assigned'";
$conn->query($alter_table);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$message = '';

// Handle bazar assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'assign') {
            $user_id = (int)$_POST['member_id'];
            $date = $conn->real_escape_string($_POST['bazar_date']);
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert bazar schedule
                $sql = "INSERT INTO bazar_schedule (user_id, date, status) VALUES ($user_id, '$date', 'assigned')";
                $conn->query($sql);
                
                // Create notification
                $formatted_date = date('Y-m-d', strtotime($date));
                $notification_sql = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                                   VALUES ($user_id, 'reminder', 'Bazar Duty Assigned', 
                                   'You have been assigned for bazar duty on $formatted_date.', NOW())";
                $conn->query($notification_sql);
                
                // Commit transaction
                $conn->commit();
                $message = "Bazar duty assigned successfully!";
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'cancel') {
            $schedule_id = (int)$_POST['schedule_id'];
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Get the user and date info before deletion
                $get_info = "SELECT user_id, date FROM bazar_schedule WHERE id = $schedule_id";
                $info_result = $conn->query($get_info);
                $info = $info_result->fetch_assoc();
                
                // Format date for notification message match
                $formatted_date = date('Y-m-d', strtotime($info['date']));
                
                // Delete bazar schedule
                $sql = "DELETE FROM bazar_schedule WHERE id = $schedule_id";
                $conn->query($sql);
                
                // Delete related notification
                $delete_notification = "DELETE FROM notifications 
                                     WHERE user_id = {$info['user_id']} 
                                     AND type = 'reminder'
                                     AND message LIKE '%$formatted_date%'";
                $conn->query($delete_notification);
                
                // Commit transaction
                $conn->commit();
                $message = "Bazar assignment cancelled!";
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch members for assignment
$members_query = "SELECT id, CONCAT(first_name, ' ', last_name) as name 
                 FROM users WHERE role = 'member' ORDER BY first_name";
$members_result = $conn->query($members_query);

// Fetch bazar schedule
$schedule_query = "SELECT bs.*, CONCAT(u.first_name, ' ', u.last_name) as member_name
                  FROM bazar_schedule bs
                  JOIN users u ON bs.user_id = u.id
                  WHERE bs.date >= CURDATE()
                  ORDER BY bs.date ASC";
$schedule_result = $conn->query($schedule_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bazar Management | Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .bazar-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .bazar-table th {
            text-align: left;
            padding: 2rem;
            border-bottom: 2px solid #27548A;
            background: none;
            color: var(--primary);
            font-weight: 600;
        }
        .bazar-table td {
            padding: 2rem;
            line-height: 1.8;
        }
        .bazar-table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            height: 5rem;
        }
        .glass-card h2 {
            color: var(--primary);
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            text-align: center;
            position: relative;
        }
        .glass-card h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--accent);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-control {
            padding: 0.75rem;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
        }
        .btn-assign {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-cancel {
            background: #f44336;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="admin-inventory.php">Inventory</a>
                <a href="admin-members.php">Members</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <main class="admin-container">
        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <section class="glass-card">
            <h2>Assign Bazar Duty</h2>
            <form method="POST" class="form-grid">
                <div class="form-group">
                    <label>Select Member</label>
                    <select name="member_id" class="form-control" required>
                        <option value="">Choose member...</option>
                        <?php while($member = $members_result->fetch_assoc()): ?>
                            <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="bazar_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <button type="submit" name="action" value="assign" class="btn-assign">Assign Bazar</button>
                </div>
            </form>
        </section>

        <section class="glass-card">
            <h2>Upcoming Bazar Schedule</h2>
            <table class="bazar-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Member</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($schedule_result && $schedule_result->num_rows > 0): ?>
                        <?php while ($schedule = $schedule_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('F j, Y', strtotime($schedule['date'])); ?></td>
                                <td><?php echo htmlspecialchars($schedule['member_name']); ?></td>
                                <td><?php echo ucfirst($schedule['status']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                        <button type="submit" name="action" value="cancel" class="btn-cancel">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No upcoming bazar assignments.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
<?php $conn->close(); ?>
