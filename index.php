<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$users = getMongoDBConnection('users');
$tasksCollection = getMongoDBConnection('tasks');

$user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);

if (!$user) {
    echo "User not found.";
    exit;
}

$dayIndex = (int) date("N") - 1;
$dayName = $texts['days'][$dayIndex];
$dayToday = date("Y.m.d");


$xp = $user['xp'] ?? 0;
$level = intdiv($xp, 100);

$joinedGroups = isset($user['groups']) ? iterator_to_array($user['groups']) : [];
$groupCount = count($joinedGroups);

$friendList = isset($user['friends']) ? iterator_to_array($user['friends']) : [];
$friendsCount = count($friendList);

$today = date("Y-m-d");

$todayTaskCount = $tasksCollection->countDocuments([
    'user_id' => new MongoDB\BSON\ObjectId($user_id),
    'deadline' => $today,
    'completed' => false
]);
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
    <h1><?= htmlspecialchars($texts['title']) ?> <?php echo htmlspecialchars($user['username']); ?>!</h1>
    <div class="stat-box">
        <p class="stat-title"><?= htmlspecialchars($texts['todays-date']) ?></p>
        <p class="stat-value"><?php echo $dayToday; ?></p>
        <p class="stat-value"><?= htmlspecialchars($texts['days'][$dayIndex]) ?></p>
    </div>
    <div class="stat-box">
        <p class="stat-title"><?= htmlspecialchars($texts['account']['level']) ?></p>
        <p class="stat-value"><?php echo $level; ?></p>
    </div>
    <div class="stat-box">
        <?php if ($friendsCount == 0): ?>
            <p class="stat-value"><?= htmlspecialchars($texts['friend-count-0']) ?></p>
        <?php else: ?>
            <p><?= htmlspecialchars($texts['friend-count-s']) ?><?php echo $friendsCount; ?><?= htmlspecialchars($texts['friend-count-e']) ?></p>
        <?php endif; ?>
        <button onclick="location.href='friends.php'"><?= htmlspecialchars($texts['friend-view']) ?></button>
    </div>
    <div class="stat-box">
        <?php if ($groupCount == 0): ?>
            <p class="stat-value"><?= htmlspecialchars($texts['group-count-0']) ?></p>
        <?php else: ?>
            <p><?= htmlspecialchars($texts['group-count-s']) ?><?php echo $groupCount; ?><?= htmlspecialchars($texts['group-count-e']) ?></p>
        <?php endif; ?>
        <button onclick="location.href='groups.php'"><?= htmlspecialchars($texts['group-view']) ?></button>
    </div>
    <div class="stat-box">
        <?php if ($todayTaskCount == 0): ?>
            <p class="stat-value"><?= htmlspecialchars($texts['task-count-0']) ?></p>
        <?php else: ?>
            <p><?= htmlspecialchars($texts['task-count-s']) ?><?php echo $todayTaskCount; ?><?= htmlspecialchars($texts['task-count-e']) ?></p>
        <?php endif; ?>
        <button onclick="location.href='tasks.php'"><?= htmlspecialchars($texts['task-view']) ?></button>
    </div>



</div>

</body>
</html>
