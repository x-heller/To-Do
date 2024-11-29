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

// Retrieve the current user from the database
$user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);
$current_friends = (array) $user['friends'] ?? [];  // Convert BSONArray to PHP array

// Handle friend request submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['friend_id'])) {
    $friend_id = $_POST['friend_id'];

    // Ensure not sending request to oneself or existing friends
    if ($friend_id !== $user_id && !in_array($friend_id, $current_friends)) {
        // Check if a friend request already exists
        $existing_request = $notifications->findOne([
            'sender_id' => new MongoDB\BSON\ObjectId($user_id),
            'receiver_id' => new MongoDB\BSON\ObjectId($friend_id),
            'type' => 'friend_request',
            'status' => 'pending'
        ]);

        if (!$existing_request) {
            // Insert friend request into notifications collection
            $notifications->insertOne([
                'sender_id' => new MongoDB\BSON\ObjectId($user_id),
                'receiver_id' => new MongoDB\BSON\ObjectId($friend_id),
                'type' => 'friend_request',
                'status' => 'pending'
            ]);
            echo "Friend request sent.";
        } else {
            echo "Friend request already sent.";
        }
    }
}

// Handle removing a friend from the list
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_friend_id'])) {
    $remove_friend_id = $_POST['remove_friend_id'];

    if (in_array($remove_friend_id, $current_friends)) {
        // Remove the friend from both users' friend lists
        $users->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['$pull' => ['friends' => new MongoDB\BSON\ObjectId($remove_friend_id)]]
        );
        $users->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($remove_friend_id)],
            ['$pull' => ['friends' => new MongoDB\BSON\ObjectId($user_id)]]
        );

        // Redirect to the same page to reload the friend list
        header("Location: friends.php");
        exit;
    }
}

// Search for users not in the friend list and not the current user
$search_results = [];
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $search_results = $users->find([
        'username' => new MongoDB\BSON\Regex("^$search_query", 'i'),
        '_id' => ['$ne' => new MongoDB\BSON\ObjectId($user_id)],  // Exclude current user
        '_id' => ['$nin' => array_map(fn($id) => new MongoDB\BSON\ObjectId($id), $current_friends)]  // Exclude friends
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends</title>
    <link rel="stylesheet" href="Styles/style.css">
    <link rel="stylesheet" href="Styles/friends.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<?php include 'sidebar.php'; ?>

<div class="content">
    <div class="friends">
        <!-- Search and Add Friends Section -->
        <div class="search-section">
            <h2>Find and Add Friends</h2>
            <form method="GET" action="friends.php">
                <input type="text" name="search" placeholder="Search users">
                <button type="submit">Search</button>
            </form>

            <h3>Search Results</h3>
            <div class="search-results">
                <?php if ($search_results): ?>
                    <ul>
                        <?php foreach ($search_results as $result): ?>
                            <li>
                                <?php echo htmlspecialchars($result['username']); ?>
                                <form method="POST" action="friends.php" style="display:inline;">
                                    <input type="hidden" name="friend_id" value="<?php echo $result['_id']; ?>">
                                    <button type="submit">Send Friend Request</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-results">No users found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Existing Friends Section -->
        <div class="friends-section">
            <h2>Your Friends</h2>
            <ul>
                <?php foreach ($current_friends as $friend_id):
                    $friend = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($friend_id)]); ?>
                    <li>
                        <?php echo htmlspecialchars($friend['username']); ?>
                        <form method="POST" action="friends.php" style="display:inline;">
                            <input type="hidden" name="remove_friend_id" value="<?php echo $friend['_id']; ?>">
                            <button type="submit">Remove Friend</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>
