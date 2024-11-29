<?php
session_start();
require 'connect.php';

// Redirect to welcome.php if the user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

// Fetch user information from MongoDB
$user_id = $_SESSION['user_id'];
$users = getMongoDBConnection('users');
$user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);


if (!$user) {
    echo "User not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <link rel="stylesheet" href="Styles/style.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<?php include 'sidebar.php'; ?>

<div class="content">
    <h1>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>
</div>
</body>
</html>
