<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome</title>
</head>
<body>
<?php include 'navbar.php'; ?>
<h1>Welcome, User!</h1>
<p>Your profile page.</p>
</body>
</html>
