<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require 'config.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Hole Admin aus DB
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$_SESSION['admin_username']]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Admin nicht gefunden.";
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        $error = "Aktuelles Passwort ist falsch.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwörter stimmen nicht überein.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Neues Passwort muss mindestens 6 Zeichen haben.";
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $user['id']]);
        $success = "Passwort erfolgreich geändert!";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Passwort ändern</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 400px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; }
        button { width: 100%; padding: 10px; background: #007BFF; color: white; border: none; cursor: pointer; }
        .error { color: red; background: #ffe5e5; padding: 10px; border: 1px solid #f99; border-radius: 4px; }
        .success { color: green; background: #e5ffe5; padding: 10px; border: 1px solid #9f9; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Passwort ändern</h2>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <label>Aktuelles Passwort</label>
            <input type="password" name="current_password" required>

            <label>Neues Passwort</label>
            <input type="password" name="new_password" required>

            <label>Neues Passwort bestätigen</label>
            <input type="password" name="confirm_password" required>

            <button type="submit">Passwort speichern</button>
        </form>

        <p><a href="dashboard.php">← Zurück zum Dashboard</a></p>
    </div>
</body>
</html>