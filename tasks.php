<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$tasksCollection = getMongoDBConnection('tasks');
$usersCollection = getMongoDBConnection('users');

// Retrieve user's friends
$user = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);
$friends = isset($user['friends']) ? $user['friends'] : [];

// Handle adding a new task with friends
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_name']) && isset($_POST['task_description']) && isset($_POST['task_deadline']) && !isset($_POST['task_id']) && !isset($_POST['delete_task_id'])) {
    $task_name = $_POST['task_name'];
    $task_description = $_POST['task_description'];
    $task_deadline = $_POST['task_deadline'];
    $assigned_friends = isset($_POST['assigned_friends']) ? $_POST['assigned_friends'] : [];

    // Insert task into the database
    $tasksCollection->insertOne([
        'user_id' => new MongoDB\BSON\ObjectId($user_id), // Task creator
        'name' => $task_name,
        'description' => $task_description,
        'deadline' => $task_deadline,
        'completed' => false,
        'assigned_friends' => array_map(function($friend_id) {
            return new MongoDB\BSON\ObjectId($friend_id);
        }, $assigned_friends)
    ]);

    header("Location: tasks.php");
    exit;
}

// Handle marking task as completed or uncompleted
if (isset($_POST['task_id']) && isset($_POST['completed'])) {
    $task_id = $_POST['task_id'];
    $completed = $_POST['completed'] === 'true';

    $tasksCollection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($task_id), '$or' => [
            ['user_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['assigned_friends' => new MongoDB\BSON\ObjectId($user_id)]
        ]],
        ['$set' => ['completed' => $completed]]
    );

    header("Location: tasks.php");
    exit;
}

// Handle deleting a task
if (isset($_POST['delete_task_id'])) {
    $delete_task_id = $_POST['delete_task_id'];

    $tasksCollection->deleteOne([
        '_id' => new MongoDB\BSON\ObjectId($delete_task_id),
        '$or' => [
            ['user_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['assigned_friends' => new MongoDB\BSON\ObjectId($user_id)]
        ]
    ]);

    header("Location: tasks.php");
    exit;
}

// Retrieve all tasks for the user and their assigned friends
$tasks = $tasksCollection->find([
    '$or' => [
        ['user_id' => new MongoDB\BSON\ObjectId($user_id)],
        ['assigned_friends' => new MongoDB\BSON\ObjectId($user_id)]
    ]
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks</title>
    <link rel="stylesheet" href="Styles/style.css">
    <link rel="stylesheet" href="Styles/tasks.css">
    <script>
        function confirmCompletion(form) {
            if (confirm("Are you sure you want to mark this task as completed?")) {
                form.submit();
            }
        }

        function confirmDeletion(form) {
            if (confirm("Are you sure you want to delete this task?")) {
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
    <h1>Your Tasks</h1>

    <!-- New Task Button -->
    <button id="new-task-button">New Task</button>

    <!-- Task Form (Initially Hidden) -->
    <form class="new-task" id="new-task-form" method="POST" action="tasks.php" style="display: none;">
        <label for="task_name">Task Name:</label>
        <input type="text" id="task_name" name="task_name" required><br>

        <label for="task_description">Description:</label>
        <textarea id="task_description" name="task_description" required></textarea><br>

        <label for="task_deadline">Deadline:</label>
        <input type="date" id="task_deadline" name="task_deadline" required><br>

        <label>Assign to Friends:</label><br>
        <?php foreach ($friends as $friend_id): ?>
            <?php $friend = $usersCollection->findOne(['_id' => $friend_id]); ?>
            <?php if ($friend): ?>
                <input type="checkbox" name="assigned_friends[]" value="<?php echo $friend['_id']; ?>">
                <?php echo htmlspecialchars($friend['username']); ?><br>
            <?php endif; ?>
        <?php endforeach; ?>

        <button type="submit">Add Task</button>
    </form>

    <h2>Your Task List</h2>

    <!-- Display Tasks -->
    <ul>
        <?php foreach ($tasks as $task): ?>
            <li class="task">
                <!-- Task Summary: Includes Completion Checkbox -->
                <div class="task-summary">
                    <!-- Completion Checkbox -->
                    <form method="POST" action="tasks.php" style="display:inline;">
                        <input type="checkbox" name="completed" value="true"
                            <?php echo $task['completed'] ? 'checked' : ''; ?>
                               onchange="this.form.submit()">
                        <input type="hidden" name="task_id" value="<?php echo $task['_id']; ?>">
                    </form>

                    <span class="task-name"><?php echo htmlspecialchars($task['name']); ?></span> -
                    <span class="task-deadline">(Due: <?php echo htmlspecialchars($task['deadline']); ?>)</span>

                    <!-- Button to Expand/Collapse the Task Details -->
                    <button type="button" class="toggle-details-btn">Show Details</button>

                    <!-- Delete Button -->
                    <form method="POST" action="tasks.php" style="display:inline;" onsubmit="event.preventDefault(); confirmDeletion(this);">
                        <input type="hidden" name="delete_task_id" value="<?php echo $task['_id']; ?>">
                        <button type="submit">X</button>
                    </form>
                </div>

                <!-- Extended Task Information (Initially Hidden) -->
                <div class="task-details" style="display:none;">
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($task['description']); ?></p>

                    <?php
                    // Convert BSONArray to PHP array, default to empty if not set
                    $assigned_friends = isset($task['assigned_friends']) ? (array)$task['assigned_friends'] : [];

                    // Include the task creator (user) in the assigned users
                    $assigned_users = [new MongoDB\BSON\ObjectId($task['user_id'])];
                    $assigned_users = array_merge($assigned_users, $assigned_friends);

                    // Fetch usernames for assigned users
                    $assigned_usernames = [];
                    foreach ($assigned_users as $user_id) {
                        $assigned_user = $usersCollection->findOne(['_id' => $user_id]);
                        if ($assigned_user) {
                            $assigned_usernames[] = htmlspecialchars($assigned_user['username']);
                        }
                    }

                    // Display assigned users (including creator)
                    if (!empty($assigned_usernames)):
                        echo "<p><strong>Assigned users:</strong> " . implode(", ", $assigned_usernames) . "</p>";
                    endif;
                    ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>



    <!-- JavaScript to Toggle New Task Form -->
    <script>
        document.getElementById('new-task-button').addEventListener('click', function() {
            var form = document.getElementById('new-task-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });

        // Add an event listener for all "Show Details" buttons
        document.querySelectorAll('.toggle-details-btn').forEach(button => {
            button.addEventListener('click', function() {
                var taskDetails = this.closest('li').querySelector('.task-details');
                var buttonText = taskDetails.style.display === 'none' ? 'Hide Details' : 'Show Details';
                this.textContent = buttonText;
                taskDetails.style.display = taskDetails.style.display === 'none' ? 'block' : 'none';
            });
        });
    </script>

</div>



</body>
</html>
