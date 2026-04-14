<?php
require_once 'includes/header.php';

// Zugriffskontrolle
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require 'config.php';

$is_superadmin = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$current_user_id = $_SESSION['admin_id'] ?? 0;
$error = '';
$success = '';
$action = $_GET['action'] ?? '';
$edit_id = (int)($_GET['id'] ?? 0);

// Aktuelle Benutzer laden
$users = [];
try {
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, username, email, role, status, 
               last_login, last_login_ip, created_at, updated_at 
        FROM admins 
        ORDER BY 
            CASE role 
                WHEN 'superadmin' THEN 1
                WHEN 'admin' THEN 2
                WHEN 'support' THEN 3
            END,
            created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Fehler beim Laden der Benutzer: " . $e->getMessage();
}

// Benutzer für Bearbeitung laden
$edit_user = null;
if ($action === 'edit' && $edit_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit_user) {
            $error = "Benutzer nicht gefunden.";
            $action = '';
        }
    } catch (PDOException $e) {
        $error = "Fehler beim Laden des Benutzers: " . $e->getMessage();
    }
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    $status = $_POST['status'] ?? 'active';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // Bearbeitungsmodus
    if ($user_id && $action === 'edit') {
        // Validierungen
        if (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
            $error = "Alle Pflichtfelder müssen ausgefüllt sein.";
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Ungültige E-Mail-Adresse.";
        }
        elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = "Benutzername muss zwischen 3 und 50 Zeichen lang sein.";
        }
        elseif (!empty($password) && strlen($password) < 6) {
            $error = "Passwort muss mindestens 6 Zeichen haben.";
        }
        elseif (!empty($password) && $password !== $confirm_password) {
            $error = "Passwörter stimmen nicht überein.";
        } else {
            try {
                // Prüfen ob Benutzername/E-Mail bereits vergeben
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
                $stmt->execute([$username, $user_id]);
                if ($stmt->fetch()) {
                    $error = "Benutzername bereits vergeben.";
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $error = "E-Mail-Adresse bereits vergeben.";
                    } else {
                        // Benutzer aktualisieren
                        if (!empty($password)) {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE admins SET first_name = ?, last_name = ?, username = ?, email = ?, password_hash = ?, role = ?, status = ? WHERE id = ?");
                            $result = $stmt->execute([$first_name, $last_name, $username, $email, $password_hash, $role, $status, $user_id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE admins SET first_name = ?, last_name = ?, username = ?, email = ?, role = ?, status = ? WHERE id = ?");
                            $result = $stmt->execute([$first_name, $last_name, $username, $email, $role, $status, $user_id]);
                        }
                        
                        if ($result) {
                            header("Location: user-configuration.php?success=updated");
                            exit();
                        } else {
                            $error = "Fehler beim Aktualisieren des Benutzers.";
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = "Datenbankfehler: " . $e->getMessage();
            }
        }
    } 
    // Neuen Benutzer erstellen
    else {
        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "Alle Pflichtfelder müssen ausgefüllt sein.";
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Ungültige E-Mail-Adresse.";
        }
        elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = "Benutzername muss zwischen 3 und 50 Zeichen lang sein.";
        }
        elseif (strlen($password) < 6) {
            $error = "Passwort muss mindestens 6 Zeichen haben.";
        }
        elseif ($password !== $confirm_password) {
            $error = "Passwörter stimmen nicht überein.";
        } else {
            try {
                // Prüfen ob Benutzername/E-Mail bereits vergeben
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = "Benutzername bereits vergeben.";
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = "E-Mail-Adresse bereits vergeben.";
                    } else {
                        // Neuen Benutzer erstellen
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmt = $pdo->prepare("INSERT INTO admins (first_name, last_name, username, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if ($stmt->execute([$first_name, $last_name, $username, $email, $password_hash, $role, $status])) {
                            header("Location: user-configuration.php?success=created");
                            exit();
                        } else {
                            $error = "Fehler beim Erstellen des Benutzers.";
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = "Datenbankfehler: " . $e->getMessage();
            }
        }
    }
    
    // Bei POST-Fehlern: Bearbeitungsdaten beibehalten
    if ($error && $user_id) {
        $action = 'edit';
        // Benutzerdaten aus POST für erneute Anzeige speichern
        $edit_user = [
            'id' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'status' => $status
        ];
    } elseif ($error) {
        $action = 'create';
    }
}

// Erfolgsmeldungen
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created': $success = "Neuer Benutzer erfolgreich erstellt!"; break;
        case 'updated': $success = "Benutzer erfolgreich aktualisiert!"; break;
        case 'deleted': $success = "Benutzer erfolgreich gelöscht!"; break;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer Verwaltung</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --system-gray6: #F2F2F7;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, var(--system-gray6) 0%, #FFFFFF 100%);
            color: var(--system-label);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Main Content */
        .main-content {
            width: 100%; 
            max-width: 1400px;
            margin: 0 auto;
            background: var(--gray-50);
            min-height: 100vh;
            padding: 0;
        }

        .top-bar {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
        }

        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.danger { border-left-color: var(--danger); }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-100);
            color: var(--primary);
        }

        .stat-card.warning .stat-icon { background: #fef3c7; color: var(--warning); }
        .stat-card.success .stat-icon { background: #d1fae5; color: var(--success); }
        .stat-card.danger .stat-icon { background: #fee2e2; color: var(--danger); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Main Card */
        .main-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .card-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .users-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .users-table tr:hover {
            background: var(--gray-50);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar-table {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-info-table {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--gray-900);
        }

        .user-username {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-primary {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-superadmin {
            background: #f3e8ff;
            color: #6b21a8;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 50;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
        }

        .modal-close:hover {
            color: var(--gray-700);
        }

        .modal-body {
            padding: 2rem;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-help {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-bar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .card-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.75rem;
                font-size: 0.875rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .main-content {
                width: 100%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
            
            .modal-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Benutzer Verwaltung</h1>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= count($users) ?></div>
                    <div class="stat-label">Gesamt Benutzer</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= count(array_filter($users, fn($u) => $u['status'] === 'active')) ?></div>
                    <div class="stat-label">Aktive Benutzer</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= count(array_filter($users, fn($u) => $u['status'] === 'inactive')) ?></div>
                    <div class="stat-label">Inaktive Benutzer</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-user-lock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= count(array_filter($users, fn($u) => $u['status'] === 'locked')) ?></div>
                    <div class="stat-label">Gesperrte Benutzer</div>
                </div>
            </div>

            <!-- Main Card -->
            <div class="main-card">
                <div class="card-header">
                    <div class="card-title">Benutzerliste</div>
                    <div class="card-actions">
                        <button class="btn btn-primary" onclick="openCreateModal()">
                            <i class="fas fa-user-plus"></i>
                            Neuer Benutzer
                        </button>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Benutzer</th>
                                <th>Kontakt</th>
                                <th>Rolle</th>
                                <th>Status</th>
                                <th>Letzter Login</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar-table">
                                                <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                            </div>
                                            <div class="user-info-table">
                                                <div class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                                <div class="user-username">@<?= htmlspecialchars($user['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge <?= $user['role'] === 'superadmin' ? 'badge-superadmin' : 'badge-primary' ?>">
                                            <?= htmlspecialchars($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= 
                                            $user['status'] === 'active' ? 'badge-success' : 
                                            ($user['status'] === 'inactive' ? 'badge-warning' : 'badge-danger')
                                        ?>">
                                            <?= htmlspecialchars($user['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['last_login']): ?>
                                            <div style="font-weight: 600;"><?= date('d.m.Y', strtotime($user['last_login'])) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);"><?= date('H:i', strtotime($user['last_login'])) ?></div>
                                        <?php else: ?>
                                            <span style="color: var(--gray-400);">Noch nie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn btn-outline btn-sm" onclick="openEditModal(<?= $user['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                                Bearbeiten
                                            </button>
                                            <?php if ($user['id'] != $current_user_id && $is_superadmin): ?>
                                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['first_name'] . ' ' . $user['last_name'])) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                    Löschen
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Create Modal -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Neuen Benutzer erstellen</div>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" id="createForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Vorname *</label>
                            <input type="text" name="first_name" class="form-input" required 
                                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nachname *</label>
                            <input type="text" name="last_name" class="form-input" required 
                                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Benutzername *</label>
                            <input type="text" name="username" class="form-input" required 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            <div class="form-help">3-50 Zeichen</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">E-Mail *</label>
                            <input type="email" name="email" class="form-input" required 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Passwort *</label>
                            <input type="password" name="password" class="form-input" required 
                                   value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">
                            <div class="form-help">Mindestens 6 Zeichen</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Passwort bestätigen *</label>
                            <input type="password" name="confirm_password" class="form-input" required 
                                   value="<?= htmlspecialchars($_POST['confirm_password'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Rolle</label>
                            <select name="role" class="form-select">
                                <option value="support" <?= (($_POST['role'] ?? '') === 'support') ? 'selected' : '' ?>>Support</option>
                                <option value="admin" <?= (($_POST['role'] ?? 'admin') === 'admin') ? 'selected' : '' ?>>Admin</option>
                                <?php if ($is_superadmin): ?>
                                    <option value="superadmin" <?= (($_POST['role'] ?? '') === 'superadmin') ? 'selected' : '' ?>>Superadmin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Aktiv</option>
                                <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inaktiv</option>
                                <option value="locked" <?= (($_POST['status'] ?? '') === 'locked') ? 'selected' : '' ?>>Gesperrt</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">
                            <i class="fas fa-save"></i>
                            Erstellen
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal()">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Benutzer bearbeiten</div>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" id="editForm">
                    <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?? 0 ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Vorname *</label>
                            <input type="text" name="first_name" class="form-input" required 
                                   value="<?= htmlspecialchars($edit_user['first_name'] ?? ($_POST['first_name'] ?? '')) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nachname *</label>
                            <input type="text" name="last_name" class="form-input" required 
                                   value="<?= htmlspecialchars($edit_user['last_name'] ?? ($_POST['last_name'] ?? '')) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Benutzername *</label>
                            <input type="text" name="username" class="form-input" required 
                                   value="<?= htmlspecialchars($edit_user['username'] ?? ($_POST['username'] ?? '')) ?>">
                            <div class="form-help">3-50 Zeichen</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">E-Mail *</label>
                            <input type="email" name="email" class="form-input" required 
                                   value="<?= htmlspecialchars($edit_user['email'] ?? ($_POST['email'] ?? '')) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Passwort (optional)</label>
                            <input type="password" name="password" class="form-input" 
                                   value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">
                            <div class="form-help">Leer lassen um nicht zu ändern</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Passwort bestätigen (optional)</label>
                            <input type="password" name="confirm_password" class="form-input" 
                                   value="<?= htmlspecialchars($_POST['confirm_password'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Rolle</label>
                            <select name="role" class="form-select">
                                <option value="support" <?= (($edit_user['role'] ?? ($_POST['role'] ?? '')) === 'support') ? 'selected' : '' ?>>Support</option>
                                <option value="admin" <?= (($edit_user['role'] ?? ($_POST['role'] ?? 'admin')) === 'admin') ? 'selected' : '' ?>>Admin</option>
                                <?php if ($is_superadmin): ?>
                                    <option value="superadmin" <?= (($edit_user['role'] ?? ($_POST['role'] ?? '')) === 'superadmin') ? 'selected' : '' ?>>Superadmin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= (($edit_user['status'] ?? ($_POST['status'] ?? 'active')) === 'active') ? 'selected' : '' ?>>Aktiv</option>
                                <option value="inactive" <?= (($edit_user['status'] ?? ($_POST['status'] ?? '')) === 'inactive') ? 'selected' : '' ?>>Inaktiv</option>
                                <option value="locked" <?= (($edit_user['status'] ?? ($_POST['status'] ?? '')) === 'locked') ? 'selected' : '' ?>>Gesperrt</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">
                            <i class="fas fa-save"></i>
                            Aktualisieren
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal()">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funktionen für Modals
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }

        function openEditModal(userId) {
            // Seite mit Bearbeitungs-Parameter neu laden
            window.location.href = 'user-configuration.php?action=edit&id=' + userId;
        }

        function closeModal() {
            // Alle Modals schließen
            document.getElementById('createModal').classList.remove('active');
            document.getElementById('editModal').classList.remove('active');
            // Zur Hauptseite ohne Parameter zurückkehren
            window.location.href = 'user-configuration.php';
        }

        function confirmDelete(userId, userName) {
            if (confirm(`Sind Sie sicher, dass Sie den Benutzer "${userName}" löschen möchten?\nDiese Aktion kann nicht rückgängig gemacht werden.`)) {
                window.location.href = `delete_user.php?id=${userId}`;
            }
        }

        // Form Validation
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return true;
            
            const password = form.querySelector('[name="password"]')?.value || '';
            const confirmPassword = form.querySelector('[name="confirm_password"]')?.value || '';
            const isEdit = formId === 'editForm';
            
            // Client-side Validation
            if (!isEdit && password.length < 6) {
                alert('Passwort muss mindestens 6 Zeichen lang sein.');
                return false;
            }
            
            if (isEdit && password && password.length < 6) {
                alert('Passwort muss mindestens 6 Zeichen lang sein.');
                return false;
            }
            
            if (password && password !== confirmPassword) {
                alert('Passwörter stimmen nicht überein.');
                return false;
            }
            
            // Loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Wird gespeichert...';
            submitBtn.disabled = true;
            
            return true;
        }

        // Event Listener für Formulare
        document.getElementById('createForm')?.addEventListener('submit', function(e) {
            if (!validateForm('createForm')) {
                e.preventDefault();
            }
        });

        document.getElementById('editForm')?.addEventListener('submit', function(e) {
            if (!validateForm('editForm')) {
                e.preventDefault();
            }
        });

        // Password confirmation validation
        function setupPasswordValidation(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const passwordInput = form.querySelector('[name="password"]');
            const confirmPasswordInput = form.querySelector('[name="confirm_password"]');
            
            if (!passwordInput || !confirmPasswordInput) return;
            
            function validatePasswords() {
                if (passwordInput.value && confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.style.borderColor = 'var(--danger)';
                    return false;
                } else {
                    confirmPasswordInput.style.borderColor = '';
                    return true;
                }
            }
            
            passwordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
        }

        // Setup validation for both forms
        setupPasswordValidation('createForm');
        setupPasswordValidation('editForm');

        // Close modal on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        });

        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Automatisch Modals öffnen basierend auf PHP-Zustand
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($action === 'create' || ($error && $action === 'create')): ?>
                document.getElementById('createModal').classList.add('active');
            <?php elseif ($action === 'edit' && $edit_user): ?>
                document.getElementById('editModal').classList.add('active');
            <?php endif; ?>
        });
    </script>
</body>
</html>