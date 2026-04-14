<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nicht autorisiert']);
    exit();
}

require 'config.php';

$token = $_GET['ticket'] ?? '';
if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'Kein Ticket angegeben.']);
    exit();
}

// Ticket-ID holen
$stmt = $pdo->prepare("SELECT id FROM tickets WHERE ticket_number = ?");
$stmt->execute([$token]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo json_encode(['status' => 'error', 'message' => 'Ticket nicht gefunden.']);
    exit();
}

try {
    // Updates löschen
    $pdo->prepare("DELETE FROM ticket_updates WHERE ticket_id = ?")->execute([$ticket['id']]);
    
    // Ticket löschen
    $pdo->prepare("DELETE FROM tickets WHERE ticket_number = ?")->execute([$token]);

    // ✅ JSON-Antwort statt Session
    echo json_encode([
        'status' => 'success',
        'message' => "Ticket #{$token} wurde gelöscht.",
        'token' => $token
    ]);

} catch (Exception $e) {
    error_log("🚨 Fehler beim Löschen: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Fehler beim Löschen des Tickets.']);
}