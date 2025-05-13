<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $apartment = filter_input(INPUT_POST, 'apartment', FILTER_SANITIZE_STRING);
    
    $update_query = "UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    apartment = ?
                    WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $apartment, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating profile";
    }
    header("Location: profile.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Animation Effects */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
            animation: fadeIn 0.5s ease-in-out;
        }

        .glass-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.25);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .section-heading {
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            position: relative;
            display: inline-block;
        }

        .section-heading::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), transparent);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .glass-card:hover .section-heading::after {
            transform: scaleX(1);
        }

        .form-group {
            position: relative;
            margin-bottom: 2rem;
        }

        .form-group label {
            position: absolute;
            left: 1rem;
            top: 0.75rem;
            color: var(--primary);
            font-weight: 500;
            transition: all 0.3s ease;
            pointer-events: none;
            opacity: 0.7;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(221, 168, 83, 0.1);
            background: rgba(255, 255, 255, 0.1);
        }

        .form-control:focus + label,
        .form-control:not(:placeholder-shown) + label {
            transform: translateY(-2.5rem) scale(0.85);
            color: var(--accent);
            opacity: 1;
        }

        .submit-btn {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
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

        .submit-btn:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 10px 20px rgba(221, 168, 83, 0.2);
        }

        .submit-btn:active {
            transform: translateY(1px) scale(0.99);
        }

        .alert {
            animation: float 3s ease-in-out infinite;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        @keyframes shimmer {
            100% { left: 100%; }
        }

        @media (max-width: 768px) {
            .profile-form {
                grid-template-columns: 1fr;
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
                <a href="payments.php">Payments</a>
                <a href="profile.php" class="active">Profile</a>
                <a href="login.html">Logout</a>
            </div>
        </div>
    </nav>

    <main class="profile-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="glass-card">
            <div class="profile-header">
                <h2 class="section-heading">My Profile</h2>
            </div>
            
            <form class="profile-form" method="POST" action="profile.php">
                <div class="form-group">
                    <input type="text" id="first_name" name="first_name" class="form-control" 
                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required placeholder=" ">
                    <label for="first_name">First Name</label>
                </div>
                
                <div class="form-group">
                    <input type="text" id="last_name" name="last_name" class="form-control" 
                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required placeholder=" ">
                    <label for="last_name">Last Name</label>
                </div>
                
                <div class="form-group">
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required placeholder=" ">
                    <label for="email">Email</label>
                </div>
                
                <div class="form-group">
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($user['phone']); ?>" required placeholder=" ">
                    <label for="phone">Phone</label>
                </div>
                
                <div class="form-group">
                    <input type="text" id="apartment" name="apartment" class="form-control" 
                           value="<?php echo htmlspecialchars($user['apartment']); ?>" required placeholder=" ">
                    <label for="apartment">Apartment</label>
                </div>
                
                <button type="submit" class="submit-btn">Update Profile</button>
            </form>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
