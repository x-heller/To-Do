<?php
session_start();
require 'connect.php';

// Define error and success messages
$error = '';
$success = '';
$show_register_form = false; // Control which form is shown
$registration_successful = false; // Flag for successful registration

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
            $show_register_form = true; // Stay on register form
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
            $show_register_form = true; // Stay on register form
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
            $show_register_form = true; // Stay on register form
        } else {
            $users = getMongoDBConnection('users');
            $existing_user = $users->findOne(['email' => $email]);

            if ($existing_user) {
                $error = "User already exists.";
                $show_register_form = true; // Stay on register form
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
                    'friends' => [],
                    'groups' => [],
                    'verification' => false,
                    'profile_picture' => 'Images/default_avatar.jpg'
                ]);

//                $registration_successful = true; // Registration succeeded
                header("Location: welcome.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Register</title>
    <link rel="stylesheet" href="Styles/welcome.css">
    <style>
        /* Add your CSS here (from previous examples) */
    </style>
</head>
<body>

<h1>Welcome! Please log in or register.</h1>

<!-- Success Modal -->
<div id="success-modal" class="modal">
    <div class="modal-content">
        <p>Registration successful! Please log in to continue.</p>
        <button id="modal-ok">OK</button>
    </div>
</div>

<!-- Show error messages -->
<?php if ($error) { echo "<p style='color: red;'>$error</p>"; } ?>

<!-- Login Form -->
<div id="login-form" class="form-container <?php if ($show_register_form || $registration_successful) echo 'hidden'; ?>">
    <h2>Login</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Username"><br>
        <input type="password" name="password" placeholder="Password"><br>
        <button type="submit" name="login">Login</button>
    </form>
    <p>Don't have an account? <a href="#" id="show-register">Register</a></p>
</div>

<!-- Registration Form -->
<div id="register-form" class="form-container <?php if (!$show_register_form && !$registration_successful) echo 'hidden'; ?>">
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
    <p>Already have an account? <a href="#" id="show-login">Login</a></p>
</div>

<script>
    // JavaScript to toggle forms
    document.getElementById('show-register').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.remove('hidden');
    });

    document.getElementById('show-login').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('login-form').classList.remove('hidden');
    });

    // Show the modal if registration was successful
    <?php if ($registration_successful): ?>
    document.getElementById('success-modal').style.display = 'block';
    <?php endif; ?>

    // Handle the modal OK button
    document.getElementById('modal-ok').addEventListener('click', function() {
        document.getElementById('success-modal').style.display = 'none';
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('login-form').classList.remove('hidden');
    });
</script>

</body>
</html>