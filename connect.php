<?php
require 'vendor/autoload.php';

function getMongoDBConnection($collectionName) {
    $uri = 'mongodb+srv://Felhasználónév:Jelszó@cluster0.afhbz.mongodb.net/?retryWrites=true&w=majority&appName=Cluster0';

    try {
        $client = new MongoDB\Client($uri);
        $db = $client->todo;
        return $db->$collectionName;
    } catch (MongoDB\Driver\Exception\Exception $e) {
        echo "Connection failed: " . $e->getMessage();
        exit;
    }
}
?>



