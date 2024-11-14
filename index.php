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
    <title>Welcome</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<h1>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>

</body>
</html>
