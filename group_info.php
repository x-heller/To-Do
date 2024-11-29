<?php
session_start();
require 'connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$groupsCollection = getMongoDBConnection('groups');
$usersCollection = getMongoDBConnection('users');

// Check if group ID is provided
if (!isset($_GET['id'])) {
    echo "Group ID is required!";
    exit;
}

$group_id = $_GET['id'];

// Get the group from the database
$group = $groupsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($group_id)]);

if (!$group) {
    echo "Group not found!";
    exit;
}

// Get members of the group
$members = $group['members'];
$member_usernames = [];

foreach ($members as $member_id) {
    $user = $usersCollection->findOne(['_id' => $member_id]);
    if ($user) {
        $member_usernames[] = htmlspecialchars($user['username']);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Info</title>
    <link rel="stylesheet" href="Styles/style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>

<!-- Content -->
<div class="content">
    <h1>Group Information</h1>

    <h2><?php echo htmlspecialchars($group['name']); ?></h2>
    <p><strong>Description:</strong> <?php echo htmlspecialchars($group['description']); ?></p>

    <p><strong>Invitation Code:</strong> <?php echo htmlspecialchars($group['invitation_code']); ?></p>

    <h3>Members:</h3>
    <ul>
        <?php foreach ($member_usernames as $username): ?>
            <li><?php echo $username; ?></li>
        <?php endforeach; ?>
    </ul>

    <a href="groups.php">Back to Groups</a>
</div>
</body>
</html>
