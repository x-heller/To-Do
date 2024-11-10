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
            echo "Friend request accepted.";
        } elseif ($action === 'decline') {
            // Update notification status to declined
            $notifications->updateOne(
                ['_id' => $notification['_id']],
                ['$set' => ['status' => 'declined']]
            );
            echo "Friend request declined.";
        }
    }
}

// Fetch all pending friend requests
$pending_requests = $notifications->find([
    'receiver_id' => new MongoDB\BSON\ObjectId($user_id),
    'type' => 'friend_request',
    'status' => 'pending'
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<h1>Friend Requests</h1>

<?php if ($pending_requests->isDead()): ?>
    <p>No pending friend requests.</p>
<?php else: ?>
    <ul>
        <?php foreach ($pending_requests as $request):
            $sender = $users->findOne(['_id' => $request['sender_id']]);
            ?>
            <li>
                Friend request from <?php echo htmlspecialchars($sender['username']); ?>
                <form method="POST" action="notifications.php" style="display:inline;">
                    <input type="hidden" name="notification_id" value="<?php echo $request['_id']; ?>">
                    <button type="submit" name="action" value="accept">Accept</button>
                    <button type="submit" name="action" value="decline">Decline</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
</body>
</html>
