<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Assign 'pending' role to new users
    $role = 'pending';

    $insert_user_query = "INSERT INTO users (first_name, last_name, email, phone, password, role, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($insert_user_query);
    $stmt->bind_param("ssssss", $first_name, $last_name, $email, $phone, $password, $role);

    if ($stmt->execute()) {
        header('Location: login.html?signup=success');
        exit();
    } else {
        $error_message = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup | Meal Manager</title>
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

        .glass-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            padding: 2rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 400px;
            margin: 2rem auto;
        }

        h2 {
            text-align: center;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 1rem;
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
            text-align: center;
        }

        .button:hover {
            background-color: var(--dark-primary);
            transform: translateY(-2px);
        }

        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="glass-card">
        <h2>Signup</h2>
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="button">Signup</button>
        </form>
    </div>
</body>
</html>