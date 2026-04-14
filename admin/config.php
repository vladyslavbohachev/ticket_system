<?php
$host = "";
$dbname = "";
$user = "";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}
?>