<?php
session_start();
require 'connect.php';

// Define error and success messages
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        // Registration logic
        $username = $_POST['username'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate registration data
        if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $users = getMongoDBConnection('users');
            $existing_user = $users->findOne(['email' => $email]);

            if ($existing_user) {
                $error = "User already exists.";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insert into MongoDB
                $result = $users->insertOne([
                    'username' => $username,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'password' => $hashed_password,
                    'xp' => 0,
                    'friends' => []
                ]);

                // Store user id in session
                $_SESSION['user_id'] = (string)$result->getInsertedId();
                $success = "Registration successful. You are now logged in.";
                header('Location: index.php');
                exit;
            }
        }
    } elseif (isset($_POST['login'])) {
        // Login logic
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Validate login data
        if (empty($username) || empty($password)) {
            $error = "Username and password are required.";
        } else {
            $users = getMongoDBConnection('users');
            $user = $users->findOne(['username' => $username]);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = (string)$user['_id'];
                header('Location: index.php');
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login/Register</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<h1>Welcome! Please log in or register.</h1>

<!-- Show success message if registration is successful -->
<?php if ($success) { echo "<p style='color: green;'>$success</p>"; } ?>

<!-- Show error messages -->
<?php if ($error) { echo "<p style='color: red;'>$error</p>"; } ?>

<!-- Login Form -->
<h2>Login</h2>
<form method="POST">
    <input type="text" name="username" placeholder="Username"><br>
    <input type="password" name="password" placeholder="Password"><br>
    <button type="submit" name="login">Login</button>
</form>

<hr>

<!-- Registration Form -->
<h2>Register</h2>
<form method="POST">
    <input type="text" name="username" placeholder="Username"><br>
    <input type="text" name="first_name" placeholder="First Name"><br>
    <input type="text" name="last_name" placeholder="Last Name"><br>
    <input type="email" name="email" placeholder="Email"><br>
    <input type="password" name="password" placeholder="Password"><br>
    <input type="password" name="confirm_password" placeholder="Confirm Password"><br>
    <button type="submit" name="register">Register</button>
</form>
</body>
</html>
