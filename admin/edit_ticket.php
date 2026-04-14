<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

require 'config.php';

// Ticket laden
$token = $_GET['ticket'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE ticket_number = ?");
$stmt->execute([$token]);
$ticket = $stmt->fetch();

if (!$ticket) {
    die("Ticket nicht gefunden.");
}

$error = null;
$mailSent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Werte aus Formular laden
    $new_status = htmlspecialchars($_POST['status'] ?? '', ENT_QUOTES, 'UTF-8');
    $response = htmlspecialchars($_POST['response'] ?? '', ENT_QUOTES, 'UTF-8');

    // Admin-ID holen
    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) {
        error_log("❌ Admin-ID fehlt in Session!");
        die("Admin-ID konnte nicht ermittelt werden");
    }

    try {
        // Status + updated_by setzen
        $pdo->prepare("
            UPDATE tickets 
            SET status = ?, 
                updated_by = ? 
            WHERE ticket_number = ?
        ")->execute([$new_status, $admin_id, $token]);

        error_log("🔄 Ticket aktualisiert | ID: $token | von Admin: $admin_id");

        // Wenn geschlossen, dann closed_by setzen
        if ($new_status === 'geschlossen') {
            $pdo->prepare("UPDATE tickets SET closed_by = ? WHERE ticket_number = ?")
                ->execute([$admin_id, $token]);

            error_log("✅ Ticket geschlossen | ID: $token | closed_by: $admin_id");
        }

        // Antwort speichern (wenn vorhanden)
        if (!empty($response)) {
            $pdo->prepare("INSERT INTO ticket_updates (ticket_id, update_text, updated_by) VALUES (?, ?, ?)")
                ->execute([$ticket['id'], $response, 'admin']);

            // Mail senden
            require '../mailer/send_mail.php';
            $ticket_link = "https://ticket.domain.tld/view_ticket.php?token=" . urlencode($token);
            $subject_plain = "Ihre Anfrage: {$ticket['subject']} (#{$ticket['ticket_number']})";

            $mail_body = '
            <html>
                <body style="font-family: Arial; font-size: 14px;">
                    <div style="max-width: 600px; margin: auto; background: white; color: #333; border-radius: 8px; padding: 20px; border: 1px solid #ddd;">';

            if ($new_status === 'geschlossen') {
                $mail_body .= "<p>Ihr Ticket wurde bearbeitet und ist nun abgeschlossen.</p>";
            } else {
                $mail_body .= "<p>Ihr Ticket wurde aktualisiert.</p>";
            }

            $mail_body .= '
                        <p><b><i>' . nl2br($response) . '</i></b></p>
                        <p><a href="' . $ticket_link . '">Zum Ticket</a></p>
                        <p>Mit freundlichen Grüßen<br>Ihr Support Team</p>
                    </div>
                </body>
            </html>
            ';

            // ✅ Richtiger Betreff je nach Status
            if ($new_status === 'geschlossen') {
                $mail_subject = "Ticket #{$token} wurde geschlossen";
            } else {
                $mail_subject = "Ticket #{$token} aktualisiert";
            }

            $mail_subject = mb_encode_mimeheader($mail_subject, 'UTF-8', 'Q');

            // Mail senden
            $sent = sendMail($ticket['email'], $mail_subject, $mail_body, true);

            if ($sent) {
                error_log("📧 E-Mail wurde gesendet.");
                $mailSent = true;
            } else {
                error_log("❌ E-Mail konnte NICHT gesendet werden.");
            }
        }

        // Nachricht an Dashboard senden (falls im Modal geöffnet)
        echo '
        <script>
            if (window.parent && window.top !== window.self) {
                window.parent.postMessage("ticketSaved", "*");
            }
                window.location.href = "dashboard.php"
        </script>
        ';

        // Weiterleitung nach Speichern
        header("Location: dashboard.php");

        exit();

    } catch (Exception $e) {
        error_log("🚨 Fehler beim Speichern: " . $e->getMessage());
        $error = "Fehler beim Speichern des Tickets.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket bearbeiten</title>
    <link rel="icon" href="" sizes="32x32" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --system-blue: #007AFF;
            --system-green: #34C759;
            --system-orange: #FF9500;
            --system-red: #FF3B30;
            --system-purple: #AF52DE;
            --system-gray: #8E8E93;
            --system-gray2: #AEAEB2;
            --system-gray6: #F2F2F7;
            --system-background: #FFFFFF;
            --system-label: #1D1D1F;
            --system-separator: #C6C6C8;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --card-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --system-background: #FFFFFF;
                --system-gray6: #F2F2F7;
                --system-label: #1D1D1F;
                --system-separator: #38383A;
                --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                --card-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.2);
            }
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
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-header h1,
        .card-header h2 {
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--system-label);
        }

        /* Layout */
        .ticket-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .ticket-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .ticket-layout {
                gap: 16px;
            }
        }

        /* Form Styles - Enhanced */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 17px;
            font-weight: 600;
            color: var(--system-label);
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--system-separator);
            border-radius: 12px;
            font-size: 17px;
            background: var(--system-background);
            color: var(--system-label);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }

        .form-textarea {
            min-height: 140px;
            resize: vertical;
            line-height: 1.5;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--system-blue);
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
            transform: translateY(-1px);
        }

        /* Buttons - Enhanced */
        .btn-submit {
            background: linear-gradient(135deg, var(--system-blue), #0056CC);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px 24px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 122, 255, 0.35);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Ticket Info - Enhanced */
        .info-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-size: 15px;
            font-weight: 600;
            color: var(--system-gray);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-value {
            font-size: 17px;
            color: var(--system-label);
            font-weight: 500;
        }

        .message-box {
            background: var(--system-gray6);
            border-radius: 12px;
            padding: 18px;
            margin-top: 8px;
            font-size: 15px;
            line-height: 1.6;
            border-left: 4px solid var(--system-blue);
        }

        /* Status Badge - Enhanced */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge i {
            font-size: 12px;
        }

        .status-offen {
            background: linear-gradient(135deg, var(--system-green), #4CD964);
            color: white;
            box-shadow: 0 2px 8px rgba(52, 199, 89, 0.3);
        }

        .status-in-bearbeitung {
            background: linear-gradient(135deg, var(--system-orange), #FF9500);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 149, 0, 0.3);
        }

        .status-geschlossen {
            background: linear-gradient(135deg, var(--system-gray), #8E8E93);
            color: white;
            box-shadow: 0 2px 8px rgba(142, 142, 147, 0.3);
        }

        /* File Attachment - Enhanced */
        .file-attachment {
            margin-top: 24px;
            padding: 20px;
            background: var(--system-gray6);
            border-radius: 12px;
            border: 2px dashed var(--system-separator);
        }

        .file-preview {
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--system-separator);
            margin-top: 12px;
            max-width: 100%;
            transition: all 0.3s ease;
        }

        .file-preview img {
            width: 100%;
            height: auto;
            display: block;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .file-preview img:hover {
            transform: scale(1.05);
        }

        .file-preview iframe {
            width: 100%;
            height: 400px;
            border: none;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--system-blue);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            margin-top: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.3);
        }

        .btn-download:hover {
            background: #0056CC;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 122, 255, 0.4);
        }

        /* Ticket History - Enhanced */
        .ticket-history {
            list-style: none;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .ticket-history::-webkit-scrollbar {
            width: 6px;
        }

        .ticket-history::-webkit-scrollbar-track {
            background: var(--system-gray6);
            border-radius: 3px;
        }

        .ticket-history::-webkit-scrollbar-thumb {
            background: var(--system-gray);
            border-radius: 3px;
        }

        .ticket-update {
            padding: 20px;
            margin-bottom: 16px;
            border-radius: 14px;
            border: 2px solid var(--system-separator);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .ticket-update::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .ticket-update.admin {
            background: linear-gradient(135deg, rgba(0, 122, 255, 0.05), rgba(0, 122, 255, 0.02));
            border-left: 4px solid var(--system-blue);
        }

        .ticket-update.admin::before {
            background: var(--system-blue);
        }

        .ticket-update.user {
            background: linear-gradient(135deg, rgba(52, 199, 89, 0.05), rgba(52, 199, 89, 0.02));
            border-left: 4px solid var(--system-green);
        }

        .ticket-update.user::before {
            background: var(--system-green);
        }

        .ticket-update:hover {
            transform: translateX(4px);
        }

        .update-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 14px;
            color: var(--system-gray);
            flex-wrap: wrap;
            gap: 8px;
        }

        .update-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-admin {
            background: var(--system-blue);
            color: white;
            box-shadow: 0 2px 6px rgba(0, 122, 255, 0.3);
        }

        .badge-user {
            background: var(--system-green);
            color: white;
            box-shadow: 0 2px 6px rgba(52, 199, 89, 0.3);
        }

        .update-text {
            font-size: 15px;
            line-height: 1.6;
            color: var(--system-label);
        }

        /* Alerts - Enhanced */
        .alert {
            padding: 20px;
            border-radius: 14px;
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
            background: linear-gradient(135deg, rgba(255, 59, 48, 0.1), rgba(255, 59, 48, 0.05));
            border-color: var(--system-red);
            color: var(--system-red);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(52, 199, 89, 0.1), rgba(52, 199, 89, 0.05));
            border-color: var(--system-green);
            color: var(--system-green);
        }

        /* Image Modal - Enhanced */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(10px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .image-modal-content {
            max-width: 95%;
            max-height: 95%;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.6);
            transform: scale(0.9);
            animation: zoomIn 0.3s ease 0.1s forwards;
        }

        @keyframes zoomIn {
            to {
                transform: scale(1);
            }
        }

        .image-modal-content img {
            width: 100%;
            height: auto;
            display: block;
        }

        .close-modal {
            position: absolute;
            top: 24px;
            right: 24px;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            color: white;
            font-size: 22px;
            cursor: pointer;
            backdrop-filter: blur(20px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: rotate(90deg);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--system-gray);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state p {
            font-size: 16px;
            font-weight: 500;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .card {
                padding: 20px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .card-header h1,
            .card-header h2 {
                font-size: 20px;
            }

            .ticket-update {
                padding: 16px;
            }

            .update-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .file-preview iframe {
                height: 300px;
            }

            .btn-download {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 8px;
            }

            .card {
                padding: 16px;
                border-radius: 12px;
            }

            .card-header h1,
            .card-header h2 {
                font-size: 18px;
            }

            .status-badge {
                font-size: 12px;
                padding: 6px 12px;
            }

            .form-input,
            .form-select,
            .form-textarea {
                padding: 12px;
                font-size: 16px;
            }

            .btn-submit {
                padding: 14px 20px;
                font-size: 16px;
            }

            .message-box {
                padding: 14px;
            }
        }
    </style>
</head>

<body>
    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <button class="close-modal" onclick="closeImageModal()">×</button>
        <div class="image-modal-content">
            <img id="modalImage" src="" alt="Vergrößerte Ansicht">
        </div>
    </div>

    <div class="container">
        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($mailSent): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Ticket gespeichert und Mail versendet!
            </div>
        <?php endif; ?>

        <div class="ticket-layout">
            <!-- Linke Spalte: Ticket-Daten und Formular -->
            <div>
                <!-- Ticket Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h1>
                            <i class="fas fa-ticket-alt"></i>
                            <?= $t['subject'] ?>
                            <span class="status-badge status-<?= str_replace(' ', '-', $ticket['status']) ?>">
                                <i class="fas fa-circle"></i>
                                <?= htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </h1>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-hashtag"></i>
                                Ticketnummer
                            </span>
                            <span
                                class="info-value">#<?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-user"></i>
                                Kunde
                            </span>
                            <span class="info-value">
                                <?= htmlspecialchars($ticket['name'], ENT_QUOTES, 'UTF-8') ?>
                                <br>
                                <small
                                    style="color: var(--system-gray);"><?= htmlspecialchars($ticket['email'], ENT_QUOTES, 'UTF-8') ?></small>
                            </span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-info-circle"></i>
                                Status
                            </span>
                            <span class="info-value">
                                <span class="status-badge status-<?= str_replace(' ', '-', $ticket['status']) ?>">
                                    <i class="fas fa-circle"></i>
                                    <?= htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fa-solid fa-note-sticky"></i>
                                Betreff:
                            </span>
                            <div class="message-box">
                                <?= nl2br(htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-envelope"></i>
                                Nachricht
                            </span>
                            <div class="message-box">
                                <?= nl2br(htmlspecialchars($ticket['message'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Angehängte Datei -->
                    <?php if (!empty($ticket['file_path'])):
                        $file_path = htmlspecialchars($ticket['file_path']);
                        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                        ?>
                        <div class="file-attachment">
                            <span class="info-label">
                                <i class="fas fa-paperclip"></i>
                                Angehängte Datei
                            </span>
                            <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                <div class="file-preview">
                                    <img src="../<?= $file_path ?>" alt="Anhang"
                                        onclick="openImageModal('../<?= $file_path ?>')">
                                </div>
                                <a href="../<?= $file_path ?>" download class="btn-download">
                                    <i class="fas fa-download"></i> Datei herunterladen
                                </a>
                            <?php elseif ($file_extension === 'pdf'): ?>
                                <div class="file-preview">
                                    <iframe src="../<?= $file_path ?>" frameborder="0"></iframe>
                                </div>
                                <a href="../<?= $file_path ?>" download class="btn-download">
                                    <i class="fas fa-download"></i> PDF herunterladen
                                </a>
                            <?php else: ?>
                                <a href="../<?= $file_path ?>" download class="btn-download">
                                    <i class="fas fa-download"></i> Datei herunterladen
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formular Card -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-edit"></i> Ticket bearbeiten</h2>
                    </div>

                    <form method="post">
                        <div class="form-group">
                            <label class="form-label" for="status">
                                <i class="fas fa-sync-alt"></i>
                                Status ändern
                            </label>
                            <select name="status" id="status" class="form-select">
                                <option value="offen" <?= $ticket['status'] === 'offen' ? 'selected' : '' ?>>Offen</option>
                                <option value="in Bearbeitung" <?= $ticket['status'] === 'in Bearbeitung' ? 'selected' : '' ?>>In Bearbeitung</option>
                                <option value="geschlossen" <?= $ticket['status'] === 'geschlossen' ? 'selected' : '' ?>>
                                    Geschlossen</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="response">
                                <i class="fas fa-reply"></i>
                                Nachricht an Kunden
                            </label>
                            <textarea name="response" class="form-textarea"
                                placeholder="Ihre Antwort an den Kunden..."><?= $response ?? '' ?></textarea>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Speichern & Nachricht senden
                        </button>
                    </form>
                </div>
            </div>

            <!-- Rechte Spalte: Ticketverlauf -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Ticketverlauf</h2>
                </div>

                <ul class="ticket-history">
                    <?php
                    $update_stmt = $pdo->prepare("SELECT * FROM ticket_updates WHERE ticket_id = ? ORDER BY created_at DESC");
                    $update_stmt->execute([$ticket['id']]);
                    $updates = $update_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($updates)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Noch keine Nachrichten vorhanden</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($updates as $update): ?>
                            <li class="ticket-update <?= $update['updated_by'] === 'admin' ? 'admin' : 'user' ?>">
                                <div class="update-meta">
                                    <span><?= date('d.m.Y H:i', strtotime($update['created_at'])) ?></span>
                                    <span class="update-badge badge-<?= $update['updated_by'] ?>">
                                        <?= $update['updated_by'] === 'admin' ? 'Mitarbeiter' : 'Kunde' ?>
                                    </span>
                                </div>
                                <div class="update-text">
                                    <?= nl2br(htmlspecialchars($update['update_text'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Image Modal Functions
        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // ESC key closes modal
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeImageModal();
            }
        });

        // Click outside modal content closes it
        document.getElementById("imageModal").addEventListener("click", function (e) {
            if (e.target.id === 'imageModal') {
                closeImageModal();
            }
        });

        // Auto-focus textarea
        document.addEventListener('DOMContentLoaded', function () {
            const textarea = document.querySelector('textarea[name="response"]');
            if (textarea) {
                textarea.focus();

                // Auto-resize textarea
                textarea.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
        });

        // Add smooth animations to cards on load
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>

</html>