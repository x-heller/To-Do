<?php
$current_page = basename($_SERVER['PHP_SELF'], ".php");
?>

<?php include 'Includes/language.php'; ?>
<header>
    <span class="current-page">
        <?php echo $current_page === 'index' ? 'Home' : ucfirst($current_page); ?>
    </span>

    <select id="language-selector">
        <option value="hu" <?= ($lang == 'hu') ? 'selected' : '' ?>>Magyar</option>
        <option value="en" <?= ($lang == 'en') ? 'selected' : '' ?>>English</option>
        <option value="de" <?= ($lang == 'de') ? 'selected' : '' ?>>Deutsch</option>
    </select>

    <a href="../notifications.php">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAASpJREFUSEvdlVESgjAMRLM3kZvoSZSTCCfRm8hN5CaRddqZUpumDMOP/PBBk5dstgFy8IOD80szQFXPIvIIBfUAppbiXICqnkTkuiQbkoSziDwBjB6kCghVvypJCBoBPK0zJiBLTjmYaAodUS52xXcVUgO8FxkoD6XoSxWq6j1INwPoSmeKgCRwAnCp6ayqlJCdDKWZWIAYRLeY+hKcSFksxgJEeToA1Nh8wkx4viiTBVBmXFp2bRy6MM//ISDRdLNEIvIzs5VEITn3DW3nWjROPrEqDXFJjZEDoj3Ni2NcOF5IxvK9is0Bm9yTwixpiwBvQ3rfU3vnAOp/8xI431eza7pIe4BNAFVlV/FvFnnF5ZYX0wQI64CrmfaNK9z9m33XzZ72W2IPB3wAl/iWGUCC7ZQAAAAASUVORK5CYII="/>
    </a>
</header>

<script src="../script.js"></script>
