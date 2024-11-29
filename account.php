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

    if (password_verify($old_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $updateResult = $users->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($user_id)],
                    ['$set' => ['password' => $hashed_password]]
                );

                $message = $updateResult->getModifiedCount() > 0 ? "Password updated successfully!" : "Failed to update password. Please try again.";
            } else {
                $message = "New password must be at least 6 characters long.";
            }
        } else {
            $message = "New password and confirmation do not match.";
        }
    } else {
        $message = "Old password is incorrect.";
    }
}

// Handle verification toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verification'])) {
    $verification_status = isset($_POST['verification']) ? true : false;

    $users->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($user_id)],
        ['$set' => ['verification' => $verification_status]]
    );

    $message = $verification_status ? "Account verified." : "Account unverified.";
}

// Calculate user level and progress
$xp = $user['xp'] ?? 0;
$level = intdiv($xp, 100); // Full levels
$xp_progress = $xp % 100;  // Remaining XP for the next level
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account</title>
    <link rel="stylesheet" href="Styles/style.css">
    <link rel="stylesheet" href="Styles/account.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="account-grid">
    <!-- Account Details Section -->
    <div class="account-details">
        <h1>Your Account</h1>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
        <p><strong>First Name:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
        <p><strong>Last Name:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
    </div>

    <!-- Level Section -->
    <div class="level">
        <h2>Your Level</h2>
        <p><strong>Level:</strong> <?php echo $level; ?></p>
        <p><strong>XP Progress:</strong> <?php echo $xp_progress; ?>/100</p>
        <div class="progress-bar">
            <div class="progress" style="width: <?php echo $xp_progress; ?>%;"></div>
        </div>
    </div>

    <!-- Change Password Section -->
    <div class="change-password">
        <h2>Change Password</h2>
        <form method="POST" action="account.php">
            <label for="old_password">Old Password:</label>
            <input type="password" id="old_password" name="old_password" required><br>

            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required><br>

            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required><br>

            <button type="submit">Change Password</button>
        </form>
    </div>

    <!-- Verification Section -->
    <div class="verification">
        <h2>Verification</h2>
        <form method="POST" action="account.php">
            <label for="verification">
                <input type="checkbox" id="verification" name="verification" <?php echo $user['verification'] ? 'checked' : ''; ?>>
                Verify my account
            </label><br>
            <button type="submit">Save</button>
        </form>
        <!-- Display Message -->
        <?php if (isset($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
    </div>
</div>



</body>
</html>
