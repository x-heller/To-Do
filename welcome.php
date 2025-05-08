<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés / Regisztráció</title>
    <link rel="stylesheet" href="Styles/welcome.css">
</head>
<body>

<?php include 'Includes/language.php'; ?>

<header>
    <select id="language-selector">
        <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?>>English</option>
        <option value="hu" <?php echo $lang === 'hu' ? 'selected' : ''; ?>>Hungarian</option>
        <option value="sk" <?php echo $lang === 'sk' ? 'selected' : ''; ?>>Slovak</option>
        <option value="de" <?php echo $lang === 'de' ? 'selected' : ''; ?>>German</option>
    </select>
</header>

<h1><?= $texts['welcome-message'] ?></h1>

<!-- Login Form -->
<div id="login-form">
    <h2><?= $texts['login-title'] ?></h2>
    <form id="loginForm">
        <input type="text" name="username" placeholder="<?= htmlspecialchars($texts['placeholder']['username']) ?>" required><br>
        <input type="password" name="password" placeholder="<?= htmlspecialchars($texts['placeholder']['password']) ?>" required><br>
        <button type="submit"><?= $texts['login-button'] ?></button>
    </form>
    <p><?= $texts['no-account'] ?> <a href="#" id="show-register"><?= $texts['show-register'] ?></a></p>
</div>

<!-- Registration Form -->
<div id="register-form" class="hidden">
    <h2><?= $texts['register-title'] ?></h2>
    <form id="registerForm">
        <input type="text" name="username" placeholder="<?= htmlspecialchars($texts['placeholder']['username']) ?>" required><br>
        <input type="text" name="first_name" placeholder="<?= htmlspecialchars($texts['placeholder']['first-name']) ?>" required><br>
        <input type="text" name="last_name" placeholder="<?= htmlspecialchars($texts['placeholder']['last-name']) ?>" required><br>
        <input type="email" name="email" placeholder="<?= htmlspecialchars($texts['placeholder']['email']) ?>" required><br>
        <input type="password" name="password" placeholder="<?= htmlspecialchars($texts['placeholder']['password']) ?>" required><br>
        <input type="password" name="confirm_password" placeholder="<?= htmlspecialchars($texts['placeholder']['confirm-password']) ?>" required><br>
        <button type="submit"><?= $texts['register-button'] ?></button>
    </form>
    <p><?= $texts['have-account'] ?> <a href="#" id="show-login"><?= $texts['show-login'] ?></a></p>
</div>

<script src="script.js"></script>
</body>
</html>
