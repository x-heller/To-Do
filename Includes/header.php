<?php
$current_page = basename($_SERVER['PHP_SELF'], ".php");
$notifications = getMongoDBConnection('notifications');
$notification_count = $notifications->countDocuments([
    'receiver_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id']),
    'status' => 'pending'
]);
?>

<?php include 'Includes/language.php'; ?>
<header>
    <div class="header-icon" onclick="toggleSidebar()">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAFFJREFUSEvtlEkKADAIA83/H52eu1APZQShnquTBaqAR/D9+IA04fqIbDuVdXkgaRK9OcABL+pPu/Ud4A7wDnAAHlF/AN4BDujfwXewJoD/pgNrVBgZX2h0sgAAAABJRU5ErkJggg=="/>
    </div>

    <span class="current-page">
        <?php echo $current_page === 'index' ? 'Home' : ucfirst($current_page); ?>
    </span>

    <select id="language-selector">
        <option value="hu" <?= ($lang == 'hu') ? 'selected' : '' ?>>Magyar</option>
        <option value="sk" <?= ($lang == 'sk') ? 'selected' : '' ?>>Slovenský</option>
        <option value="en" <?= ($lang == 'en') ? 'selected' : '' ?>>English</option>
        <option value="de" <?= ($lang == 'de') ? 'selected' : '' ?>>Deutsch</option>
    </select>

    <a href="../notifications.php" class="notification-icon">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAASpJREFUSEvdlVESgjAMRLM3kZvoSZSTCCfRm8hN5CaRddqZUpumDMOP/PBBk5dstgFy8IOD80szQFXPIvIIBfUAppbiXICqnkTkuiQbkoSziDwBjB6kCghVvypJCBoBPK0zJiBLTjmYaAodUS52xXcVUgO8FxkoD6XoSxWq6j1INwPoSmeKgCRwAnCp6ayqlJCdDKWZWIAYRLeY+hKcSFksxgJEeToA1Nh8wkx4viiTBVBmXFp2bRy6MM//ISDRdLNEIvIzs5VEITn3DW3nWjROPrEqDXFJjZEDoj3Ni2NcOF5IxvK9is0Bm9yTwixpiwBvQ3rfU3vnAOp/8xI431eza7pIe4BNAFVlV/FvFnnF5ZYX0wQI64CrmfaNK9z9m33XzZ72W2IPB3wAl/iWGUCC7ZQAAAAASUVORK5CYII="/>
        <?php if ($notification_count > 0): ?>
            <span class="notification-count"><?php echo $notification_count; ?></span>
        <?php endif; ?>
    </a>
</header>

<script src="../script.js"></script>

<script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
    }
</script>