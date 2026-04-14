<?php
session_start();

// Nur erlauben, wenn der Benutzer eingeloggt ist (Admin)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require 'config.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Prüfung auf leere Felder
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Alle Felder müssen ausgefüllt sein.";
    }
    // Prüfung ob Benutzername bereits existiert
    elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = "Benutzername muss zwischen 3 und 50 Zeichen lang sein.";
    }
    // Passwort-Länge prüfen
    elseif (strlen($password) < 6) {
        $error = "Passwort muss mindestens 6 Zeichen haben.";
    }
    // Passwörter vergleichen
    elseif ($password !== $confirm_password) {
        $error = "Passwörter stimmen nicht überein.";
    } else {
        // Prüfen, ob Benutzername schon vergeben ist
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Benutzername bereits vergeben.";
        } else {
            // Passwort hashen und speichern
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
            if ($stmt->execute([$username, $password_hash])) {
                $success = "Neuer Admin erfolgreich registriert!";
            } else {
                $error = "Fehler beim Speichern des Admins.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin registrieren</title>
    <link rel="icon" href="" sizes="32x32" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --system-blue: #007AFF;
            --system-green: #34C759;
            --system-red: #FF3B30;
            --system-gray: #8E8E93;
            --system-gray6: #F2F2F7;
            --system-background: #FFFFFF;
            --system-label: #1D1D1F;
            --system-separator: #C6C6C8;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Main Header - Fixed at top */
        .main-header {
            background: var(--system-background);
            border-bottom: 1px solid var(--system-separator);
            padding: 16px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-logo h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--system-label);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--system-blue), #34C759);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .search-container {
            flex: 1;
            max-width: 400px;
            margin: 0 20px;
        }

        .search-form {
            display: flex;
            gap: 8px;
            width: 100%;
        }

        .search-input {
            flex: 1;
            padding: 10px 16px;
            border: 1px solid var(--system-separator);
            border-radius: 10px;
            font-size: 14px;
            background: var(--system-gray6);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--system-blue);
            background: var(--system-background);
        }

        .search-btn {
            padding: 10px 16px;
            background: var(--system-blue);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .search-btn:hover {
            background: #0056CC;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 15px;
            color: var(--system-gray);
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-actions a {
            color: var(--system-blue);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
        }

        .user-actions a:hover {
            background: rgba(0, 122, 255, 0.1);
            transform: translateY(-1px);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Container */
        .register-container {
            background: var(--system-background);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--system-separator);
            width: 100%;
            max-width: 440px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 20px;
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--system-blue), #34C759);
        }

        .register-container:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        /* Page Header inside container */
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--system-label);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .page-header .subtitle {
            font-size: 17px;
            color: var(--system-gray);
            font-weight: 400;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
            font-weight: 600;
            color: var(--system-label);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--system-separator);
            border-radius: 12px;
            font-size: 17px;
            background: var(--system-background);
            color: var(--system-label);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--system-blue);
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
            transform: translateY(-1px);
        }

        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--system-blue), #0056CC);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            background: linear-gradient(135deg, #0056CC, var(--system-blue));
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 122, 255, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--system-gray6);
            color: var(--system-label);
            border: 1px solid var(--system-separator);
            margin-top: 16px;
        }

        .btn-secondary:hover {
            background: var(--system-separator);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Alerts */
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid transparent;
            animation: slideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: rgba(255, 59, 48, 0.1);
            border-color: var(--system-red);
            color: var(--system-red);
        }

        .alert-success {
            background: rgba(52, 199, 89, 0.1);
            border-color: var(--system-green);
            color: var(--system-green);
        }

        /* Back Link */
        .back-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--system-separator);
        }

        .back-link a {
            color: var(--system-blue);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .back-link a:hover {
            color: #0056CC;
            transform: translateX(-2px);
        }

        /* Password Requirements */
        .password-requirements {
            font-size: 13px;
            color: var(--system-gray);
            margin-top: 4px;
            padding-left: 8px;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 16px;
            }

            .search-container {
                max-width: 100%;
                margin: 0;
                order: 3;
            }

            .user-info {
                flex-direction: column;
                gap: 8px;
            }

            .main-content {
                padding: 20px 16px;
            }

            .register-container {
                padding: 30px 24px;
                margin-top: 0;
            }

            .page-header h1 {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .main-header {
                padding: 12px 16px;
            }

            .register-container {
                padding: 24px 20px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .page-header .subtitle {
                font-size: 16px;
            }

            .form-input {
                padding: 12px 14px;
                font-size: 16px;
            }

            .btn {
                padding: 14px 20px;
                font-size: 16px;
            }
        }

        /* Loading Animation */
        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        .loading {
            animation: pulse 2s infinite;
        }
    </style>
</head>

<body>
    <!-- Main Header - Fixed at top -->
    <header class="main-header">
        <div class="header-content">
            <div class="header-logo">
                <div class="logo-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>
                    <a href="dashboard.php" style="text-decoration: none; color: inherit;"> Admin registrieren</a>
                </h1>
            </div>

            <div class="user-info">
                <span>Angemeldet als:
                    <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></strong></span>
                <div class="user-actions">
                    <a href="closed.php">
                        <i class="fas fa-archive"></i> Geschlossen
                    </a>
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="edit_profile.php"><i class="fas fa-lock"></i> Passwort</a>
                    <a href="register.php"><i class="fas fa-user-plus"></i> Admin</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="register-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-user-plus"></i>
                    Admin registrieren
                </h1>
                <p class="subtitle">Erstellen Sie einen neuen Administrator-Account</p>
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

            <!-- Registration Form -->
            <form method="post" id="registerForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i>
                        Benutzername
                    </label>
                    <input type="text" name="username" class="form-input" placeholder="Benutzername eingeben" required
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <div class="password-requirements">3-50 Zeichen</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i>
                        Passwort
                    </label>
                    <input type="password" name="password" class="form-input" placeholder="Passwort eingeben" required>
                    <div class="password-requirements">Mindestens 6 Zeichen</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i>
                        Passwort bestätigen
                    </label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="Passwort wiederholen"
                        required>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i>
                    Admin registrieren
                </button>
            </form>

            <!-- Back to Dashboard -->
            <div class="back-link">
                <a href="dashboard.php">
                    <i class="fas fa-arrow-left"></i>
                    Zurück zum Dashboard
                </a>
            </div>
        </div>
    </main>

    <script>
        // Form Validation and Enhancements
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('registerForm');
            const submitBtn = form.querySelector('button[type="submit"]');

            // Real-time password validation
            const passwordInput = document.querySelector('input[name="password"]');
            const confirmInput = document.querySelector('input[name="confirm_password"]');

            function validatePasswords() {
                if (passwordInput.value && confirmInput.value) {
                    if (passwordInput.value !== confirmInput.value) {
                        confirmInput.style.borderColor = 'var(--system-red)';
                    } else {
                        confirmInput.style.borderColor = 'var(--system-green)';
                    }
                }
            }

            passwordInput.addEventListener('input', validatePasswords);
            confirmInput.addEventListener('input', validatePasswords);

            // Form submission loading state
            form.addEventListener('submit', function (e) {
                const originalText = submitBtn.innerHTML;

                // Loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Wird registriert...';
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');

                // Re-enable after 3 seconds if still processing
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                }, 3000);
            });

            // Auto-focus first input
            const firstInput = form.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }

            // Add smooth entrance animation
            const container = document.querySelector('.register-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';

            setTimeout(() => {
                container.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>

</html>