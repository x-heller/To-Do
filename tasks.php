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

$showNewTaskForm = isset($_GET['show_new_task_form']) && $_GET['show_new_task_form'] === 'true';


// Handle adding a new task with friends
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_name']) && isset($_POST['task_description']) && isset($_POST['task_deadline']) && !isset($_POST['task_id']) && !isset($_POST['delete_task_id'])) {
    $task_name = $_POST['task_name'];
    $task_description = $_POST['task_description'];
    $task_deadline = $_POST['task_deadline'];
    $assigned_friends = isset($_POST['assigned_friends']) ? $_POST['assigned_friends'] : [];
    $is_verified = isset($user['verification']) && $user['verification'] === true;

    // Insert task into the database
    $tasksCollection->insertOne([
        'user_id' => new MongoDB\BSON\ObjectId($user_id), // Task creator
        'name' => $task_name,
        'description' => $task_description,
        'deadline' => $task_deadline,
        'completed' => false,
        'verified_task' => $is_verified, // Indicate whether the task was created by a verified user
        'assigned_friends' => array_map(function ($friend_id) {
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

    // Retrieve the task
    $task = $tasksCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($task_id)]);

    if ($task['verified_task'] && !$task['completed']) {
        // Handle file upload for verified tasks
        if (!isset($_FILES['verification_image']) || $_FILES['verification_image']['error'] !== UPLOAD_ERR_OK) {
            echo "Please upload an image to verify task completion.";
            exit;
        }

        $imageFile = $_FILES['verification_image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB max size

        if (!in_array($imageFile['type'], $allowedTypes)) {
            echo "Only JPEG, PNG, or GIF files are allowed.";
            exit;
        }

        if ($imageFile['size'] > $maxFileSize) {
            echo "File size exceeds 2MB.";
            exit;
        }

        // Save the image
        $uploadDir = 'uploads/task_verifications/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
        $uniqueName = $task_id . '_' . time() . '.' . $ext;
        $imagePath = $uploadDir . $uniqueName;

        if (!move_uploaded_file($imageFile['tmp_name'], $imagePath)) {
            echo "Failed to save the uploaded image.";
            exit;
        }

        // Update the task with completion and image path
        $tasksCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($task_id)],
            ['$set' => ['completed' => $completed, 'verification_image' => $imagePath]]
        );

        // Add XP to the user
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['$inc' => ['xp' => 10]]
        );

    } else if (!$task['verified_task']) {
        // Non-verified tasks can be completed without an image
        $tasksCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($task_id)],
            ['$set' => ['completed' => $completed]]
        );
    }

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
                    <!-- Completion Checkbox and Verification Image Upload -->
                    <form method="POST" action="tasks.php" enctype="multipart/form-data" style="display:inline;">
                        <?php if ($task['verified_task'] && !$task['completed']): ?>
                            <input type="file" name="verification_image" id="verification_image_<?php echo $task['_id']; ?>" required
                                   onchange="enableCheckbox('<?php echo $task['_id']; ?>')">
                        <?php endif; ?>

                        <input type="checkbox" name="completed" id="completed_<?php echo $task['_id']; ?>" value="true"
                            <?php echo $task['completed'] ? 'checked' : ''; ?>
                            <?php echo ($task['verified_task'] && !$task['completed']) ? 'disabled' : ''; ?>
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

                    <?php if (isset($task['verification_image']) && $task['completed']): ?>
                        <p><strong>Verification Image:</strong></p>
                        <img src="<?php echo htmlspecialchars($task['verification_image']); ?>" alt="Verification Image" style="max-width:200px;">
                    <?php endif; ?>

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
        document.addEventListener("DOMContentLoaded", function() {
            // Ellenőrizzük, hogy a PHP-ből jövő $showNewTaskForm értéke igaz-e
            const showForm = <?php echo json_encode($showNewTaskForm); ?>;
            if (showForm) {
                const newTaskForm = document.getElementById('new-task-form');
                if (newTaskForm) {
                    newTaskForm.style.display = 'block';
                }
            }
        });

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

        function enableCheckbox(taskId) {
            const checkbox = document.getElementById(`completed_${taskId}`);
            checkbox.disabled = false;
        }
    </script>

</div>



</body>
</html>
