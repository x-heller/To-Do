<?php
session_start();
require 'connect.php';

// Redirect to welcome.php if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: welcome.php");
    exit;
}

// Retrieve user information from MongoDB
$user_id = $_SESSION['user_id'];
$users = getMongoDBConnection('users');
$user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);

if (!$user) {
    echo "User not found.";
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['old_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if old password matches the current one (assuming password is hashed)
    if (password_verify($old_password, $user['password'])) {
        // Check if the new password and confirm password match
        if ($new_password === $confirm_password) {
            // Validate password strength (e.g., minimum length)
            if (strlen($new_password) >= 6) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update the password in the database
                $updateResult = $users->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($user_id)],
                    ['$set' => ['password' => $hashed_password]]
                );

                if ($updateResult->getModifiedCount() > 0) {
                    echo "Password updated successfully!";
                } else {
                    echo "Failed to update password. Please try again.";
                }
            } else {
                echo "New password must be at least 6 characters long.";
            }
        } else {
            echo "New password and confirmation do not match.";
        }
    } else {
        echo "Old password is incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<h1>Your Account</h1>

<!-- Display user information -->
<p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
<p><strong>First Name:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
<p><strong>Last Name:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
<p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
<p><strong>XP:</strong> <?php echo htmlspecialchars($user['xp']); ?></p>

<h2>Change Password</h2>

<!-- Change Password Form -->
<form method="POST" action="account.php">
    <label for="old_password">Old Password:</label>
    <input type="password" id="old_password" name="old_password" required><br>

    <label for="new_password">New Password:</label>
    <input type="password" id="new_password" name="new_password" required><br>

    <label for="confirm_password">Confirm New Password:</label>
    <input type="password" id="confirm_password" name="confirm_password" required><br>

    <button type="submit">Change Password</button>
</form>

</body>
</html>
