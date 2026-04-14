<?php

/**
 * Sendet eine E-Mail über SMTP (TLS) ohne externe Bibliotheken.
 *
 * @param string $to Empfängeradresse
 * @param string $subject Betreff
 * @param string $message Nachrichtentext (kann HTML sein)
 * @param bool $isHtml Ob die Mail im HTML-Format gesendet werden soll
 * @return bool Erfolgreich gesendet?
 */
function sendMail($to, $subject, $message, $isHtml = false) {
    $smtpServer   = 'SMTP';
    $smtpPort     = 587;
    $smtpUser     = 'ticket@domain.tld';
    $smtpPassword = ''; // ⚠️ Achtung: Passwort immer aktuell halten!
    $from         = 'ticket@domain.tld';
    $nameFrom     = 'Support System';

    // Verbindung herstellen
    $socket = fsockopen($smtpServer, $smtpPort, $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP Fehler: Verbindung fehlgeschlagen - $errstr ($errno)");
        return false;
    }

    // Begrüßung lesen
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '220') {
        error_log("SMTP Fehler: Serverantwort erwartet 220, erhalten: " . $response);
        fclose($socket);
        return false;
    }

    // EHLO / HELO
    fputs($socket, "EHLO localhost\r\n");
    while (true) {
        $line = fgets($socket, 515);
        if (substr($line, 3, 1) === ' ') break;
    }

    // STARTTLS
    fputs($socket, "STARTTLS\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '220') {
        error_log("SMTP Fehler: STARTTLS fehlgeschlagen: " . $response);
        fclose($socket);
        return false;
    }

    // TLS aktivieren
    stream_set_blocking($socket, true);
    $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if (!$crypto) {
        error_log("SMTP Fehler: Konnte TLS nicht aktivieren.");
        fclose($socket);
        return false;
    }

    // Re-EHLO nach TLS
    fputs($socket, "EHLO localhost\r\n");
    while (true) {
        $line = fgets($socket, 515);
        if (substr($line, 3, 1) === ' ') break;
    }

    // LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '334') {
        error_log("SMTP Fehler: AUTH LOGIN fehlgeschlagen.");
        fclose($socket);
        return false;
    }

    fputs($socket, base64_encode($smtpUser) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '334') {
        error_log("SMTP Fehler: Benutzername ungültig.");
        fclose($socket);
        return false;
    }

    fputs($socket, base64_encode($smtpPassword) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '235') {
        error_log("SMTP Fehler: Authentifizierung fehlgeschlagen.");
        fclose($socket);
        return false;
    }

    // MAIL FROM
    fputs($socket, "MAIL FROM:<$from>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '250') {
        error_log("SMTP Fehler: MAIL FROM fehlgeschlagen: " . $response);
        fclose($socket);    
        return false;
    }

    // RCPT TO
    fputs($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '250') {
        error_log("SMTP Fehler: RCPT TO fehlgeschlagen: " . $response);
        fclose($socket);
        return false;
    }

    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '354') {
        error_log("SMTP Fehler: DATA fehlgeschlagen: " . $response);
        fclose($socket);
        return false;
    }

    // MIME-Headers + Betreff sichern
    $contentType = $isHtml ? 'text/html' : 'text/plain';
    $headers = [
        "MIME-Version: 1.0",
        "Content-type: {$contentType}; charset=utf-8",
        "From: {$nameFrom} <{$from}>",
        "Reply-To: {$from}",
        "Subject: =?utf-8?B?" . base64_encode($subject) . "?="
    ];

    // Nachricht zusammenbauen
    $emailMessage = implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.\r\n";
    fputs($socket, $emailMessage);

    // Antwort vom Server prüfen
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '250') {
        error_log("SMTP Fehler: Nachricht konnte nicht gesendet werden: " . $response);
        fclose($socket);
        return false;
    }

    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}