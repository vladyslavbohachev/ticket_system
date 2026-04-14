<?php
session_start();
require 'admin/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eingaben säubern und validieren
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $message = htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8');

    if (!$name || !$email || !$subject || empty($message)) {
        die("Bitte füllen Sie alle Felder korrekt aus.");
    }

    // Ticketnummer generieren
    $ticket_number = uniqid('TKT-');

    // Dateiupload prüfen
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $file_type = mime_content_type($_FILES['file']['tmp_name']);

        if (!in_array($file_type, $allowed_types)) {
            die("Ungültiger Dateityp. Nur JPG, PNG oder PDF erlaubt.");
        }

        $upload_dir = 'upload/';
        $safe_filename = uniqid() . '-' . preg_replace("/[^A-Za-z0-9._\-]/", "", $_FILES['file']['name']);
        $file_path = $upload_dir . $safe_filename;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
            die("Fehler beim Hochladen der Datei.");
        }
    }

    try {
        // Ticket speichern
        $stmt = $pdo->prepare("
            INSERT INTO tickets (
                ticket_number, subject, name, email, message, file_path
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticket_number, $subject, $name, $email, $message, $file_path]);

        $ticket_id = $pdo->lastInsertId();

        // Ticketupdate speichern
        $pdo->prepare("INSERT INTO ticket_updates (ticket_id, update_text, updated_by) VALUES (?, ?, ?)")
            ->execute([$ticket_id, 'Ticket wurde erstellt.', 'user']);


        // ✅ Admins laden
        $admins = $pdo->query("SELECT * FROM admins WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_ASSOC);

        // Mail senden
        require 'mailer/send_mail.php';
        $ticket_link = "https://ticket.domain.tld/view_ticket.php?token=" . urlencode($ticket_number);

        $mail_body = '
        <html>
            <body style="font-family: Arial; font-size: 14px;">
                <div style="max-width: 600px; margin: auto; background: white; color: #333; border-radius: 8px; padding: 20px; border: 1px solid #ddd;">
                    <h2>Ihr Ticket wurde eingereicht</h2>
                    <p>Vielen Dank für Ihre Anfrage.</p>
                    <p>Ihr Ticket wurde erfasst und wird bearbeitet.</p>
                    <p><strong>Ticketnummer:</strong> ' . $ticket_number . '</p>
                    <p><strong>Link zur Verfolgung:</strong> <a href="' . $ticket_link . '">' . $ticket_link . '</a></p>
                    <p style="margin-top: 30px;"><em>Mit freundlichen Grüßen<br>Ihr Support Team</em></p>
                </div>
            </body>
        </html>
        ';

        // Betreff ohne Base64-Verschlüsselung
        $mail_subject_user = html_entity_decode("Ihre Anfrage: {$subject} (#{$ticket_number})", ENT_QUOTES | ENT_HTML5, 'UTF-8');


        $sent = sendMail($email, $mail_subject_user, $mail_body, true); // true = HTML-Mail

        $ticket_link_admin = "https://ticket.domain.tld/dashboard.php";

        foreach ($admins as $admin) {
            $mail_body_admin = '
            <html>
                <body style="font-family: Arial; font-size: 14px;">
                    <div style="max-width: 600px; margin: auto; background: white; color: #333; border-radius: 8px; padding: 20px; border: 1px solid #ddd;">
                        <h2>Neues Support-Ticket #' . $ticket_number . '</h2>
                        <p><strong>Kunde:</strong> ' . htmlspecialchars($name) . ' (' . htmlspecialchars($email) . ')</p>
                        <p><strong>Betreff:</strong> ' . html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>
                        <p><strong>Nachricht:</strong><br><br>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>
                        <p><a href="' . $ticket_link_admin . '">Zur Bearbeitung</a></p>
                        <p>Mit freundlichen Grüßen<br>Ihr Support Team</p>
                    </div>
                </body>
            </html>
            ';

            $mail_subject_admin = "🔔 Neues Ticket #" . $ticket_number;


            $sent = sendMail($admin['email'], $mail_subject_admin, $mail_body_admin, true);

        }

        if ($sent) {
            error_log("📧 Ticket-Mail erfolgreich gesendet an $email");
        } else {
            error_log("❌ Ticket-Mail konnte NICHT gesendet werden an $email");
        }

        // Weiterleiten nach Speichern
        $_SESSION['success_ticket'] = $ticket_number;
        header("Location: " . $_SERVER['PHP_SELF'] . "?created=1");
        exit();

    } catch (Exception $e) {
        error_log("🚨 Fehler beim Speichern des Tickets: " . $e->getMessage());
        die("Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.");
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket</title>
    <link rel="icon" href="" sizes="32x32" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --system-blue: #007AFF;
            --system-green: #34C759;
            --system-orange: #FF9500;
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
            position: relative;
            text-align: center;
            margin-bottom: 50px;
            padding: 20px 30px;
            background: linear-gradient(135deg, var(--system-background), var(--system-gray6));
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--system-separator);
        }

        .header>div {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .header h1 {
            font-size: 42px;
            font-weight: 800;
            color: var(--system-label);
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--system-blue), var(--system-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header .subtitle {
            font-size: 20px;
            color: var(--system-gray);
            font-weight: 500;
            max-width: 600px;
            line-height: 1.5;
            margin: 0;
        }

        /* Responsive Anpassungen für Mobile */
        @media (max-width: 768px) {
            .header>div {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .header {
                padding: 20px;
            }

            .header .subtitle {
                max-width: none;
            }
        }

        /* Main Content Layout - Enhanced */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .main-content {
                gap: 30px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        /* Card Komponente - Enhanced */
        .card {
            background: var(--system-background);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--system-separator);
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
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .card-header {
            margin-bottom: 15px;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }


        /* Form Styles - Enhanced */
        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 500;
            color: var(--system-label);
        }

        .form-input,
        .form-textarea {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid var(--system-separator);
            border-radius: 16px;
            font-size: 17px;
            background: var(--system-background);
            color: var(--system-label);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }

        .form-textarea {
            min-height: 80px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--system-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }


        /* File Upload Styling - Enhanced */
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-upload-button {
            background: linear-gradient(135deg, var(--system-gray6), #FFFFFF);
            border: 2px dashed var(--system-separator);
            border-radius: 16px;
            padding: 12px 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .file-upload-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 122, 255, 0.05), transparent);
            transition: left 0.6s;
        }

        .file-upload-button:hover::before {
            left: 100%;
        }

        .file-upload-button:hover {
            border-color: var(--system-blue);
            background: linear-gradient(135deg, rgba(0, 122, 255, 0.05), #FFFFFF);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 122, 255, 0.1);
        }

        .file-upload-button i {
            font-size: 48px;
            color: var(--system-gray);
            margin-bottom: 16px;
            display: block;
            transition: all 0.3s ease;
        }

        .file-upload-button:hover i {
            color: var(--system-blue);
            transform: scale(1.1);
        }

        .file-upload-button span {
            font-size: 18px;
            font-weight: 600;
            color: var(--system-label);
            display: block;
            margin-bottom: 8px;
        }

        .form-file {
            position: absolute;
            left: -9999px;
        }

        /* Button - Enhanced */
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--system-blue), #0056CC);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 20px 32px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #0056CC, var(--system-blue));
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 122, 255, 0.4);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        /* Instructions List - Enhanced */
        .instructions-list {
            list-style: none;
            font-size: 13px;
        }

        .instructions-list li {
            margin-bottom: 4px;
            padding: 16px;
            background: linear-gradient(135deg, var(--system-gray6), #FFFFFF);
            border-radius: 16px;
            border-left: 4px solid var(--system-blue);
            transition: all 0.3s ease;
        }

        .instructions-list li:hover {
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .instructions-list li strong {
            font-size: 18px;
            color: var(--system-label);
            display: block;
            margin-bottom: 8px;
        }

        .instructions-sublist {
            list-style: none;
            margin-top: 12px;
            margin-left: 8px;
        }

        .instructions-sublist li {
            margin-bottom: 6px;
            font-size: 15px;
            color: var(--system-gray);
            padding: 0;
            background: none;
            border-left: none;
            position: relative;
            padding-left: 20px;
        }

        .instructions-sublist li::before {
            content: '•';
            color: var(--system-blue);
            font-size: 20px;
            position: absolute;
            left: 0;
            top: 0;
        }

        .info-box {
            margin-top: 24px;
            padding: 24px;
            background: linear-gradient(135deg, rgba(52, 199, 89, 0.1), rgba(52, 199, 89, 0.05));
            border-radius: 16px;
            border: 2px solid var(--system-green);
        }

        .info-box strong {
            color: var(--system-green);
            font-size: 16px;
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

        .modal-buttons {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }

        .btn {
            flex: 1;
            padding: 16px 24px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--system-blue), #0056CC);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 122, 255, 0.4);
        }

        .btn-secondary {
            background: var(--system-gray6);
            color: var(--system-label);
            border: 2px solid var(--system-separator);
        }

        .btn-secondary:hover {
            background: var(--system-separator);
            transform: translateY(-2px);
        }

        /* Cookie Banner - Enhanced */
        .cookie-banner {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--system-background);
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            text-align: center;
            z-index: 1001;
            border: 2px solid var(--system-separator);
            animation: slideUp 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                transform: translateX(-50%) translateY(100%);
                opacity: 0;
            }

            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }

        .cookie-banner button {
            background: linear-gradient(135deg, var(--system-blue), #0056CC);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            margin-top: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.3);
        }

        .cookie-banner button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.4);
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .header {
                padding: 32px 20px;
                margin-bottom: 32px;
            }

            .header h1 {
                font-size: 32px;
            }

            .header .subtitle {
                font-size: 18px;
            }

            .card {
                padding: 24px;
                border-radius: 20px;
            }

            .card-header h2 {
                font-size: 24px;
            }

            .form-input,
            .form-textarea {
                padding: 14px 16px;
            }

            .file-upload-button {
                padding: 24px 20px;
            }

            .btn-submit {
                padding: 18px 24px;
                font-size: 17px;
            }

            .modal-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 8px;
            }

            .header {
                padding: 24px 16px;
                border-radius: 20px;
            }

            .header h1 {
                font-size: 28px;
            }

            .header .subtitle {
                font-size: 16px;
            }

            .card {
                padding: 20px 16px;
                border-radius: 16px;
            }

            .card-header h2 {
                font-size: 22px;
            }

            .instructions-list li {
                padding: 16px;
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

        .modal[style*="display: block"],
        .modal[style*="display: flex"] {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <!-- Erfolgs-Meldung nach Ticket-Erstellung -->
    <?php
    // Erfolgsmeldung anzeigen
    if (isset($_GET['created'])):
        $ticket_number = $_SESSION['success_ticket'] ?? '';
        ?>
        <?php if (!empty($ticket_number)): ?>
            <div class="modal" style="display: block;">
                <div class="modal-content">
                    <div class="success-icon">✅</div>
                    <h2 style="margin-bottom: 16px; font-size: 24px; font-weight: 700;">Ticket erstellt</h2>
                    <p style="margin-bottom: 16px; color: var(--system-gray); font-size: 17px;">Ihr Ticket wurde erfolgreich
                        erstellt!</p>
                    <p style="margin-bottom: 24px; font-size: 18px; font-weight: 600;"><strong>Ticketnummer:</strong>
                        <?= htmlspecialchars($ticket_number) ?></p>

                    <div class="modal-buttons">
                        <a href="<?= "view_ticket.php?token=" . urlencode($ticket_number) ?>" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i> Zum Ticket
                        </a>
                        <button onclick="closeModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Schließen
                        </button>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success_ticket']); ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <div>
                    <h1>📧 Support Ticket</h1>
                    <p class="subtitle">Beschreiben Sie Ihr Anliegen – wir helfen Ihnen gerne weiter</p>
                </div>
                <a href="admin/" style="text-decoration:none; padding: 10px 20px; border: 1px solid #007AFF; color: white; 
            background: linear-gradient(135deg, var(--system-blue), #0056CC); border-radius:16px; cursor: pointer; 
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.3); transition: all 0.3s ease; font-weight: 600; 
            white-space: nowrap; margin-left: 20px;">
                    <i class="fas fa-user-shield"></i> Adminanmeldung
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Formular Card -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-plus-circle"></i> Neues Ticket</h2>

                </div>

                <form method="post" enctype="multipart/form-data" id="ticketForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i>
                            Name
                        </label>

                        <input type="text" name="name" class="form-input" placeholder="Ihr Name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i>
                            E-Mail
                        </label>
                        <input type="email" name="email" class="form-input" placeholder="ihre.email@beispiel.de"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i>
                            Betreff
                        </label>
                        <input type="text" name="subject" class="form-input"
                            placeholder="Kurze Beschreibung des Problems" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment"></i>
                            Beschreibung
                        </label>
                        <textarea name="message" class="form-textarea"
                            placeholder="Beschreiben Sie Ihr Problem detailliert..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-paperclip"></i>
                            Dateianhang (optional)
                        </label>
                        <div class="file-upload-wrapper">
                            <div class="file-upload-button" id="fileUploadButton">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Datei auswählen oder hierher ziehen</span>
                                <br>
                                <small style="color: var(--system-gray);">JPG, PNG oder PDF, max. 5MB</small>
                            </div>
                            <input type="file" name="file" class="form-file" accept=".jpg,.jpeg,.png,.pdf"
                                id="fileInput">
                        </div>
                    </div>

                    <button type="submit" name="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Ticket absenden
                    </button>
                </form>
            </div>

            <!-- Anleitung Card -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> So erstellen Sie ein gutes Ticket </h2>
                </div>

                <ul class="instructions-list">
                    <li>
                        <strong>Betreff kurz und präzise</strong><br>
                        Verwenden Sie eine aussagekräftige Betreffzeile, z.B. "Login-Fehler bei Zugriff auf
                        Kundenportal"
                    </li>

                    <li>
                        <strong>Problem genau beschreiben</strong>
                        <ul class="instructions-sublist">
                            <li>Was ist passiert?</li>
                            <li>Seit wann besteht das Problem?</li>
                            <li>Welche Schritte führen zum Fehler?</li>
                            <li>Was wurde bereits versucht?</li>
                        </ul>
                    </li>

                    <li>
                        <strong>Optional: Screenshot oder PDF anhängen</strong><br>
                        Hilfreich zur Fehleranalyse
                    </li>

                    <li>
                        <strong>Kontaktdaten korrekt angeben</strong><br>
                        E-Mail-Adresse prüfen, damit wir Sie erreichen können
                    </li>
                </ul>

                <div class="info-box">
                    <strong>💡 Hinweis:</strong> Nach dem Absenden erhalten Sie eine Ticketnummer und einen Link per
                    E-Mail.
                    Bitte antworten Sie nicht per E-Mail, sondern nutzen Sie den Link für weitere Updates.
                </div>



            </div>
        </div>

        <!-- Cookie Banner -->
        <div id="cookieBanner" class="cookie-banner" style="display: none;">
            <p style="margin-bottom: 12px; font-size: 15px; line-height: 1.5;">
                Diese Website nutzt Cookies für die beste Erfahrung.
                <a href="" target="_blank"
                    style="color: var(--system-blue); font-weight: 600;">Mehr erfahren</a>
            </p>
            <button onclick="acceptCookies()">
                <i class="fas fa-check"></i> Akzeptieren
            </button>
        </div>
    </div>

    <script>
        // Modal Funktionen
        function closeModal() {
            const modal = document.querySelector('.modal');
            if (modal) {
                modal.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    modal.style.display = 'none';
                    modal.style.animation = '';
                }, 300);
            }
        }

        // Cookie Funktionen
        function acceptCookies() {
            document.cookie = "cookies_accepted=true; path=/; max-age=" + (3600 * 24 * 365);
            const banner = document.getElementById("cookieBanner");
            banner.style.animation = 'slideDown 0.5s ease';
            setTimeout(() => {
                banner.style.display = "none";
            }, 500);
        }

        function checkCookiesAccepted() {
            return document.cookie.split(';').some(cookie => cookie.trim().startsWith('cookies_accepted='));
        }

        // File Upload UI Verbesserung
        document.addEventListener('DOMContentLoaded', function () {
            // Cookie Banner anzeigen wenn nötig
            if (!checkCookiesAccepted()) {
                setTimeout(() => {
                    document.getElementById("cookieBanner").style.display = "block";
                }, 1000);
            }

            // File Upload Elements
            const fileInput = document.getElementById('fileInput');
            const uploadButton = document.getElementById('fileUploadButton');
            const form = document.getElementById('ticketForm');

            if (fileInput && uploadButton) {
                // Klick auf Button öffnet File Dialog
                uploadButton.addEventListener('click', () => fileInput.click());

                // File Input Change Event
                fileInput.addEventListener('change', function (e) {
                    if (this.files.length > 0) {
                        const file = this.files[0];
                        const fileSize = (file.size / (1024 * 1024)).toFixed(2); // MB

                        uploadButton.innerHTML = `
                            <i class="fas fa-check-circle" style="color: var(--system-green);"></i>
                            <span>${file.name}</span>
                            <br>
                            <small style="color: var(--system-gray);">${fileSize} MB - Klicken zum Ändern</small>
                        `;
                        uploadButton.style.borderColor = 'var(--system-green)';
                        uploadButton.style.background = 'linear-gradient(135deg, rgba(52, 199, 89, 0.1), #FFFFFF)';
                    }
                });

                // Drag & Drop Events
                uploadButton.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadButton.style.borderColor = 'var(--system-blue)';
                    uploadButton.style.background = 'linear-gradient(135deg, rgba(0, 122, 255, 0.1), #FFFFFF)';
                });

                uploadButton.addEventListener('dragleave', () => {
                    if (fileInput.files.length === 0) {
                        uploadButton.style.borderColor = 'var(--system-separator)';
                        uploadButton.style.background = 'linear-gradient(135deg, var(--system-gray6), #FFFFFF)';
                    }
                });

                uploadButton.addEventListener('drop', (e) => {
                    e.preventDefault();
                    fileInput.files = e.dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change'));
                });
            }

            // Form Validation
            if (form) {
                form.addEventListener('submit', function (e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    // Loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Wird gesendet...';
                    submitBtn.disabled = true;

                    // Re-enable after 3 seconds if still processing
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                });
            }

            // ESC-Taste schließt Modal
            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape") closeModal();
            });

            // Klick außerhalb des Modals schließt es
            document.addEventListener("click", function (e) {
                if (e.target.classList.contains('modal')) closeModal();
            });

            // Auto-resize textarea
            const textarea = document.querySelector('textarea[name="message"]');
            if (textarea) {
                textarea.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }

            // Add animations to cards on load
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });

        // Add additional animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
            @keyframes slideDown {
                from {
                    transform: translateX(-50%) translateY(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(-50%) translateY(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>