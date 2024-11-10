<?php
session_start();
?>
<nav>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="account.php">Profile</a></li>
        <li><a href="notifications.php">Notifications</a></li>
        <li><a href="friends.php">Friends</a></li>

        <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="welcome.php">Login/Register</a></li>
        <?php endif; ?>
    </ul>
</nav>
