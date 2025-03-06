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
$tasks = getMongoDBConnection('tasks');

$user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);

if (!isset($_GET['id'])) {
    echo "Invalid friend ID.";
    exit;
}

$friend_id = $_GET['id'];
$friend = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($friend_id)]);

if (!$friend) {
    echo "Friend not found.";
    exit;
}

// Fetch pending tasks
$pendingTasks = $tasks->find([
    'user_id' => new MongoDB\BSON\ObjectId($friend_id),
    'status' => 'pending'
])->toArray();

// Handle accept and decline actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['task_id'])) {
    $task_id = $_POST['task_id'];
    $action = $_POST['action'];

    $update = [];
    if ($action === "accept") {
        $update = ['$set' => ['status' => 'completed', 'completed' => true]];
    } elseif ($action === "decline") {
        $update = ['$set' => ['status' => 'active']];
    }

    if (!empty($update)) {
        $tasks->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($task_id)],
            $update
        );
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$friend_id);
        exit;
    }
}

// Set profile picture path
$friendProfilePictureSrc = isset($friend['profile_picture']) ? htmlspecialchars($friend['profile_picture']) : 'path/to/default/profile-picture.jpg';

$xp = $friend['xp'] ?? 0;
$level = intdiv($xp, 100);
$xp_progress = $xp % 100;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($friend['username']); ?>'s Profile</title>
    <link rel="stylesheet" href="Styles/style.css">
    <link rel="stylesheet" href="Styles/account.css">
</head>
<body>
<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="columns">
        <div class="column">
            <div class="account-details">
                <div class="profile-picture">
                    <img src="<?php echo $friendProfilePictureSrc; ?>" alt="Profile Picture">
                </div>

                <h1><strong><?php echo htmlspecialchars($friend['username']); ?></strong></h1><br>
                <p><strong>First Name:</strong> <?php echo htmlspecialchars($friend['first_name']); ?></p>
                <p><strong>Last Name:</strong> <?php echo htmlspecialchars($friend['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($friend['email']); ?></p>

                <?php if ($friend['verification']): ?>
                    <p style="color: green;"><strong>Verified Account</strong></p>
                <?php else: ?>
                    <p style="color: red;"><strong>Not Verified</strong></p>
                <?php endif; ?>
            </div>

            <!-- Level Progress -->
            <div class="level">
                <h2><strong>Level:</strong> <?php echo $level; ?></h2><br>
                <p><strong>XP Progress:</strong> <?php echo $xp_progress; ?>/100</p>
                <div class="progress-bar">
                    <div class="progress" style="width: <?php echo $xp_progress; ?>%;"></div>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="tasks">
                <h2>Pending Tasks</h2>
                <?php if (empty($pendingTasks)): ?>
                    <p>No pending tasks.</p>
                <?php else: ?>
                    <?php foreach ($pendingTasks as $task): ?>
                        <div class="task">
                            <h3><?php echo htmlspecialchars($task['name']); ?></h3>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars($task['description']); ?></p>
                            <p><strong>Deadline:</strong> <?php echo htmlspecialchars($task['deadline']); ?></p>
                            <p><strong>Status:</strong> <?php echo htmlspecialchars($task['status']); ?></p>

                            <!-- Task Verification Image -->
                            <?php if (!empty($task['verification_image'])): ?>
                                <p><strong>Verification Image:</strong></p>
                                <img src="<?php echo htmlspecialchars($task['verification_image']); ?>" alt="Verification Image" width="200">
                            <?php endif; ?>

                            <!-- Accept or Decline Buttons -->
                            <form method="post">
                                <input type="hidden" name="task_id" value="<?php echo $task['_id']; ?>">
                                <button type="submit" name="action" value="accept" class="btn btn-success">Accept</button>
                                <button type="submit" name="action" value="decline" class="btn btn-danger">Decline</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>
