<?php
// Get the current page's filename
$current_page = basename($_SERVER['PHP_SELF'], ".php");
?>
<div class="sidebar">
    <ul>
        <li>
            <a href="index.php" <?php echo $current_page === 'index' ? 'class="active"' : ''; ?>>
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAANBJREFUSEvtldENwjAMRK+b0E1gFEZhEkYpm8Am0JNiyYnc5FzRv+YnUuTcs6I7Z8LBazpYHxnABcCzNHQH8FGaUwEmfi2iFL8pEAXgxa1rnkmQEaAVnwHwbCn7ENIDROL27DJkC9ATT0EigCIuQ1pARlyCeEBrxZEBfAx49x1Z2IvQGeZz1mYArP864qvkpBJhB+zE1t8BJmyd7AVU9yKRE3A+0eY3E5ojclGbB+XjshqOb470bpiYZn6NPnQKhOl9rNOA++60KqCqJpvWNOAHX28/GbZOqjAAAAAASUVORK5CYII="/>
                Home
            </a>
        </li>
        <li>
            <a href="tasks.php" <?php echo $current_page === 'tasks' ? 'class="active"' : ''; ?>>
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAONJREFUSEvVldENwjAMRK+b0E1gFCZBnYRRYBQ2AU6qkXESHJtUiHw1rXPvktrxhI3HtLE+PMAFwN4xcQMwt2IsgGJnALvkzgg7Pk1dZb0F9Dj22G87soD7ulrey9wTtfEv3Z8DPOf2uz2BIouKgCBhKICZxh+qxzAAxZnOTMdFEdIA7VbEWTO2yFIAOqXYYXUq81oFhwF0y+KTHVC05lxOKQzgQg3h/NPdkwJoCJ+bFxuANEAgNi3/u9BqRf7VEfXcGi5gRD9gdUvNFJcdc/zU0SZbu3E7Ws8xhGK8ph8SqwU/AI0gPRkjGfiWAAAAAElFTkSuQmCC"/>
                Tasks
            </a>
        </li>
        <li>
            <a href="friends.php" <?php echo $current_page === 'friends' ? 'class="active"' : ''; ?>>
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAATtJREFUSEvtldENwjAMRK+bwCYwCTAJMAkwCWwCm0CflENpiZP2A4kPIiFE6vidz3Hp9OXVfTm/fhpwknSU9Ki50KpgK2kjaZGSnFPSlaRrSr6uQWqAfZ/0UFBnCADAxFBJcUUADt7TCRJcJKEaW7Bkl74ds4yqiABYQzLUkswL1YDY49n490cVEcAe3yThcQtADLGTASWLaLZ7YkueKeNsizgXNdlN9XN6AmBWkx1MEuziQyLfIPeIOPa5RTybbFEkyPsAsREBnpHidY2anCvPYailmVZtK6moOBNjAEFcTxS2FhBPcd6vQcPHAN/ryFcEALc1+TVGGJUMrMoBblz1VqSyAPlV4aHz+cHs5AAr8IGWRaVh9Fy88+YA3isoC4dmRPQwTp6DD3qrhL4XzTOt/4MJjHrIH9C08AXIclMZxHVfcgAAAABJRU5ErkJggg=="/>
                Friends
            </a>
        </li>
        <li>
            <a href="groups.php" <?php echo $current_page === 'groups' ? 'class="active"' : ''; ?>>
                Groups
            </a>
        </li>
        <li>
            <a href="account.php" <?php echo $current_page === 'account' ? 'class="active"' : ''; ?>>
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAARxJREFUSEvVlNENwjAMRN1NYBOYBJgEmAQ2oZvAJtAnxVIanFxU1A8i5aOJes++2B5s5TWsrG+9gI2Z3cxsZ2avtK/T96gC7AEck3ipBehuZoCqSwGI+JH+viQxsjlMZ3wDObUyUQBsIQMXzyP1zLBpX0tBAZ6TDUS8TdHmOpxzTxbch+sXAILvpFrVUQD85x0ii87pnIfmHRZlkNvgFZM/MqL4Xy1XlQECHmkUIZEDXlym/uOqjaaatXmvLMIeHpldLh8Z+F/t5hoAQZoMa3pWtaMjQD4eWqMAONuzDCsqAnj3Nus7S6ucTbOuLgFd8yXwLK+yWVOWAB9usr4DiFs7G34loDXc1GOHw68EyOElKF//qz5QUcv7/wd8AKJNRBl0Vm5MAAAAAElFTkSuQmCC"/>
                Profile
            </a>
        </li>
    </ul>
    <?php if (isset($_SESSION['user_id'])): ?>
        <ul class="logout">
            <li><a href="logout.php">
                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAMFJREFUSEvVld0NwjAMhL9uApuUSYBJaCdhFNikbAIcJFKKVGJHidTmJQ+x7+zzTzoan64xPqsh6IELoDt37sD4ttVtzmACdjnk5F3gBw/BMzhbJJ3ZWhyEvU2CK3AO0lTNQMUUuDrGKmFa/781EOgtsa5KcAqRO7rxY/obxGIGGqTBi+4hELb0l0RxqKpKFINPSZoQxEyOYadsd9AWezvTCOvcRd51/QD2nnWtCdf6sPwJRR9Owfx9XUr63EXWnOAFtkQoGYmuDuMAAAAASUVORK5CYII="/>
                    Logout
                </a>
            </li>
        </ul>
    <?php endif; ?>
</div>