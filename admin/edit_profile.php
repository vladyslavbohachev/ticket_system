<?php
require 'includes/header.php';

// Admin-ID prüfen
$admin_id = $_SESSION['admin_id'] ?? $user['id'] ?? null;
if (!$admin_id) {
    die("Admin-ID konnte nicht ermittelt werden.");
}

// Tickets des Admins laden
$myTickets = $pdo->prepare("SELECT * FROM tickets WHERE updated_by = ? OR closed_by = ?");
$myTickets->execute([$admin_id, $admin_id]);
$myTicketsList = $myTickets->fetchAll(PDO::FETCH_ASSOC);

// Ticketzahlen zählen
$inProgressCount = count(array_filter($myTicketsList, fn($t) => $t['status'] === 'in Bearbeitung'));
$closedCount = count(array_filter($myTicketsList, fn($t) => $t['status'] === 'geschlossen'));

$error_email = '';
$success_email = '';
$error_password = '';
$success_password = '';
$error_profile = '';
$success_profile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_profile'])) {
        // E-Mail ändern
        $newEmail = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $currentPassword = $_POST['current_password'] ?? '';

        if (!$newEmail) {
            $error_email = "Bitte geben Sie eine gültige E-Mail-Adresse ein.";
        } elseif (empty($currentPassword)) {
            $error_email = "Aktuelles Passwort erforderlich für E-Mail-Änderung";
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $error_email = "Aktuelles Passwort ist falsch.";
        } else {
            $pdo->prepare("UPDATE admins SET email = ? WHERE id = ?")
               ->execute([$newEmail, $user['id']]);
            $success_email = "E-Mail wurde aktualisiert.";
            // User-Daten aktualisieren
            $user['email'] = $newEmail;
        }

    } elseif (isset($_POST['change_password'])) {
        // Passwort ändern
        $currentPassword = $_POST['current_password_pw'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $error_password = "Aktuelles Passwort ist falsch.";
        } elseif ($newPassword !== $confirmPassword) {
            $error_password = "Passwörter stimmen nicht überein.";
        } elseif (strlen($newPassword) < 6) {
            $error_password = "Neues Passwort muss mindestens 6 Zeichen haben.";
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")
               ->execute([$newHash, $user['id']]);
            $success_password = "Passwort erfolgreich geändert!";
        }
    
    } elseif (isset($_POST['change_profile_image'])) {
        // Profilbild hochladen
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['profile_image']['type'];
            
            if (in_array($fileType, $allowedTypes)) {
                // Upload-Verzeichnis erstellen falls nicht vorhanden
                if (!is_dir('uploads/profiles')) {
                    mkdir('uploads/profiles', 0755, true);
                }
                
                // Dateinamen generieren
                $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user['id'] . '_' . time() . '.' . $extension;
                $uploadPath = 'uploads/profiles/' . $filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                    // Altes Bild löschen falls vorhanden
                    if (!empty($user['profile_image']) && file_exists('uploads/profiles/' . $user['profile_image'])) {
                        unlink('uploads/profiles/' . $user['profile_image']);
                    }
                    
                    // Datenbank aktualisieren
                    $pdo->prepare("UPDATE admins SET profile_image = ? WHERE id = ?")
                       ->execute([$filename, $user['id']]);
                    $success_profile = "Profilbild wurde erfolgreich aktualisiert!";
                    
                    // User-Daten aktualisieren
                    $user['profile_image'] = $filename;
                } else {
                    $error_profile = "Fehler beim Hochladen des Bildes.";
                }
            } else {
                $error_profile = "Nur JPG, PNG, GIF und WebP Bilder sind erlaubt.";
            }
        } else {
            $error_profile = "Bitte wählen Sie ein gültiges Bild aus.";
        }
    }
}
?>

<div class="container">
    <!-- Statistik Card -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-line"></i> Ihre Ticketstatistik</h2>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $inProgressCount ?: 0 ?></div>
                <div class="stat-label">
                    <i class="fas fa-spinner" style="color: var(--system-orange);"></i>
                    In Bearbeitung
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $closedCount ?: 0 ?></div>
                <div class="stat-label">
                    <i class="fas fa-check-circle" style="color: var(--system-green);"></i>
                    Geschlossen
                </div>
            </div>
        </div>
    </div>

    <!-- Profil & Passwort Card -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-user-edit"></i> Profil bearbeiten</h2>
        </div>
        
        <div class="form-sections">
            <!-- Profilbild Upload -->
            <div class="form-group">
                <h3><i class="fas fa-camera"></i> Profilbild</h3>
                
                <?php if (!empty($error_profile)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_profile) ?>
                    </div>
                <?php elseif (!empty($success_profile)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_profile) ?>
                    </div>
                <?php endif; ?>

                <div style="text-align: center; margin-bottom: 20px;">
                    <div class="profile-circle <?= empty($user['profile_image']) ? 'initials' : '' ?>" style="width: 100px; height: 100px; margin: 0 auto 16px; font-size: 24px;">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" alt="Profilbild" onerror="this.style.display='none'; this.parentElement.classList.add('initials');">
                        <?php endif; ?>
                        <?php if (empty($user['profile_image'])): ?>
                            <?= strtoupper(substr($user['username'], 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <p style="color: var(--system-gray); font-size: 14px; margin-bottom: 16px;">
                        Klicken Sie auf "Bild auswählen" um ein neues Profilbild hochzuladen.
                    </p>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="profile_image" accept="image/*" style="margin-bottom: 16px;" required>
                    <button type="submit" name="change_profile_image" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Profilbild hochladen
                    </button>
                </form>
            </div>

            <!-- E-Mail Formular -->
            <div class="form-group">
                <h3><i class="fas fa-envelope"></i> E-Mail ändern</h3>
                
                <?php if (!empty($error_email)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_email) ?>
                    </div>
                <?php elseif (!empty($success_email)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_email) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <label class="form-label">E-Mail</label>
                    <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" required>

                    <label class="form-label">Aktuelles Passwort</label>
                    <input type="password" name="current_password" class="form-input" placeholder="Aktuelles Passwort" required>

                    <button type="submit" name="change_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> E-Mail speichern
                    </button>
                </form>
            </div>

            <!-- Passwort Formular -->
            <div class="form-group">
                <h3><i class="fas fa-key"></i> Passwort ändern</h3>
                
                <?php if (!empty($error_password)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_password) ?>
                    </div>
                <?php elseif (!empty($success_password)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_password) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <label class="form-label">Aktuelles Passwort</label>
                    <input type="password" name="current_password_pw" class="form-input" placeholder="Aktuelles Passwort" required>

                    <label class="form-label">Neues Passwort</label>
                    <input type="password" name="new_password" class="form-input" placeholder="Neues Passwort" required>

                    <label class="form-label">Passwort bestätigen</label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="Passwort wiederholen" required>

                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Passwort ändern
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Zusätzliche Styles für die Hauptseite -->
<style>
    /* Container */
    .container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 24px;
    }

    @media (max-width: 768px) {
        .container {
            padding: 16px;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 12px;
        }
    }

    /* Card Komponente - Enhanced */
    .card {
        background: var(--system-background);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--card-shadow);
        border: 1px solid var(--system-separator);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
    }

    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--system-blue), var(--system-purple));
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .card:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-shadow-hover);
    }

    .card:hover::before {
        transform: scaleX(1);
    }

    .card-header {
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--system-separator);
    }

    .card-header h2 {
        font-size: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--system-label);
    }

    /* Form Sections */
    .form-sections {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    @media (max-width: 768px) {
        .form-sections {
            grid-template-columns: 1fr;
            gap: 20px;
        }
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 0;
    }

    .form-group h3 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 16px;
        color: var(--system-label);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 15px;
        font-weight: 600;
        color: var(--system-label);
    }

    .form-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--system-separator);
        border-radius: 10px;
        font-size: 16px;
        background: var(--system-background);
        color: var(--system-label);
        transition: all 0.2s ease;
        margin-bottom: 16px;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--system-blue);
        box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        width: 100%;
        justify-content: center;
    }

    .btn-primary {
        background: var(--system-blue);
        color: white;
    }

    .btn-primary:hover {
        background: #0056CC;
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: var(--system-gray6);
        color: var(--system-label);
        border: 1px solid var(--system-separator);
    }

    .btn-secondary:hover {
        background: var(--system-separator);
        transform: translateY(-2px);
    }

    /* Statistics */
    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-top: 16px;
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    .stat-card {
        background: var(--system-gray6);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        border: 1px solid var(--system-separator);
        transition: transform 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .stat-number {
        font-size: 24px;
        font-weight: 700;
        color: var(--system-label);
        margin-bottom: 4px;
    }

    .stat-label {
        font-size: 14px;
        color: var(--system-gray);
        font-weight: 500;
    }

    /* Alerts */
    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .alert-success {
        background: rgba(52, 199, 89, 0.1);
        border: 1px solid var(--system-green);
        color: var(--system-green);
    }

    .alert-error {
        background: rgba(255, 59, 48, 0.1);
        border: 1px solid var(--system-red);
        color: var(--system-red);
    }

    /* Back Button */
    .back-container {
        text-align: center;
        margin-top: 24px;
    }
</style>

<script>
    // Add smooth animations to cards on load
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 150);
        });
    });
</script>

<?php include 'includes/footer.php'; ?>