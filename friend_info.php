<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: welcome.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$users = getMongoDBConnection('users');
$tasks = getMongoDBConnection('tasks');
$notifications = getMongoDBConnection('notifications');

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

        $users->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($friend_id)],
            ['$inc' => ['xp' => 10]]
        );
    } elseif ($action === "decline") {
        $update = ['$set' => ['status' => 'active']];
    }

    if (!empty($update)) {
        $tasks->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($task_id)],
            $update
        );

        // Remove notification
        $notifications->deleteMany([
            'task_id' => new MongoDB\BSON\ObjectId($task_id),
            'type' => 'task_pending'
        ]);

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
    <link rel="stylesheet" href="Styles/friend_info.css">
    <script>
        function showTaskPopup(taskId) {
            document.getElementById('task-popup-' + taskId).style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        }

        function closeTaskPopup(taskId) {
            document.getElementById('task-popup-' + taskId).style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }
    </script>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeTaskPopup()"></div>

<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'Includes/language.php'; ?>

<div class="main-content">
    <div class="columns">
        <div class="column">
            <span class="friend-info-btn">
            <button type="button" class="btn" onclick="location.href='friends.php'"><?= htmlspecialchars($texts['friend-info']['back']) ?></button>
            </span>
            <div class="account-details">
                <div class="profile-picture">
                    <img src="<?php echo $friendProfilePictureSrc; ?>" alt="Profile Picture">
                </div>

                <h1><strong><?php echo htmlspecialchars($friend['username']); ?></strong></h1><br>
                <p><strong><?= htmlspecialchars($texts['friend-info']['first-name']) ?></strong> <?php echo htmlspecialchars($friend['first_name']); ?></p>
                <p><strong><?= htmlspecialchars($texts['friend-info']['last-name']) ?></strong> <?php echo htmlspecialchars($friend['last_name']); ?></p>
                <p><strong><?= htmlspecialchars($texts['friend-info']['email']) ?></strong> <?php echo htmlspecialchars($friend['email']); ?></p>

                <?php if ($friend['verification']): ?>
                    <p style="color: green;"><strong><?= htmlspecialchars($texts['friend-info']['verified']) ?></strong></p>
                <?php else: ?>
                    <p style="color: red;"><strong><?= htmlspecialchars($texts['friend-info']['not-verified']) ?></strong></p>
                <?php endif; ?>
            </div>

            <!-- Level Progress -->
            <div class="level">
                <h2><strong><?= htmlspecialchars($texts['friend-info']['level']) ?></strong> <?php echo $level; ?></h2><br>
                <p><strong><?= htmlspecialchars($texts['friend-info']['xp']) ?></strong> <?php echo $xp_progress; ?>/100</p>
                <div class="progress-bar">
                    <div class="progress" style="width: <?php echo $xp_progress; ?>%;"></div>
                </div>
            </div>
        </div>
        <div class="column">
            <!-- Pending Tasks -->
            <div class="tasks">
                <h2><?= htmlspecialchars($texts['friend-info']['pending-tasks']) ?></h2>
                <?php if (empty($pendingTasks)): ?>
                    <p><?= htmlspecialchars($texts['friend-info']['no-pending-tasks']) ?></p>
                <?php else: ?>
                    <ul id="task-list">
                        <?php foreach ($pendingTasks as $task): ?>
                            <li class="task-item">
                                <div class="task-summary">
                                    <span class="task-name"><?php echo htmlspecialchars($task['name']); ?></span>
                                    <span class="task-deadline"><?php echo htmlspecialchars($task['deadline']); ?></span>
                                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAHJJREFUSEtjZKAxYKSx+QyjFhAM4dEgGqFB5MDAwDAf6vdEBgaGA1A2qeJgbdhS0X6goSDDQOABAwODIpRNqjhRFoBc74jFAmLEcVoAcn09AwODAgMDA3oQkSKO0wKCSY8UBaM5mWBojQbRaBARDAGCCgBGlxgZvrplfAAAAABJRU5ErkJggg==" onclick="showTaskPopup('<?php echo $task['_id']; ?>')" style="cursor: pointer;">
                                </div>

                                <div class="details-popup" id="task-popup-<?php echo $task['_id']; ?>">
                                    <span class="close-btn" onclick="closeTaskPopup('<?php echo $task['_id']; ?>')">&times;</span>
                                    <h2><?php echo htmlspecialchars($task['name']); ?></h2>
                                    <p><strong><?= htmlspecialchars($texts['tasks']['task-description']) ?></strong> <?php echo htmlspecialchars($task['description']); ?></p>
                                    <p><strong><?= htmlspecialchars($texts['tasks']['task-deadline']) ?></strong> <?php echo htmlspecialchars($task['deadline']); ?></p>
                                    <p><strong><?= htmlspecialchars($texts['friend-info']['task-status']) ?></strong> <?php echo htmlspecialchars($task['status']); ?></p>

                                    <?php if (!empty($task['verification_image'])): ?>
                                        <p><strong><?= htmlspecialchars($texts['tasks']['verification-image']) ?></strong></p>
                                        <img src="<?php echo htmlspecialchars($task['verification_image']); ?>" alt="Verification Image" width="200">
                                    <?php endif; ?>

                                    <form method="post">
                                        <input type="hidden" name="task_id" value="<?php echo $task['_id']; ?>">
                                        <button type="submit" name="action" value="accept" class="btn btn-success"><?= htmlspecialchars($texts['notifications']['friend-request-accept']) ?></button>
                                        <button type="submit" name="action" value="decline" class="btn btn-danger"><?= htmlspecialchars($texts['notifications']['friend-request-decline']) ?></button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>