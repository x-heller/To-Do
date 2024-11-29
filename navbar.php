<?php
/*session_start();
*/?><!--
<nav>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="tasks.php">Tasks</a></li>
        <li><a href="account.php">Profile</a></li>
        <li><a href="notifications.php">Notifications</a></li>
        <li><a href="friends.php">Friends</a></li>

        <?php /*if (isset($_SESSION['user_id'])): */?>
            <li><a href="logout.php">Logout</a></li>
        <?php /*else: */?>
            <li><a href="welcome.php">Login/Register</a></li>
        <?php /*endif; */?>
    </ul>
</nav>-->

<?php
// Get the current page's filename
$current_page = basename($_SERVER['PHP_SELF'], ".php");
?>
<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('visible');
    }
</script>
<nav class="navbar">
    <div class="nav-left">
        <!-- Mobile Sidebar Toggle Button -->
<!--        <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>-->
        <img class="sidebar-toggle" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAFFJREFUSEvtlEkKADAIA83/H52eu1APZQShnquTBaqAR/D9+IA04fqIbDuVdXkgaRK9OcABL+pPu/Ud4A7wDnAAHlF/AN4BDujfwXewJoD/pgNrVBgZX2h0sgAAAABJRU5ErkJggg==" onclick="toggleSidebar()"/>

        <span class="current-page">
        <?php echo $current_page === 'index' ? 'Home' : ucfirst($current_page); ?>
        </span>

    </div>

    <div class="nav-right">
        <ul>
            <li>
                <a href="notifications.php">
                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAASpJREFUSEvdlVESgjAMRLM3kZvoSZSTCCfRm8hN5CaRddqZUpumDMOP/PBBk5dstgFy8IOD80szQFXPIvIIBfUAppbiXICqnkTkuiQbkoSziDwBjB6kCghVvypJCBoBPK0zJiBLTjmYaAodUS52xXcVUgO8FxkoD6XoSxWq6j1INwPoSmeKgCRwAnCp6ayqlJCdDKWZWIAYRLeY+hKcSFksxgJEeToA1Nh8wkx4viiTBVBmXFp2bRy6MM//ISDRdLNEIvIzs5VEITn3DW3nWjROPrEqDXFJjZEDoj3Ni2NcOF5IxvK9is0Bm9yTwixpiwBvQ3rfU3vnAOp/8xI431eza7pIe4BNAFVlV/FvFnnF5ZYX0wQI64CrmfaNK9z9m33XzZ72W2IPB3wAl/iWGUCC7ZQAAAAASUVORK5CYII="/>
                </a>
            </li>
        </ul>
    </div>
</nav>
