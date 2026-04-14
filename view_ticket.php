<?php
session_start();
require 'admin/config.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    die("Kein Ticket-Token angegeben.");
}

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE ticket_number = ?");
$stmt->execute([$token]);
$ticket = $stmt->fetch();

if (!$ticket) {
    die("Ticket nicht gefunden.");
}

// Kundenantwort verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_message = htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8');

    if (!empty($user_message)) {
        // Antwort speichern
        $pdo->prepare("INSERT INTO ticket_updates (ticket_id, update_text, updated_by) VALUES (?, ?, ?)")
            ->execute([$ticket['id'], $user_message, 'user']);

        // Mail an Admin senden
        require 'mailer/send_mail.php';
        $ticket_link = "https://ticket.domain.tld/admin/edit_ticket.php?ticket=" . urlencode($token);
        $mail_body = '
        <html>
            <body style="font-family: Arial; font-size: 14px;">
                <div style="max-width: 600px; margin: auto; background: white; color: #333; border-radius: 8px; padding: 20px; border: 1px solid #ddd;">
                    <h2>Neue Nachricht zum Ticket #' . $token . '</h2>
                    <p>' . nl2br(htmlspecialchars($user_message, ENT_QUOTES, 'UTF-8')) . '</p>
                    <p><a href="' . $ticket_link . '">Zur Bearbeitung</a></p>
                    <p>Mit freundlichen Grüßen<br>Ihr Support Team</p>
                </div>
            </body>
        </html>
        ';

        // ✅ Betreff je nach Status anpassen
        if ($ticket['status'] === 'geschlossen') {
            $mail_subject = "Ticket #{$token} wurde geschlossen";
        } else {
            $mail_subject = "Neue Antwort auf Ticket #{$token}";
        }

        $mail_subject = mb_encode_mimeheader($mail_subject, 'UTF-8', 'Q');

        // Admin-ID holen
        $admin_id = $ticket['updated_by'] ?? $ticket['closed_by'] ?? null;

        if ($admin_id) {
            $stmt = $pdo->prepare("SELECT email, username FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();

            if ($admin && filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
                sendMail($admin['email'], $mail_subject, $mail_body, true);
                error_log("📧 Benachrichtigung an Admin gesendet: " . $admin['email']);
            }
        }

        $_SESSION['message_sent'] = true;
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= htmlspecialchars($ticket['ticket_number']) ?> - </title>
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
            max-width: 1200px;
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

        /* Header - Enhanced */
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--system-separator);
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--system-label);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .header-meta {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 16px;
            color: var(--system-gray);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .status-offen {
            background: linear-gradient(135deg, var(--system-green), #4CD964);
            color: white;
        }

        .status-in-bearbeitung {
            background: linear-gradient(135deg, var(--system-orange), #FF9500);
            color: white;
        }

        .status-geschlossen {
            background: linear-gradient(135deg, var(--system-gray), #8E8E93);
            color: white;
        }

        /* Main Layout */
        .ticket-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .ticket-layout {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        @media (max-width: 768px) {
            .ticket-layout {
                gap: 20px;
            }
        }

        /* Card Komponente - Enhanced */
        .card {
            background: var(--system-background);
            border-radius: 20px;
            padding: 28px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--system-separator);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--system-blue), var(--system-purple));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: var(--card-shadow-hover);
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .card-header {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--system-separator);
        }

        .card-header h2 {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--system-label);
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
            gap: 8px;
        }

        .info-label {
            font-size: 16px;
            font-weight: 700;
            color: var(--system-gray);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            font-size: 18px;
            color: var(--system-label);
            font-weight: 600;
        }

        .message-box {
            background: linear-gradient(135deg, var(--system-gray6), #FFFFFF);
            border-radius: 16px;
            padding: 20px;
            margin-top: 8px;
            font-size: 16px;
            line-height: 1.7;
            border-left: 5px solid var(--system-blue);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        /* File Attachment - Enhanced */
        .file-attachment {
            margin-top: 24px;
            padding: 20px;
            background: linear-gradient(135deg, var(--system-gray6), #FFFFFF);
            border-radius: 16px;
            border: 2px dashed var(--system-separator);
            transition: all 0.3s ease;
        }

        .file-attachment:hover {
            border-color: var(--system-blue);
            background: linear-gradient(135deg, rgba(0, 122, 255, 0.05), #FFFFFF);
        }

        .file-preview {
            border-radius: 14px;
            overflow: hidden;
            border: 2px solid var(--system-separator);
            margin-top: 12px;
            max-width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .file-preview img {
            width: 100%;
            height: auto;
            display: block;
            cursor: pointer;
            transition: transform 0.4s ease;
        }

        .file-preview img:hover {
            transform: scale(1.08);
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--system-blue), #0056CC);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            margin-top: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.3);
        }

        .btn-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 122, 255, 0.4);
        }

        /* Form Styles - Enhanced */
        .form-group {
            margin-bottom: 28px;
        }

        .form-label {
            display: block;
            margin-bottom: 12px;
            font-size: 18px;
            font-weight: 700;
            color: var(--system-label);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-textarea {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid var(--system-separator);
            border-radius: 16px;
            font-size: 17px;
            background: var(--system-background);
            color: var(--system-label);
            font-family: inherit;
            resize: vertical;
            min-height: 160px;
            line-height: 1.6;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--system-blue);
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.15);
            transform: translateY(-2px);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--system-green), #4CD964);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 18px 32px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(52, 199, 89, 0.3);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(52, 199, 89, 0.4);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        .btn-submit:disabled {
            background: var(--system-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Ticket History - Enhanced */
        .ticket-history {
            list-style: none;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 12px;
        }

        .ticket-history::-webkit-scrollbar {
            width: 8px;
        }

        .ticket-history::-webkit-scrollbar-track {
            background: var(--system-gray6);
            border-radius: 4px;
        }

        .ticket-history::-webkit-scrollbar-thumb {
            background: var(--system-gray);
            border-radius: 4px;
        }

        .ticket-history::-webkit-scrollbar-thumb:hover {
            background: var(--system-gray2);
        }

        .ticket-update {
            padding: 24px;
            margin-bottom: 20px;
            border-radius: 18px;
            border: 2px solid var(--system-separator);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .ticket-update::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
        }

        .ticket-update.admin {
            background: linear-gradient(135deg, rgba(0, 122, 255, 0.08), rgba(0, 122, 255, 0.03));
            border-left: 6px solid var(--system-blue);
        }

        .ticket-update.admin::before {
            background: var(--system-blue);
        }

        .ticket-update.user {
            background: linear-gradient(135deg, rgba(52, 199, 89, 0.08), rgba(52, 199, 89, 0.03));
            border-left: 6px solid var(--system-green);
        }

        .ticket-update.user::before {
            background: var(--system-green);
        }

        .ticket-update:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .update-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            font-size: 15px;
            color: var(--system-gray);
            flex-wrap: wrap;
            gap: 12px;
        }

        .update-badge {
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .badge-admin {
            background: var(--system-blue);
            color: white;
        }

        .badge-user {
            background: var(--system-green);
            color: white;
        }

        .update-text {
            font-size: 16px;
            line-height: 1.7;
            color: var(--system-label);
            font-weight: 500;
        }

        /* Closed Ticket Message - Enhanced */
        .closed-message {
            background: linear-gradient(135deg, var(--system-gray6), #FFFFFF);
            border: 2px solid var(--system-separator);
            border-radius: 18px;
            padding: 32px 24px;
            text-align: center;
            margin-top: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .closed-message i {
            font-size: 48px;
            color: var(--system-gray);
            margin-bottom: 20px;
            display: block;
            opacity: 0.7;
        }

        .closed-message h3 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--system-label);
        }

        .closed-message p {
            font-size: 16px;
            color: var(--system-gray);
            line-height: 1.6;
        }

        /* Modal - Enhanced */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(12px);
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: var(--system-background);
            border-radius: 24px;
            padding: 32px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            animation: modalIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.1s forwards;
        }

        @keyframes modalIn {
            to {
                transform: scale(1);
            }
        }

        .success-icon {
            font-size: 64px;
            color: var(--system-green);
            margin-bottom: 20px;
            display: block;
        }

        /* Image Preview Modal - Enhanced */
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
            z-index: 1001;
            backdrop-filter: blur(15px);
            animation: fadeIn 0.4s ease;
        }

        .image-modal-content {
            max-width: 95%;
            max-height: 95%;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);
            transform: scale(0.9);
            animation: zoomIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.1s forwards;
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
            top: 28px;
            right: 28px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 52px;
            height: 52px;
            color: white;
            font-size: 24px;
            cursor: pointer;
            backdrop-filter: blur(20px);
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg) scale(1.1);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--system-gray);
        }

        .empty-state i {
            font-size: 72px;
            margin-bottom: 24px;
            opacity: 0.3;
        }

        .empty-state p {
            font-size: 18px;
            font-weight: 500;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 28px;
                flex-direction: column;
                gap: 12px;
            }

            .header-meta {
                flex-direction: column;
                gap: 12px;
            }

            .card {
                padding: 24px 20px;
                border-radius: 16px;
            }

            .card-header h2 {
                font-size: 22px;
            }

            .ticket-update {
                padding: 20px;
            }

            .update-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .form-textarea {
                padding: 16px;
                min-height: 140px;
            }

            .btn-submit {
                padding: 16px 24px;
                font-size: 17px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 8px;
            }

            .header h1 {
                font-size: 24px;
            }

            .card {
                padding: 20px 16px;
                border-radius: 14px;
            }

            .card-header h2 {
                font-size: 20px;
            }

            .status-badge {
                font-size: 13px;
                padding: 8px 16px;
            }

            .info-value {
                font-size: 16px;
            }

            .message-box {
                padding: 16px;
            }

            .modal-content {
                padding: 24px;
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
    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="success-icon">✅</div>
            <h2 style="margin-bottom: 16px; font-size: 24px; font-weight: 700;">Antwort gesendet</h2>
            <p style="color: var(--system-gray); font-size: 17px; margin-bottom: 24px;">Ihre Nachricht wurde erfolgreich
                übermittelt.</p>
            <button onclick="closeModal()" class="btn-submit"
                style="background: var(--system-blue); max-width: 200px; margin: 0 auto;">
                OK
            </button>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div id="imageModal" class="image-modal">
        <button class="close-modal" onclick="closeImageModal()">×</button>
        <div class="image-modal-content">
            <img id="modalImage" src="" alt="Vergrößerte Ansicht">
        </div>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-ticket-alt"></i>
                Ticket #<?= htmlspecialchars($ticket['ticket_number']) ?>
                <span class="status-badge status-<?= str_replace(' ', '-', $ticket['status']) ?>">
                    <i class="fas fa-circle"></i>
                    <?= htmlspecialchars($ticket['status']) ?>
                </span>
            </h1>
            <div class="header-meta">
                <span>
                    <i class="fas fa-calendar"></i>
                    Erstellt am <?= date('d.m.Y \u\m H:i', strtotime($ticket['created_at'])) ?>
                </span>
                <span>
                    <i class="fas fa-user"></i>
                    Von: <?= htmlspecialchars($ticket['name']) ?>
                </span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="ticket-layout">
            <!-- Linke Spalte: Ticket-Informationen -->
            <div>
                <!-- Ticket Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> Ticket-Details</h2>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-tag"></i>
                                Betreff
                            </span>
                            <span class="info-value"><?= $ticket['subject'] ?></span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-user"></i>
                                Name
                            </span>
                            <span class="info-value"><?= htmlspecialchars($ticket['name']) ?></span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-envelope"></i>
                                E-Mail
                            </span>
                            <span class="info-value"><?= htmlspecialchars($ticket['email']) ?></span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-chart-line"></i>
                                Status
                            </span>
                            <span class="info-value">
                                <span class="status-badge status-<?= str_replace(' ', '-', $ticket['status']) ?>">
                                    <i class="fas fa-circle"></i>
                                    <?= htmlspecialchars($ticket['status']) ?>
                                </span>
                            </span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-comment"></i>
                                Beschreibung
                            </span>
                            <div class="message-box">
                                <?= nl2br(htmlspecialchars($ticket['message'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </div>
                    </div>

                    
                </div>

                <!-- Antwort-Formular -->
                <?php if ($ticket['status'] !== 'geschlossen'): ?>
                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <h2><i class="fas fa-reply"></i> Antwort senden</h2>
                        </div>

                        <form method="post">
                            <div class="form-group">
                                <label class="form-label">Ihre Nachricht</label>
                                <textarea name="message" class="form-textarea" placeholder="Schreiben Sie Ihre Antwort..."
                                    required></textarea>
                            </div>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane"></i> Antwort absenden
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="closed-message">
                        <i class="fas fa-lock"></i>
                        <h3>Ticket geschlossen</h3>
                        <p>Dieses Ticket ist abgeschlossen und kann nicht mehr bearbeitet werden.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Rechte Spalte: Ticketverlauf -->
            <div class="card">
                <!-- Hochgeladene Datei anzeigen -->
                    <?php if (!empty($ticket['file_path'])): ?>
                        <div class="file-attachment">
                            <span class="info-label">
                                <i class="fas fa-paperclip"></i>
                                Anhang
                            </span>
                            <?php
                            $file_extension = strtolower(pathinfo($ticket['file_path'], PATHINFO_EXTENSION));
                            $file_name = basename($ticket['file_path']);

                            // Icon basierend auf Dateityp auswählen
                            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                $file_icon = 'fa-image';
                                $is_image = true;
                            } elseif ($file_extension === 'pdf') {
                                $file_icon = 'fa-file-pdf';
                                $is_image = false;
                            } else {
                                $file_icon = 'fa-file';
                                $is_image = false;
                            }
                            ?>

                            <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                                <i class="fas <?= $file_icon ?>" style="font-size: 24px; color: var(--system-gray);"></i>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 16px; color: var(--system-label);">
                                        <?= htmlspecialchars($file_name) ?></div>
                                    <?php if (file_exists($ticket['file_path'])): ?>
                                        <div style="font-size: 14px; color: var(--system-gray);">
                                            <?= formatFileSize(filesize($ticket['file_path'])) ?></div>
                                    <?php endif; ?>
                                </div>
                                <a href="<?= htmlspecialchars($ticket['file_path']) ?>" download class="btn-download">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>

                            <!-- Bild-Vorschau für Bilder -->
                            <?php if ($is_image && file_exists($ticket['file_path'])): ?>
                                <div class="file-preview">
                                    <img src="<?= htmlspecialchars($ticket['file_path']) ?>" alt="Anhang"
                                        onclick="openImageModal('<?= htmlspecialchars($ticket['file_path']) ?>')">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?= date('d.m.Y H:i', strtotime($update['created_at'])) ?>
                                    </span>
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
        // Modal Funktionen
        function closeModal() {
            const modal = document.getElementById('successModal');
            modal.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                modal.style.display = 'none';
                modal.style.animation = '';
            }, 300);
        }

        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                modal.style.display = 'none';
                modal.style.animation = '';
                document.body.style.overflow = '';
            }, 300);
        }

        // Erfolgsmodal anzeigen wenn Nachricht gesendet wurde
        <?php if (isset($_SESSION['message_sent']) && $_SESSION['message_sent']): ?>
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('successModal').style.display = 'flex';
                document.querySelector("textarea[name='message']").value = "";
            });
            <?php unset($_SESSION['message_sent']); ?>
        <?php endif; ?>

        // Modal schließen bei Klick außerhalb oder ESC
        document.addEventListener('DOMContentLoaded', function () {
            // ESC-Taste
            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape") {
                    closeModal();
                    closeImageModal();
                }
            });

            // Klick außerhalb
            document.addEventListener("click", function (e) {
                if (e.target.id === 'successModal') closeModal();
                if (e.target.id === 'imageModal') closeImageModal();
            });

            // Auto-resize textarea
            const textarea = document.querySelector('textarea[name="message"]');
            if (textarea) {
                textarea.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });

                // Initial focus
                textarea.focus();
            }
        });

        // Add smooth animations to cards on load
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });

        // Add fadeOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>

<?php
// Hilfsfunktion zur Formatierung der Dateigröße
function formatFileSize($bytes)
{
    if ($bytes == 0)
        return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format(($bytes / pow($k, $i)), 2) . ' ' . $sizes[$i];
}
?>