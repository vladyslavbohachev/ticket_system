<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    header('Location: login.php');
    exit();
}

require 'config.php';

$id = $_GET['id'] ?? 0;

if ($id && $id != $_SESSION['admin_id']) {
    try {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Benutzer erfolgreich gelöscht!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Fehler beim Löschen des Benutzers: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Sie können sich nicht selbst löschen.";
}

header('Location: users-configuration.php');
exit();
?>