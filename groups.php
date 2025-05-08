<?php
session_start();
require 'connect.php';

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
$groupCount = count($joinedGroups);
$groupLimit = 5;  // Maximum number of groups a user can join

// Group creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_group_name']) && isset($_POST['create_group_description'])) {
    $group_name = $_POST['create_group_name'];
    $group_description = $_POST['create_group_description'];

    // Check if the user is already in the maximum number of groups
    if ($groupCount >= $groupLimit) {
        echo "You can only be in a maximum of $groupLimit groups.";
        exit;
    }

    // Generate a unique invitation code
    $invitation_code = bin2hex(random_bytes(6));  // Generates a 12-character invitation code

    // Insert new group into the database with invitation code
    $group_id = (string) $groupsCollection->insertOne([
        'name' => $group_name,
        'description' => $group_description,
        'members' => [new MongoDB\BSON\ObjectId($user_id)],
        'invitation_code' => $invitation_code
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

    // Check if the user is already in the maximum number of groups
    if ($groupCount >= $groupLimit) {
        echo "You can only be in a maximum of $groupLimit groups.";
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
            ['$push' => ['members' => new MongoDB\BSON\ObjectId($user_id)]]
        );

        // Add the group to the user's list of groups
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['$push' => ['groups' => (string) $group['_id']]]
        );

        header("Location: groups.php");
        exit;
    } else {
        echo "<script>alert('Invalid invitation code. Please try again.');</script>";
        header("Location: groups.php");
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
    <link rel="stylesheet" href="Styles/groups.css">
    <script>
        // Function to toggle the visibility of the popup
        function togglePopup() {
            const popup = document.getElementById('group-popup');
            const overlay = document.getElementById('overlay');
            const isVisible = popup.style.display === 'block';
            popup.style.display = isVisible ? 'none' : 'block';
            overlay.style.display = isVisible ? 'none' : 'block';
        }

        // Function to confirm joining a group
        function confirmJoinGroup(form) {
            if (confirm("Are you sure you want to join this group?")) {
                form.submit();
            }
        }

        // Function to close all popups
        function closeAllPopups() {
            const popups = document.querySelectorAll('.popup');
            const overlay = document.getElementById('overlay');
            popups.forEach(popup => popup.style.display = 'none');
            overlay.style.display = 'none';
        }

        document.getElementById('overlay').addEventListener('click', closeAllPopups);
    </script>
</head>
<body>
<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'Includes/language.php'; ?>

<div class="main-content">
    <h1><?= htmlspecialchars($texts['groups']['title']) ?></h1>

    <p><?= htmlspecialchars($texts['groups']['group-count-s']) ?><?php echo $groupCount; ?>/<?php echo $groupLimit; ?><?= htmlspecialchars($texts['groups']['group-count-e']) ?></p>

    <button onclick="togglePopup()" <?php if ($groupCount >= $groupLimit) echo 'disabled'; ?>><?= htmlspecialchars($texts['groups']['join-group']) ?></button>

    <!-- Create or Join Group Popup -->
    <div id="group-popup" class="popup">
        <span class="close-btn" onclick="togglePopup()">&times;</span>
        <h2><?= htmlspecialchars($texts['groups']['create-title']) ?></h2>
        <form method="POST" action="groups.php">
            <label for="create_group_name"><?= htmlspecialchars($texts['groups']['create-name']) ?></label>
            <input type="text" id="create_group_name" name="create_group_name" required><br>

            <label for="create_group_description"><?= htmlspecialchars($texts['groups']['create-description']) ?></label>
            <textarea id="create_group_description" name="create_group_description" required></textarea><br>

            <button type="submit"><?= htmlspecialchars($texts['groups']['create-button']) ?></button>
        </form>

        <hr>

        <!-- Join an Existing Group Form -->
        <h2><?= htmlspecialchars($texts['groups']['join-title']) ?></h2>
        <form method="POST" action="groups.php" onsubmit="event.preventDefault(); confirmJoinGroup(this);">
            <label for="join_group_code"><?= htmlspecialchars($texts['groups']['join-code']) ?></label>
            <input type="text" id="join_group_code" name="join_group_code" required><br>
            <button type="submit"><?= htmlspecialchars($texts['groups']['join-button']) ?></button>
        </form>
    </div>

    <div class="overlay" id="overlay" onclick="togglePopup()"></div>


    <!-- List of Groups -->
    <br><br>
    <h2><?= htmlspecialchars($texts['groups']['groups-title']) ?></h2>
    <div class="groups-section">
        <ul>
            <?php foreach ($groups as $group): ?>
                <li>
                    <span class="group-name">
                    <?php echo htmlspecialchars($group['name']); ?>
                </span>
                    <span class="member-count">
                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAARxJREFUSEvVlNENwjAMRN1NYBOYBJgEmAQ2oZvAJtAnxVIanFxU1A8i5aOJes++2B5s5TWsrG+9gI2Z3cxsZ2avtK/T96gC7AEck3ipBehuZoCqSwGI+JH+viQxsjlMZ3wDObUyUQBsIQMXzyP1zLBpX0tBAZ6TDUS8TdHmOpxzTxbch+sXAILvpFrVUQD85x0ii87pnIfmHRZlkNvgFZM/MqL4Xy1XlQECHmkUIZEDXlym/uOqjaaatXmvLMIeHpldLh8Z+F/t5hoAQZoMa3pWtaMjQD4eWqMAONuzDCsqAnj3Nus7S6ucTbOuLgFd8yXwLK+yWVOWAB9usr4DiFs7G34loDXc1GOHw68EyOElKF//qz5QUcv7/wd8AKJNRBl0Vm5MAAAAAElFTkSuQmCC" alt="Member Icon" class="member-icon">
                    <span><?php echo count($group['members']); ?></span>
                </span>
                    <span class="group-info-btn">
                    <button type="button" class="btn" onclick="location.href='group_info.php?id=<?php echo $group['_id']; ?>'"><?= htmlspecialchars($texts['groups']['group-info']) ?></button>
                </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="no-groups-message">
        <?php if ($groupCount == 0): ?>
            <p><?= htmlspecialchars($texts['groups']['no-groups']) ?></p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>