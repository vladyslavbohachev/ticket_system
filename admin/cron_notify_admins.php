<?php
require 'config.php';

// Hole alle Tickets, die seit X Minuten aktualisiert wurden
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE status = 'offen' AND updated_at > NOW() - INTERVAL 1 MINUTE");
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tickets as $ticket) {
    // Hole Admin-Mail
    $stmt = $pdo->prepare("SELECT email, username FROM admins WHERE id = ?");
    $stmt->execute([$ticket['updated_by']]);
    $admin = $stmt->fetch();

    if ($admin && $admin['email']) {
        $mail_body = '
        <html>
            <body style="font-family: Arial; font-size: 14px;">
                <div style="max-width: 600px; margin: auto; background: white; color: #333; border-radius: 8px; padding: 20px; border: 1px solid #ddd;">
                    <h2 style="color: #007BFF;">🔔 Neue Antwort auf Ihr Ticket</h2>
                    <p>Hallo ' . htmlspecialchars($admin['username']) . ',</p>
                    <p>Ein Kunde hat auf eines Ihrer Tickets geantwortet:</p>
                    <ul>
                        <li><strong>Kunde:</strong> ' . htmlspecialchars($ticket['name']) . '</li>
                        <li><strong>Ticketnummer:</strong> #' . htmlspecialchars($ticket['ticket_number']) . '</li>
                    </ul>
                    <p><a href=" https://ticket.domain.tld/edit_ticket.php?ticket=' . urlencode($ticket['ticket_number']) . '">Jetzt bearbeiten</a></p>
                    <p>Mit freundlichen Grüßen<br>Ihr Support Team</p>
                </div>
            </body>
        </html>
        ';
        $mail_subject = "Neue Antwort auf Ticket #" . $ticket['ticket_number'];
        $mail_subject = mb_encode_mimeheader($mail_subject, 'UTF-8', 'Q');

        sendMail($admin['email'], $mail_subject, $mail_body, true);
    }
}