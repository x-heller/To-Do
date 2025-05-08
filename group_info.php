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
$tasksCollection = getMongoDBConnection('group_tasks');

$user = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);


if (!isset($_GET['id'])) {
    echo "Group ID is required!";
    exit;
}

$group_id = $_GET['id'];
$group = $groupsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($group_id)]);

if (!$group) {
    echo "Group not found!";
    exit;
}

$is_creator = (string)$group['members'][0] === $user_id;

$creator = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($group['members'][0])]);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete_task') {
        $task_id = $_POST['task_id'];
        $task = $tasksCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($task_id)]);

        if ($task && ($is_creator || (string)$task['user_id'] === $user_id)) {
            $tasksCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($task_id)]);
            header("Location: group_info.php?id=" . $group_id);
            exit;
        } else {
            echo "You do not have permission to delete this task.";
        }
    }

    if ($action === 'delete' && $is_creator) {
        $groupsCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($group_id)]);
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

    // Check if the user is allowed to remove a member
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

    // Check if task creation is allowed
    if ($action === 'create_task') {
        $activeTasksCount = 0;
        $existingTasks = $tasksCollection->find(['group_id' => new MongoDB\BSON\ObjectId($group_id)]);
        foreach ($existingTasks as $existingTask) {
            $allCompleted = true;
            foreach ($existingTask['subtasks'] as $subtask) {
                if (!$subtask['completed']) {
                    $allCompleted = false;
                    break;
                }
            }
            if (!$allCompleted) {
                $activeTasksCount++;
            }
        }
        if ($activeTasksCount >= 2) {
            echo "Maximum active tasks reached.";
            exit;
        }

        $task_name = $_POST['task_name'];
        $task_description = $_POST['task_description'];
        $due_date = $_POST['due_date'];
        $subtasks = [];

        if (isset($_POST['subtasks']) && is_array($_POST['subtasks'])) {
            foreach ($_POST['subtasks'] as $index => $subtask_name) {
                $subtasks[] = [
                    'name' => $subtask_name,
                    'assigned_to' => $_POST['subtask_assigned_to'][$index],
                    'completed' => false
                ];
            }
        }

        $tasksCollection->insertOne([
            'group_id' => new MongoDB\BSON\ObjectId($group_id),
            'user_id' => new MongoDB\BSON\ObjectId($user_id),
            'name' => $task_name,
            'description' => $task_description,
            'due_date' => $due_date,
            'subtasks' => $subtasks
        ]);
        header("Location: group_info.php?id=" . $group_id);
        exit;
    }

    if ($action === 'mark_subtask_complete') {
        $task_id = $_POST['task_id'];
        $subtask_name = $_POST['subtask_name'];

        $task = $tasksCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($task_id)]);

        foreach ($task['subtasks'] as &$subtask) {
            if ($subtask['name'] === $subtask_name && $subtask['assigned_to'] === $user_id) {
                $subtask['completed'] = true;
            }
        }

        $tasksCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($task_id)],
            ['$set' => ['subtasks' => $task['subtasks']]]
        );
        header("Location: group_info.php?id=" . $group_id);
        exit;
    }

    if ($action === 'leave_group') {
        $groupsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($group_id)],
            ['$pull' => ['members' => new MongoDB\BSON\ObjectId($user_id)]]
        );
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['$pull' => ['groups' => new MongoDB\BSON\ObjectId($group_id)]]
        );
        header("Location: groups.php");
        exit;
    }
}

// Count active tasks
$tasksCursor = $tasksCollection->find(['group_id' => new MongoDB\BSON\ObjectId($group_id)]);
$tasksArray = iterator_to_array($tasksCursor);
$activeTasksCount = 0;
foreach ($tasksArray as $task) {
    $allCompleted = true;
    foreach ($task['subtasks'] as $subtask) {
        if (!$subtask['completed']) {
            $allCompleted = false;
            break;
        }
    }
    if (!$allCompleted) {
        $activeTasksCount++;
    }
}

$currentDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Info</title>
    <link rel="stylesheet" href="Styles/style.css">
    <link rel="stylesheet" href="Styles/group_info.css">
</head>
<body>
<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'Includes/language.php'; ?>

<div class="main-content">
    <div class="header">
        <h1><?php echo htmlspecialchars($group['name']); ?></h1>
        <span class="group-info-btn">
            <button type="button" class="btn" onclick="location.href='groups.php'"><?= htmlspecialchars($texts['group-info']['back']) ?></button>
        </span>
    </div>

    <div class="columns">
        <div class="column">
            <div class="info">
                <h2><?= htmlspecialchars($texts['group-info']['title']) ?></h2>
                <p><strong><?= htmlspecialchars($texts['group-info']['group-name']) ?></strong> <?php echo htmlspecialchars($group['name']); ?></p>
                <p><strong><?= htmlspecialchars($texts['group-info']['group-description']) ?></strong> <?php echo htmlspecialchars($group['description']); ?></p>
                <p><strong><?= htmlspecialchars($texts['group-info']['created-by']) ?></strong> <?php echo htmlspecialchars($creator['username']); ?></p>
                <p><strong><?= htmlspecialchars($texts['group-info']['code']) ?></strong> <?php echo htmlspecialchars($group['invitation_code']); ?></p>

            </div>

            <div class="members">
                <h2><?= htmlspecialchars($texts['group-info']['members']) ?></h2>
                <ul>
                    <?php foreach ($group['members'] as $member_id): ?>
                        <?php $member = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($member_id)]); ?>
                        <li>
                            <?php echo htmlspecialchars($member['username']); ?>
                            <?php if ($is_creator && $member_id !== $user_id): ?>
                                <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                                    <button type="submit"><?= htmlspecialchars($texts['group-info']['remove']) ?></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="column">
            <div class="manage">
                <h2><?= htmlspecialchars($texts['group-info']['manage']) ?></h2>
                <?php if ($is_creator): ?>
                    <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>">
                        <input type="hidden" name="action" value="rename">
                        <input type="text" name="new_name" placeholder="<?= htmlspecialchars($texts['group-info']['new-name']) ?>" required>
                        <button type="submit">Rename Group</button>
                    </form>
                    <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit"><?= htmlspecialchars($texts['group-info']['delete']) ?></button>
                    </form>
                <?php endif; ?>
                <?php if (!$is_creator): ?>
                    <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>">
                        <input type="hidden" name="action" value="leave_group">
                        <button type="submit"><?= htmlspecialchars($texts['group-info']['leave']) ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="tasks">
                <h2><?= htmlspecialchars($texts['group-info']['task-title']) ?></h2>
                <p><?= htmlspecialchars($texts['group-info']['active-tasks']) ?><?php echo $activeTasksCount; ?></p>
                <button onclick="showCreateTaskPopup()" <?php if ($activeTasksCount >= 2) echo "disabled"; ?>><?= htmlspecialchars($texts['group-info']['create-task']) ?></button>
                <ul>
                    <?php foreach ($tasksArray as $task): ?>
                        <?php
                        $completedSubtasksCount = 0;
                        $totalSubtasksCount = count($task['subtasks']);
                        foreach ($task['subtasks'] as $subtask) {
                            if ($subtask['completed']) {
                                $completedSubtasksCount++;
                            }
                        }
                        $allCompleted = $completedSubtasksCount === $totalSubtasksCount;
                        ?>
                        <li class="task-item" data-task='<?php echo json_encode($task); ?>'>
                            <div class="task-summary">
                                <span class="task-name"><?php echo htmlspecialchars($task['name']); ?></span>
                                <span class="task-deadline"><?php echo htmlspecialchars($task['due_date']); ?></span>
                                <span class="task-status">
                                <?php if ($allCompleted): ?>
                                    <?= htmlspecialchars($texts['group-info']['completed']) ?>
                                <?php else: ?>
                                    <?php echo $completedSubtasksCount; ?>/<?php echo $totalSubtasksCount; ?> <?= htmlspecialchars($texts['group-info']['subtask-completed']) ?>
                                <?php endif; ?>
                                </span>
                                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAHJJREFUSEtjZKAxYKSx+QyjFhAM4dEgGqFB5MDAwDAf6vdEBgaGA1A2qeJgbdhS0X6goSDDQOABAwODIpRNqjhRFoBc74jFAmLEcVoAcn09AwODAgMDA3oQkSKO0wKCSY8UBaM5mWBojQbRaBARDAGCCgBGlxgZvrplfAAAAABJRU5ErkJggg==" alt="Details" onclick="showTaskDetails(this)" style="cursor:pointer;" />
                                <?php if ($is_creator || ((string)$task['user_id'] === $user_id)): ?>
                                    <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="task_id" value="<?php echo $task['_id']; ?>">
                                        <button type="submit" style="border:none; background:none; padding:0;">
                                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAKVJREFUSEvtlcsRgDAIRDedaCdaipVYiqVoKXaiMmNyIIObj7klN0V4C0xch8bHNa4PBpgAbAAGQ8gJYAFwWEIZYH+SBfJ1BDKWAq430RLC4nRErACLRwCfULv70LFuvTnAK6etqxbN74uX9zdAK2TPgZ/aAStYPaIOiO5V7kj6DuivqXpEjJANSDEaDRVXm/VL6yaLi60JbubrmdbJLJONhsabA25OJTgZ1vxrLQAAAABJRU5ErkJggg==" alt="Delete Task" style="cursor:pointer;" />
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="overlay" id="overlay" onclick="closeAllPopups()"></div>

<!-- Create Task Popup -->
<div class="popup" id="create-task-popup">
    <span class="close-btn" onclick="closeCreateTaskPopup()">&times;</span>
    <h2><?= htmlspecialchars($texts['group-info']['create-task-title']) ?></h2>
    <form method="POST" action="group_info.php?id=<?php echo $group_id; ?>">
        <input type="hidden" name="action" value="create_task">
        <input type="text" name="task_name" placeholder="<?= htmlspecialchars($texts['group-info']['create-task-name']) ?>" required>
        <textarea name="task_description" placeholder="<?= htmlspecialchars($texts['group-info']['create-task-description']) ?>"></textarea>
        <input type="date" name="due_date" required>
        <h3><?= htmlspecialchars($texts['group-info']['subtasks']) ?></h3>
        <div id="subtasks-container">
            <div class="subtask">
                <input type="text" name="subtasks[]" placeholder="<?= htmlspecialchars($texts['group-info']['subtask-name']) ?>" required>
                <select name="subtask_assigned_to[]">
                    <?php foreach ($group['members'] as $member_id): ?>
                        <?php $member = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($member_id)]); ?>
                        <option value="<?php echo $member_id; ?>"><?php echo htmlspecialchars($member['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" onclick="removeSubtask(this)"><?= htmlspecialchars($texts['group-info']['remove']) ?></button>
            </div>
        </div>
        <button type="button" onclick="addSubtask()"><?= htmlspecialchars($texts['group-info']['subtask-add']) ?></button>
        <button type="submit"><?= htmlspecialchars($texts['group-info']['create-task-button']) ?></button>
    </form>
</div>

<!-- Task Details Popup -->
<div class="popup" id="task-details-popup">
    <span class="close-btn" onclick="closeTaskDetailsPopup()">&times;</span>
    <h2 id="task-details-name"></h2>
    <br>
    <p id="task-details-description"></p>
    <br>
    <h3><?= htmlspecialchars($texts['group-info']['subtasks']) ?></h3>
    <ul id="task-details-subtasks"></ul>
</div>

<script>
    var groupId = "<?php echo $group_id; ?>";
    var currentUserId = "<?php echo $user_id; ?>";

    // Create Task Popup
    function showCreateTaskPopup() {
        document.getElementById('create-task-popup').style.display = 'block';
        document.getElementById('overlay').style.display = 'block';
    }
    function closeCreateTaskPopup() {
        document.getElementById('create-task-popup').style.display = 'none';
        document.getElementById('overlay').style.display = 'none';
    }

    // Task Details Popup
    function showTaskDetails(imgElement) {
        var li = imgElement.closest('li');
        var taskData = li.getAttribute('data-task');
        try {
            var task = JSON.parse(taskData);
        } catch (e) {
            console.error("Error parsing task data", e);
            return;
        }
        document.getElementById('task-details-name').textContent = task.name;
        document.getElementById('task-details-description').textContent = task.description ? task.description : 'No description available.';

        // Subtask list
        var subtasksHtml = '';
        if (task.subtasks && task.subtasks.length > 0) {
            task.subtasks.forEach(function(subtask) {
                subtasksHtml += '<li>' + subtask.name;
                if (subtask.completed) {
                    subtasksHtml += ' (<?= htmlspecialchars($texts['group-info']['completed']) ?>)';
                } else if (subtask.assigned_to === currentUserId) {
                    subtasksHtml += ' <form method="POST" action="group_info.php?id=' + groupId + '" style="display:inline;">';
                    subtasksHtml += '<input type="hidden" name="action" value="mark_subtask_complete">';
                    subtasksHtml += '<input type="hidden" name="task_id" value="' + task._id.$oid + '">';
                    subtasksHtml += '<input type="hidden" name="subtask_name" value="' + subtask.name + '">';
                    subtasksHtml += '<button type="submit"><?= htmlspecialchars($texts['group-info']['complete']) ?></button>';
                    subtasksHtml += '</form>';
                }
                subtasksHtml += '</li>';
            });
        } else {
            subtasksHtml = '<li>No subtasks</li>';
        }
        document.getElementById('task-details-subtasks').innerHTML = subtasksHtml;

        document.getElementById('task-details-popup').style.display = 'block';
        document.getElementById('overlay').style.display = 'block';
    }
    function closeTaskDetailsPopup() {
        document.getElementById('task-details-popup').style.display = 'none';
        document.getElementById('overlay').style.display = 'none';
    }

    function closeAllPopups() {
        closeCreateTaskPopup();
        closeTaskDetailsPopup();
    }

    // Subtask add
    function addSubtask() {
        let container = document.getElementById("subtasks-container");
        let newSubtask = container.children[0].cloneNode(true);
        newSubtask.querySelector('input[name="subtasks[]"]').value = '';
        container.appendChild(newSubtask);
    }

    function removeSubtask(button) {
        // At least one subtask must remain
        let container = document.getElementById("subtasks-container");
        if (container.children.length > 1) {
            button.parentElement.remove();
        }
    }
</script>
</body>
</html>
