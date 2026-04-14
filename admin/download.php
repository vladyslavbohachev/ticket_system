<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Zugriff verboten.');
}

// Eingehende Datei aus der URL holen
$file = $_GET['file'] ?? '';

// Ersetze %2F durch /, damit Pfade funktionieren
$file = str_replace('%2F', '/', $file);

// Basisverzeichnis: Upload liegt direkt im Hauptordner
$baseDir = __DIR__ . '/upload/';
$filePath = realpath($baseDir . '/' . basename($file));

// Prüfung: Existiert die Datei und liegt sie im Upload-Ordner?
if (!$filePath || !is_file($filePath) || strpos($filePath, $baseDir) !== 0) {
    header("HTTP/1.1 404 Not Found");
    exit("Datei nicht gefunden.");
}

// MIME-Typ ermitteln
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Header für sicheren Download
header('Content-Description: File Transfer');
header("Content-Type: $mime");
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;