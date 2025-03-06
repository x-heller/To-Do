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

// Fetch tasks for the user
$tasksCollection = getMongoDBConnection('tasks');
$tasks = $tasksCollection->find(['user_id' => new MongoDB\BSON\ObjectId($user_id)]);

// Date for today
$today = date("Y-m-d");
$todayTasks = [];
$completedCount = 0;
$pendingCount = 0;

foreach ($tasks as $task) {
    if (isset($task['deadline']) && $task['deadline'] === $today) {
        $todayTasks[] = $task;
    }
    if (isset($task['completed']) && $task['completed'] === true) {
        $completedCount++;
    } else {
        $pendingCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <link rel="stylesheet" href="Styles/style.css">
    <link rel="stylesheet" href="Styles/index.css">
</head>
<body>
<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="welcome-card">
        <h1>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>
    </div>
    <div class="day-info">
        <p><strong>Date:</strong> <?php echo date("l, F j, Y"); ?></p>
        <p><?php echo $today;?></p>
    </div>

    <div class="tasks">
        <h2>Today's Tasks</h2>
        <?php if (!empty($todayTasks)): ?>
            <ul>
                <?php foreach ($todayTasks as $task): ?>
                    <li><?php echo htmlspecialchars($task['name']); ?> -
                        <?php echo $task['completed'] ? 'Completed' : 'Pending'; ?>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No tasks for today!</p>
        <?php endif; ?>
    </div>

    <div class="stats">
        <h2>Task Statistics</h2>
        <ul>
            <li><strong>Completed Tasks:</strong> <?php echo $completedCount; ?></li>
            <li><strong>Pending Tasks:</strong> <?php echo $pendingCount; ?></li>
        </ul>
    </div>
    <div class="notifications">
        <h2>Notifications</h2>
        <ul>
            <li>You have <?php echo count($todayTasks); ?> task(s) for today.</li>
        </ul>
    </div>
</div>
</body>
</html>
