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

// Check if the current user is the group creator
$is_creator = (string)$group['members'][0] === $user_id;

// Handle group deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete' && $is_creator) {
        // Delete the group
        $groupsCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($group_id)]);

        // Remove the group from all users
        $usersCollection->updateMany(
            ['groups' => $group_id],
            ['$pull' => ['groups' => $group_id]]
        );

        header("Location: groups.php");
        exit;
    }

    if ($action === 'rename' && $is_creator) {
        $new_name = $_POST['new_name'];
        $groupsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($group_id)],
            ['$set' => ['name' => $new_name]]
        );
        header("Location: group_info.php?id=" . $group_id);
        exit;
    }

    if ($action === 'remove_member' && $is_creator) {
        $member_id_to_remove = $_POST['member_id'];
        $groupsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($group_id)],
            ['$pull' => ['members' => new MongoDB\BSON\ObjectId($member_id_to_remove)]]
        );

        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($member_id_to_remove)],
            ['$pull' => ['groups' => $group_id]]
        );

        header("Location: group_info.php?id=" . $group_id);
        exit;
    }
}

// Get members of the group
$members = $group['members'];
$member_usernames = [];

foreach ($members as $member_id) {
    $user = $usersCollection->findOne(['_id' => $member_id]);
    if ($user) {
        $member_usernames[(string)$member_id] = htmlspecialchars($user['username']);
    }
}

// Check if 'invitation_code' exists, otherwise set it to an empty string
$invitation_code = isset($group['invitation_code']) ? htmlspecialchars($group['invitation_code']) : 'N/A';

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

    <p><strong>Invitation Code:</strong> <?php echo $invitation_code; ?></p>

    <h3>Members:</h3>
    <ul>
        <?php foreach ($member_usernames as $member_id => $username): ?>
            <li>
                <?php echo $username; ?>
                <?php if ($is_creator && $member_id !== $user_id): ?>
                    <!-- Option to remove members -->
                    <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>" style="display:inline;">
                        <input type="hidden" name="action" value="remove_member">
                        <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                        <button type="submit">Remove</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($is_creator): ?>
        <h3>Manage Group</h3>

        <!-- Rename Group -->
        <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>">
            <label for="new_name">Rename Group:</label>
            <input type="text" id="new_name" name="new_name" required>
            <input type="hidden" name="action" value="rename">
            <button type="submit">Rename</button>
        </form>

        <!-- Delete Group -->
        <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>" onsubmit="return confirm('Are you sure you want to delete this group? This action cannot be undone.');">
            <input type="hidden" name="action" value="delete">
            <button type="submit">Delete Group</button>
        </form>
    <?php endif; ?>

    <a href="groups.php">Back to Groups</a>
</div>
</body>
</html>
