<?php
session_start();
require 'connect.php';

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

if (!isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$groupsCollection = getMongoDBConnection('groups');
$usersCollection = getMongoDBConnection('users');
$tasksCollection = getMongoDBConnection('group_tasks');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_task') {
        $task_name = $_POST['task_name'];
        $task_description = $_POST['task_description'];
        $due_date = $_POST['due_date'];
        $subtasks = [];

        foreach ($_POST['subtasks'] as $index => $subtask_name) {
            $subtasks[] = [
                'name' => $subtask_name,
                'assigned_to' => $_POST['assigned_members'][$index],
                'due_date' => $_POST['subtask_due_dates'][$index],
                'completed' => false
            ];
        }

        $tasksCollection->insertOne([
            'group_id' => new MongoDB\BSON\ObjectId($group_id),
            'name' => $task_name,
            'description' => $task_description,
            'due_date' => $due_date,
            'subtasks' => $subtasks
        ]);
    }

    if ($action === 'mark_subtask_complete') {
        $task_id = $_POST['task_id'];
        $subtask_name = $_POST['subtask_name'];

        $task = $tasksCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($task_id)]);

        foreach ($task['subtasks'] as &$subtask) {
            if ($subtask['name'] === $subtask_name && $subtask['assigned_to'] === $user_id) {
                $tasksCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($task_id), 'subtasks.name' => $subtask_name],
                    ['$set' => ['subtasks.$.completed' => true]]
                );
            }
        }
    }
}

$tasks = $tasksCollection->find(['group_id' => new MongoDB\BSON\ObjectId($group_id)]);

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
    <link rel="stylesheet" href="Styles/group_info.css">
</head>
<body>
<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="hearder">
        <h1><?php echo htmlspecialchars($group['name']); ?></h1>

        <a href="groups.php">Back to Groups</a>
    </div>

    <div class="info">
        <p><strong>Description:</strong> <?php echo htmlspecialchars($group['description']); ?></p>
        <p>
            <strong>Invitation Code:</strong>
            <span id="invitationCode"><?php echo $invitation_code; ?></span>
            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAKJJREFUSEvtldENgCAMRI9NdBMdxUkcxVF0FDdRmxhSTGsO0D/45egj9LgG/LzCz/XBAAYAC4COvMwOYAKwiZ4BrJdYIDlLID0LOO7KzGVEmuiZQw0Qe1flFuUAtwdVbmEAVc1sAOsXe0/qNrm0BxZccmh8RsVXADfsSgGvcaM3G8CcF/qJSqIiusWbRhogYTdnTK/ELQwgZyTSWmai0cUs4QkRJDQZFdPzyQAAAABJRU5ErkJggg=="
                 alt="Copy"
                 style="cursor: pointer; margin-left: 5px;"
                 onclick="copyToClipboard()"/>
        </p>
    </div>

    <div class="members">
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
    </div>

    <div class="manage">
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
    </div>

    <div class="tasks">
        <h1>Group Tasks</h1>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_task">
            <input type="text" name="task_name" placeholder="Task Name" required>
            <textarea name="task_description" placeholder="Task Description"></textarea>
            <input type="date" name="due_date" required>
            <h3>Subtasks</h3>
            <div id="subtasks-container">
                <div class="subtask">
                    <input type="text" name="subtasks[]" placeholder="Subtask Name" required>
                    <input type="date" name="subtask_due_dates[]" required>
                    <select name="assigned_members[]">
                        <?php foreach ($group['members'] as $member_id): ?>
                            <option value="<?php echo $member_id; ?>"><?php echo htmlspecialchars($usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($member_id)])['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="removeSubtask(this)">Remove</button>
                </div>
            </div>
            <button type="button" onclick="addSubtask()">Add Subtask</button>
            <button type="submit">Create Task</button>
        </form>

        <h2>Tasks</h2>
        <ul>
            <?php foreach ($tasks as $task): ?>
                <li>
                    <strong><?php echo htmlspecialchars($task['name']); ?></strong>
                    <p><?php echo htmlspecialchars($task['description']); ?></p>
                    <p>Due Date: <?php echo htmlspecialchars($task['due_date']); ?></p>
                    <h3>Subtasks:</h3>
                    <ul>
                        <?php foreach ($task['subtasks'] as $subtask): ?>
                            <li>
                                <?php echo htmlspecialchars($subtask['name']); ?> - Assigned to: <?php echo htmlspecialchars($subtask['assigned_to']); ?>
                                <p>Due Date: <?php echo htmlspecialchars($subtask['due_date']); ?></p>
                                <?php if ($subtask['assigned_to'] === $user_id): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="mark_subtask_complete">
                                        <input type="hidden" name="task_id" value="<?php echo (string)$task['_id']; ?>">
                                        <input type="hidden" name="subtask_name" value="<?php echo htmlspecialchars($subtask['name']); ?>">
                                        <button type="submit" <?php echo $subtask['completed'] ? 'disabled' : ''; ?>>Mark Completed</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>


</div>
<script>
    function copyToClipboard() {
        var invitationCode = document.getElementById("invitationCode").textContent;
        var tempInput = document.createElement("input");
        tempInput.value = invitationCode;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        alert("Invitation code copied to clipboard!");
    }

    function addSubtask() {
        let container = document.getElementById("subtasks-container");
        let newSubtask = container.children[0].cloneNode(true);
        container.appendChild(newSubtask);

        newSubtask.querySelector('input[name="subtasks[]"]').value = '';
        newSubtask.querySelector('input[name="subtask_due_dates[]"]').value = '';
    }

    function removeSubtask(button) {
        button.parentElement.remove();
    }
</script>
</body>
</html>