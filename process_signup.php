<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging: Log the contents of the POST array
    error_log('POST data: ' . print_r($_POST, true));

    // Escape user inputs to prevent SQL injection
    $first_name = mysqli_real_escape_string($conn, $_POST['firstname']);
    $last_name = mysqli_real_escape_string($conn, $_POST['lastname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $apartment = mysqli_real_escape_string($conn, $_POST['apartment']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    // Validate input
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($apartment) || empty($password) || empty($role)) {
        echo 'All fields are required.';
        exit;
    }

    // Check if email already exists
    $check_query = "SELECT * FROM users WHERE email = '$email'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo 'Email already exists.';
        exit;
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into database
    $query = "INSERT INTO users (first_name, last_name, email, phone, apartment, password, role, created_at, updated_at) 
              VALUES ('$first_name', '$last_name', '$email', '$phone', '$apartment', '$hashed_password', '$role', NOW(), NOW())";

    if (mysqli_query($conn, $query)) {
        echo 'User registered successfully.';
        header('Location: login.html');
        exit;
    } else {
        // Log the error for debugging
        error_log('Database error: ' . mysqli_error($conn));
        echo 'Error: Could not register user. Please try again later.';
    }
}

mysqli_close($conn);
?>