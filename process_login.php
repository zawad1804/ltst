<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password']; // Do not escape the password here, as it will be hashed

    // Validate input
    if (empty($email) || empty($password)) {
        echo 'All fields are required.';
        exit;
    }

    // Query database for the user
    $query = "SELECT id, first_name, last_name, password, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Store user details in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name']; // Ensure this is set

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: admin-dashboard.php'); // Redirect admin to the admin dashboard
            } else {
                header('Location: user-dashboard.php'); // Redirect regular users to the user dashboard
            }
            exit;
        } else {
            echo 'Invalid password.';
        }
    } else {
        echo 'Invalid email.';
    }

    $stmt->close();
}

$conn->close();
?>
