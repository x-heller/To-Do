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
$notificationsCollection = getMongoDBConnection('notifications');

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
        'user_id' => new MongoDB\BSON\ObjectId($user_id),
        'name' => $task_name,
        'description' => $task_description,
        'deadline' => $task_deadline,
        'completed' => false,
        'verified_task' => $is_verified,
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

        // Update the task with "pending" status instead of "completed"
        $tasksCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($task_id)],
            [
                '$set' => [
                    'completed' => false,
                    'status' => 'pending',
                    'verification_image' => $imagePath
                ]
            ]
        );

        // Create notifications for all friends
        foreach ($friends as $friend_id) {
            $notificationsCollection->insertOne([
                'sender_id' => new MongoDB\BSON\ObjectId($user_id),
                'receiver_id' => $friend_id,
                'type' => 'task_pending',
                'task_id' => new MongoDB\BSON\ObjectId($task_id),
                'status' => 'pending',
                'message' => 'A task is pending your verification.'
            ]);
        }

    } else if (!$task['verified_task']) {
        // Non-verified tasks can be completed directly
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

//if the deadline is passed, the task is marked as failed
$tasksCollection->updateMany(
    ['deadline' => ['$lt' => date('Y-m-d')], 'completed' => false],
    ['$set' => ['status' => 'failed']]
);

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
        function showDetailsPopup(taskId) {
            document.getElementById('task-details-popup-' + taskId).style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        }

        function closeDetailsPopup(taskId) {
            document.getElementById('task-details-popup-' + taskId).style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }

        function confirmDeletion(form) {
            if (confirm('Are you sure you want to delete this task?')) {
                form.submit();
            }
        }
    </script>
</head>
<body>
<?php include 'Includes/header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'Includes/language.php'; ?>

<!-- Content -->
<div class="main-content">

    <div class="title">
        <h1><?= htmlspecialchars($texts['tasks']['title']) ?></h1>
        <button id="new-task-button"><?= htmlspecialchars($texts['tasks']['new-task']) ?></button>
    </div>

    <div class="overlay" id="overlay" onclick="closeDetailsPopup()"></div>

    <div class="popup" id="task-popup">
        <span class="close-btn" id="close-popup">&times;</span>
        <h2><?= htmlspecialchars($texts['tasks']['new-task']) ?></h2>
        <form id="new-task-form" method="POST" action="tasks.php" class="task-form">
            <div class="form-columns">
                <div class="form-column">
                    <label for="task_name"><?= htmlspecialchars($texts['tasks']['task-name']) ?></label>
                    <input type="text" id="task_name" name="task_name" required>

                    <label for="task_description"><?= htmlspecialchars($texts['tasks']['task-description']) ?></label>
                    <textarea id="task_description" name="task_description" required></textarea>

                    <label for="task_deadline"><?= htmlspecialchars($texts['tasks']['task-deadline']) ?></label>
                    <input type="date" id="task_deadline" name="task_deadline" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-column">
                    <label><?= htmlspecialchars($texts['tasks']['assign']) ?></label>
                    <div class="friends-list">
                        <?php foreach ($friends as $friend_id): ?>
                            <?php $friend = $usersCollection->findOne(['_id' => $friend_id]); ?>
                            <?php if ($friend): ?>
                                <div class="friend-option">
                                    <input type="checkbox" name="assigned_friends[]" value="<?php echo $friend['_id']; ?>">
                                    <?php echo htmlspecialchars($friend['username']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn"><?= htmlspecialchars($texts['tasks']['add-task']) ?></button>
        </form>
    </div>

    <ul id="task-list">
        <?php foreach ($tasks as $task): ?>
            <li class="task-item
            <?php
            if (isset($task['status'])) {
                echo $task['status'] === 'pending' ? 'pending-task' :
                    ($task['status'] === 'failed' ? 'failed-task' :
                        ($task['status'] === 'completed' ? 'completed-task' : ''));
            }
            ?>">
                <div class="task-summary">
                    <form method="POST" action="tasks.php" enctype="multipart/form-data" style="display:inline;">
                        <input type="checkbox" name="completed" id="task_<?php echo $task['_id']; ?>" value="true"
                            <?php echo $task['completed'] ? 'checked' : ''; ?>
                            <?php echo ($task['verified_task'] && !$task['completed']) || (isset($task['status']) && ($task['status'] === 'pending' || $task['status'] === 'failed')) ? 'disabled' : ''; ?>
                               onchange="this.form.submit()">
                        <?php if ($task['verified_task'] && !$task['completed'] && (!isset($task['status']) || ($task['status'] !== 'pending' && $task['status'] !== 'failed'))): ?>
                            <label for="verification_image_<?php echo $task['_id']; ?>" class="custom-file-upload">
                                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAKtJREFUSEvVlcENhCAURJ+drJ1oKVuJsRJbsRPtxHVMTJCI8IO4kQsXnMf8MUNF4VUV1udRQAN0gHZ/jcAXmK2OXQcT8LkQkHhrhbiAxXq7wHm57ddJaD9kcBdAuhKX21NAbvD7RTedsxH9FXC4XSCDLAfvBMT+Lj8z84iKA9ws35nBow5S6soccopo0KWlri0gVXvtd5EemiHyJqRAgnWd8rH5TG5zRoHFAT8gkzAZEYa47gAAAABJRU5ErkJggg=="/>
                            </label>
                            <input type="file" name="verification_image" id="verification_image_<?php echo $task['_id']; ?>"
                                   required
                                   onchange="enableCheckbox('<?php echo $task['_id']; ?>')">
                        <?php endif; ?>
                        <input type="hidden" name="task_id" value="<?php echo $task['_id']; ?>">
                    </form>

                    <span class="task-name"><?php echo htmlspecialchars($task['name']); ?></span>
                    <span class="task-deadline"><?php echo htmlspecialchars($task['deadline']); ?></span>

                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAHJJREFUSEtjZKAxYKSx+QyjFhAM4dEgGqFB5MDAwDAf6vdEBgaGA1A2qeJgbdhS0X6goSDDQOABAwODIpRNqjhRFoBc74jFAmLEcVoAcn09AwODAgMDA3oQkSKO0wKCSY8UBaM5mWBojQbRaBARDAGCCgBGlxgZvrplfAAAAABJRU5ErkJggg==" onclick="showDetailsPopup('<?php echo $task['_id']; ?>')" style="cursor: pointer;">
                    <form method="POST" action="tasks.php" style="display:inline;" onsubmit="event.preventDefault(); openModal('<?php echo $task['_id']; ?>');">
                        <input type="hidden" name="delete_task_id" value="<?php echo $task['_id']; ?>">
                        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAKVJREFUSEvtlcsRgDAIRDedaCdaipVYiqVoKXaiMmNyIIObj7klN0V4C0xch8bHNa4PBpgAbAAGQ8gJYAFwWEIZYH+SBfJ1BDKWAq430RLC4nRErACLRwCfULv70LFuvTnAK6etqxbN74uX9zdAK2TPgZ/aAStYPaIOiO5V7kj6DuivqXpEjJANSDEaDRVXm/VL6yaLi60JbubrmdbJLJONhsabA25OJTgZ1vxrLQAAAABJRU5ErkJggg==" onclick="openModal('<?php echo $task['_id']; ?>')" style="cursor: pointer;">
                    </form>
                </div>

                <div class="details-popup" id="task-details-popup-<?php echo $task['_id']; ?>">
                    <span class="close-btn" onclick="closeDetailsPopup('<?php echo $task['_id']; ?>')">&times;</span>
                    <h2><?php echo htmlspecialchars($task['name']); ?></h2><br>

                    <p><strong><?= htmlspecialchars($texts['tasks']['task-deadline']) ?></strong> <?php echo htmlspecialchars($task['deadline']); ?></p>

                    <p><strong><?= htmlspecialchars($texts['tasks']['task-description']) ?></strong> <?php echo htmlspecialchars($task['description']); ?></p>

                    <?php if (isset($task['verification_image']) && $task['completed']): ?>
                        <p><strong><?= htmlspecialchars($texts['tasks']['verification-image']) ?></strong></p>
                        <img src="<?php echo htmlspecialchars($task['verification_image']); ?>" alt="Verification Image" style="max-width:200px;">
                    <?php endif; ?>
                    <br><p><strong><?= htmlspecialchars($texts['tasks']['assigned-friends']) ?></strong></p>
                    <?php
                    $assigned_friends = isset($task['assigned_friends']) ? (array)$task['assigned_friends'] : [];
                    $assigned_users = [new MongoDB\BSON\ObjectId($task['user_id'])];
                    $assigned_users = array_merge($assigned_users, $assigned_friends);

                    $assigned_usernames = [];
                    foreach ($assigned_users as $user_id) {
                        $assigned_user = $usersCollection->findOne(['_id' => $user_id]);
                        if ($assigned_user) {
                            $assigned_usernames[] = htmlspecialchars($assigned_user['username']);
                        }
                    }

                    if (!empty($assigned_usernames)):
                        echo "<p>" . implode(", ", $assigned_usernames) . "</p>";
                    endif;
                    ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Deletion Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2><?= htmlspecialchars($texts['tasks']['delete-task']) ?></h2>
            <p><?= htmlspecialchars($texts['tasks']['confirm-delete']) ?></p>
            <form id="deleteForm" method="POST" action="tasks.php">
                <input type="hidden" name="delete_task_id" id="delete_task_id">
                <button type="submit" class="btn btn-danger"><?= htmlspecialchars($texts['tasks']['delete']) ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()"><?= htmlspecialchars($texts['tasks']['cancel']) ?></button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const showForm = <?php echo json_encode($showNewTaskForm); ?>;
            if (showForm) {
                const newTaskForm = document.getElementById('new-task-form');
                if (newTaskForm) {
                    newTaskForm.style.display = 'block';
                }
            }
        });

        document.getElementById('new-task-button').addEventListener('click', function() {
            document.getElementById('task-popup').style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        });

        document.getElementById('close-popup').addEventListener('click', function() {
            document.getElementById('task-popup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        });

        document.getElementById('overlay').addEventListener('click', function() {
            const popups = document.querySelectorAll('.details-popup');
            popups.forEach(popup => popup.style.display = 'none');
            document.getElementById('overlay').style.display = 'none';
        });

        document.getElementById('overlay').addEventListener('click', function() {
            const popups = document.querySelectorAll('.popup');
            popups.forEach(popup => popup.style.display = 'none');
            document.getElementById('overlay').style.display = 'none';
        });


        function enableCheckbox(taskId) {
            const checkbox = document.getElementById(`task_${taskId}`);
            checkbox.disabled = false;
        }

        function openModal(taskId) {
            document.getElementById('delete_task_id').value = taskId;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>

</div>

<script src="script.js"></script>

</body>
</html>