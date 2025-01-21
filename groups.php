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

// Get the current user
$user = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);
$joinedGroups = isset($user['groups']) ? iterator_to_array($user['groups']) : [];  // Convert BSONArray to PHP array

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_group_name']) && isset($_POST['create_group_description'])) {
    $group_name = $_POST['create_group_name'];
    $group_description = $_POST['create_group_description'];

    // Check if the user is already in 5 groups
    if (count($joinedGroups) >= 5) {
        echo "You can only be in a maximum of 5 groups.";
        exit;
    }

    // Generate a unique invitation code
    $invitation_code = bin2hex(random_bytes(6));  // Generates a 12-character invitation code

    // Insert new group into the database with invitation code
    $group_id = (string) $groupsCollection->insertOne([
        'name' => $group_name,
        'description' => $group_description,
        'members' => [new MongoDB\BSON\ObjectId($user_id)], // User is the first member
        'invitation_code' => $invitation_code  // Store the invitation code
    ])->getInsertedId();

    // Add the group to the user's list of groups
    $usersCollection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($user_id)],
        ['$push' => ['groups' => $group_id]]
    );

    header("Location: groups.php");
    exit;
}

// Handle joining an existing group using the invitation code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['join_group_code'])) {
    $invitation_code = $_POST['join_group_code'];

    // Check if the user is already in 5 groups
    if (count($joinedGroups) >= 5) {
        echo "You can only be in a maximum of 5 groups.";
        exit;
    }

    // Find the group by invitation code
    $group = $groupsCollection->findOne(['invitation_code' => $invitation_code]);

    if ($group) {
        // Check if the user is already a member of the group
        if (in_array($group['_id'], $joinedGroups)) {
            echo "You are already a member of this group.";
            exit;
        }

        // Add the user to the group
        $groupsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($group['_id'])],
            ['$push' => ['members' => new MongoDB\BSON\ObjectId($user_id)]]  // Add user to group
        );

        // Add the group to the user's list of groups
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['$push' => ['groups' => (string) $group['_id']]]  // Add group to user's list of groups
        );

        header("Location: groups.php");
        exit;
    } else {
        echo "Invalid invitation code!";
        exit;
    }
}

// Get the groups the user is a member of
$groups = $groupsCollection->find([
    '_id' => ['$in' => array_map(function($group_id) {
        return new MongoDB\BSON\ObjectId($group_id);
    }, $joinedGroups)]
]);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groups</title>
    <link rel="stylesheet" href="Styles/style.css">
    <script>
        // Toggle visibility of the create/join form
        function toggleForm() {
            const formContainer = document.getElementById('group-form-container');
            if (formContainer.style.display === 'none' || formContainer.style.display === '') {
                formContainer.style.display = 'block';
            } else {
                formContainer.style.display = 'none';
            }
        }

        // Confirmation for joining group
        function confirmJoinGroup(form) {
            if (confirm("Are you sure you want to join this group?")) {
                form.submit();
            }
        }
    </script>
</head>
<body>
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>

<!-- Content -->
<div class="content">
    <h1>Groups</h1>

    <!-- Button to toggle the create/join form -->
    <button onclick="toggleForm()">Create or Join</button>

    <!-- Create or Join Group Form -->
    <div id="group-form-container" style="display:none; margin-top: 20px;">
        <h2>Create a Group</h2>
        <form method="POST" action="groups.php">
            <label for="create_group_name">Group Name:</label>
            <input type="text" id="create_group_name" name="create_group_name" required><br>

            <label for="create_group_description">Description:</label>
            <textarea id="create_group_description" name="create_group_description" required></textarea><br>

            <button type="submit">Create a Group</button>
        </form>

        <hr>

        <!-- Join an Existing Group Form -->
        <h2>Join an Existing Group</h2>
        <form method="POST" action="groups.php" onsubmit="event.preventDefault(); confirmJoinGroup(this);">
            <label for="join_group_code">Enter Invitation Code to Join:</label>
            <input type="text" id="join_group_code" name="join_group_code" required><br>
            <button type="submit">Join Group</button>
        </form>
    </div>

    <!-- List of Groups User is a Member Of -->
    <h2>Your Groups</h2>
    <ul>
        <?php foreach ($groups as $group): ?>
            <li>
                <a href="group_info.php?id=<?php echo $group['_id']; ?>"><?php echo htmlspecialchars($group['name']); ?></a>
                - <?php echo htmlspecialchars($group['description']); ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
</body>
</html>


