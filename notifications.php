<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: welcome.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$users = getMongoDBConnection('users');
$notifications = getMongoDBConnection('notifications');
$tasksCollection = getMongoDBConnection('tasks');

$user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);

// Handle accepting or declining friend requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $notification_id = $_POST['notification_id'];
    $action = $_POST['action'];

    $notification = $notifications->findOne(['_id' => new MongoDB\BSON\ObjectId($notification_id)]);

    if ($notification && $notification['receiver_id'] == new MongoDB\BSON\ObjectId($user_id)) {
        if ($action === 'accept') {
            // Update friend arrays for both users
            $sender_id = $notification['sender_id'];
            $users->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($user_id)],
                ['$addToSet' => ['friends' => $sender_id]]
            );
            $users->updateOne(
                ['_id' => $sender_id],
                ['$addToSet' => ['friends' => new MongoDB\BSON\ObjectId($user_id)]]
            );

            // Update notification status to accepted
            $notifications->updateOne(
                ['_id' => $notification['_id']],
                ['$set' => ['status' => 'accepted']]
            );
        } elseif ($action === 'decline') {
            // Update notification status to declined
            $notifications->updateOne(
                ['_id' => $notification['_id']],
                ['$set' => ['status' => 'declined']]
            );
        }
    }
}

// Fetch all pending friend requests and task verifications
$pending_requests = $notifications->find([
    'receiver_id' => new MongoDB\BSON\ObjectId($user_id),
    'type' => ['$in' => ['friend_request', 'task_pending']],
    'status' => 'pending'
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="Styles/style.css">
    <link rel="stylesheet" href="Styles/notifications.css">
</head>
<body>
<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'Includes/language.php'; ?>

<div class="main-content">
    <h1><?= htmlspecialchars($texts['notifications']['title']) ?></h1>

    <?php if ($pending_requests->isDead()): ?>
        <p class="notification-item"><?= htmlspecialchars($texts['notifications']['no-notifications']) ?></p>
    <?php else: ?>
        <ul class="notification-list">
            <?php foreach ($pending_requests as $request):
                $sender = $users->findOne(['_id' => $request['sender_id']]);
                ?>
                <li class="notification-item">
                    <?php if ($request['type'] === 'friend_request'): ?>
                        <?= htmlspecialchars($texts['notifications']['new-friend-request']) ?> <a><?php echo htmlspecialchars($sender['username']); ?></a>
                        <form method="POST" action="notifications.php" style="display:inline;">
                            <input type="hidden" name="notification_id" value="<?php echo $request['_id']; ?>">
                            <button type="submit" name="action" value="accept"><?= htmlspecialchars($texts['notifications']['friend-request-accept']) ?></button>
                            <button type="submit" name="action" value="decline"><?= htmlspecialchars($texts['notifications']['friend-request-decline']) ?></button>
                        </form>
                    <?php elseif ($request['type'] === 'task_pending'): ?>
                        <?= htmlspecialchars($texts['notifications']['task-verification']) ?>
                        <a><?php echo htmlspecialchars($sender['username']); ?></a>
                        <button class="button-link" onclick="location.href='friend_info.php?id=<?= $sender['_id']; ?>'">
                            <?= htmlspecialchars($texts['notifications']['go-to-profile']) ?>
                        </button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
</body>
</html>