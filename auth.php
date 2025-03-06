<?php
session_start();
require 'connect.php';

header('Content-Type: application/json');

$response = ["success" => false, "message" => ""];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action === "register") {
        $username = $_POST['username'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password !== $confirm_password) {
            $response["message"] = "A jelszavak nem egyeznek!";
        } else {
            $users = getMongoDBConnection('users');
            if ($users->findOne(['email' => $email])) {
                $response["message"] = "A felhasználó már létezik!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $users->insertOne([
                    'username' => $username,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'password' => $hashed_password,
                    'xp' => 0,
                    'friends' => [],
                    'groups' => [],
                    'verification' => false,
                    'profile_picture' => 'Images/default_avatar.jpg'
                ]);

                $response["success"] = true;
                $response["message"] = "Sikeres regisztráció!";
            }
        }
    } elseif ($action === "login") {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $users = getMongoDBConnection('users');
        $user = $users->findOne(['username' => $username]);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (string)$user['_id'];
            $response["success"] = true;
            $response["message"] = "Sikeres bejelentkezés!";
        } else {
            $response["message"] = "Érvénytelen felhasználónév vagy jelszó!";
        }
    }
}

echo json_encode($response);
?>
