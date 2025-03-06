<?php
session_start();
require 'connect.php';

// Redirect if user not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: welcome.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$users = getMongoDBConnection('users');
$user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);

if (!$user) {
    echo "User not found.";
    exit;
}

$message = ''; // Initialize message

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['old_password'], $_POST['new_password'], $_POST['confirm_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!password_verify($old_password, $user['password'])) {
        $message = "Old password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 6) {
        $message = "New password must be at least 6 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $updateResult = $users->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['$set' => ['password' => $hashed_password]]
        );
        $message = $updateResult->getModifiedCount() > 0 ? "Password updated successfully!" : "Failed to update password.";
    }
}

// Handle verification toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verification_toggle'])) {
    $verification_status = isset($_POST['verification']) && $_POST['verification'] === 'on'; // Checkbox értéke
    $users->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($user_id)],
        ['$set' => ['verification' => $verification_status]]
    );
    $message = $verification_status ? "Account verified." : "Account unverified.";
    header("Refresh:0");
}


// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $imageFile = $_FILES['profile_picture'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 2 * 1024 * 1024; // 2MB max size

    if ($imageFile['error'] !== UPLOAD_ERR_OK) {
        $message = "Error uploading file.";
    } elseif (!in_array($imageFile['type'], $allowedTypes)) {
        $message = "Only JPEG, PNG, or GIF files are allowed.";
    } elseif ($imageFile['size'] > $maxFileSize) {
        $message = "File size exceeds 2MB.";
    } else {
        $uploadDir = 'uploads/profile_pictures/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
        $uniqueName = $user_id . '_' . time() . '.' . $ext; // Unique file name
        $imagePath = $uploadDir . $uniqueName;

        // Delete the old profile picture if it exists
        if (isset($user['profile_picture']) && strpos($user['profile_picture'], $uploadDir) === 0 && file_exists($user['profile_picture'])) {
            unlink($user['profile_picture']);
        }

        // Save the new profile picture
        if (move_uploaded_file($imageFile['tmp_name'], $imagePath)) {
            $users->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($user_id)],
                ['$set' => ['profile_picture' => $imagePath]]
            );
            $message = "Profile picture updated successfully!";
            header("Refresh:0");
        } else {
            $message = "Failed to save the uploaded file.";
        }
    }
}

// Set profile picture path
$profilePictureSrc = isset($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'path/to/default/profile-picture.jpg';

// User XP and level
$xp = $user['xp'] ?? 0;
$level = intdiv($xp, 100);
$xp_progress = $xp % 100;
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
<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <!-- Account Details -->
    <div class="columns">
        <div class="column">
            <div class="account-details">
                <div class="profile-picture">
                    <img src="<?php echo $profilePictureSrc; ?>" alt="Profile Picture">
                    <div class="overlay">Change Picture</div>  <!-- A szöveg megjelenik hover esetén -->
                </div>

                <h1><strong><?php echo htmlspecialchars($user['username']); ?></strong></h1><br>
                <p><strong>First Name:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
                <p><strong>Last Name:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>

                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="profile_picture" accept="image/*" style="display: none;">
                    <button type="submit" style="display: none;">Save</button>
                </form>
            </div>


            <!-- Level Progress -->
            <div class="level">
                <h2><strong>Level:</strong> <?php echo $level; ?></h2><br>
                <p><strong>XP Progress:</strong> <?php echo $xp_progress; ?>/100</p>
                <div class="progress-bar">
                    <div class="progress" style="width: <?php echo $xp_progress; ?>%;"></div>
                </div>
            </div>
        </div>
        <div class="column">
            <!-- Change Password -->
            <div class="change-password">
                <h2>Change Password</h2><br>
                <form method="POST">
                    <label for="old_password">Old Password</label><br>
                    <input type="password" id="old_password" name="old_password" required><br>

                    <label for="new_password">New Password</label><br>
                    <input type="password" id="new_password" name="new_password" required><br>

                    <label for="confirm_password">Confirm New Password</label><br>
                    <input type="password" id="confirm_password" name="confirm_password" required><br>

                    <button type="submit">Change Password</button>
                </form>
            </div>

            <!-- Verification -->
            <div class="verification">
                <h2>Verification</h2><br>
                <form method="POST">
                    <input type="hidden" name="verification_toggle" value="1">
                    <label>
                        <input type="checkbox" name="verification" <?php echo $user['verification'] ? 'checked' : ''; ?>>
                        Verify my account
                    </label><br>
                    <button type="submit">Save</button>
                </form>
            </div>
        </div>
    </div>



    <!-- Display Messages -->
    <?php if (!empty($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
</div>

<script>
    const profilePicture = document.querySelector('.profile-picture');
    const fileInput = document.querySelector('input[type="file"]');
    const saveButton = document.querySelector('button[type="submit"]');

    profilePicture.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        saveButton.click();
    });
</script>
</body>
</html>
