<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    header('Location: login.php');
    exit();
}

require 'config.php';

// Alle Tickets laden
$tickets = $pdo->query("SELECT * FROM tickets ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Helper-Funktionen zur Datumsprüfung
function isToday($date)
{
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

function isThisWeek($date)
{
    return date('W', strtotime($date)) === date('W') && date('Y', strtotime($date)) === date('Y');
}

function isThisMonth($date)
{
    return date('Y-m', strtotime($date)) === date('Y-m');
}

function isThisYear($date)
{
    return date('Y', strtotime($date)) === date('Y');
}

// Ticketzahlen zählen
$total = count($tickets);
$open = count(array_filter($tickets, fn($t) => $t['status'] === 'offen'));
$inProgress = count(array_filter($tickets, fn($t) => $t['status'] === 'in Bearbeitung'));
$closed = count(array_filter($tickets, fn($t) => $t['status'] === 'geschlossen'));

// Statistiken Tag / Woche / Monat
$ticketsToday = count(array_filter($tickets, fn($t) => isToday($t['created_at'])));
$ticketsThisWeek = count(array_filter($tickets, fn($t) => isThisWeek($t['created_at'])));
$ticketsThisMonth = count(array_filter($tickets, fn($t) => isThisMonth($t['created_at'])));
$ticketsThisYear = count(array_filter($tickets, fn($t) => isThisYear($t['created_at'])));

// Prozent berechnen
$percentOpen = $total > 0 ? round(($open / $total) * 100, 1) : 0;
$percentInProgress = $total > 0 ? round(($inProgress / $total) * 100, 1) : 0;
$percentClosed = $total > 0 ? round(($closed / $total) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" href="" sizes="32x32" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        <?php include 'assets/css/dashboard.css'; ?>
    </style>
</head>

<body>
    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <i id="toast-icon" class="toast-icon"></i>
        <span id="toast-message" class="toast-message"></span>
    </div>

    <!-- Success Message -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="toast toast-success show">
            <i class="fas fa-check-circle toast-icon"></i>
            <span class="toast-message"><?= htmlspecialchars($_SESSION['success']) ?></span>
        </div>
        <?php unset($_SESSION['success']); ?>
        <script>
            setTimeout(() => {
                document.querySelector('.toast-success').classList.remove('show');
            }, 3000);
        </script>
    <?php endif; ?>

    <?php include 'includes/head.php'; ?>


    <!-- Main Content -->
    <div class="container">
        <!-- Left Column - Tickets -->
        <div>
            <!-- Offene Tickets -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-inbox" style="color: var(--system-green);"></i> Offene Tickets</h2>
                    <span
                        class="card-count"><?= count($openTickets = array_filter($tickets, fn($t) => $t['status'] === 'offen')) ?></span>
                </div>

                <?php if (!empty($openTickets)): ?>
                    <div class="table-container">
                        <table class="ticket-table">
                            <thead>
                                <tr>
                                    <th>Betreff</th>
                                    <th>Ticketnummer</th>
                                    <th>Name</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($openTickets as $t): ?>
                                    <tr>
                                        <td><strong><?= $t['subject'] ?></strong></td>
                                        <td>
                                            <span class="status-badge status-offen">
                                                <i class="fas fa-circle"></i>
                                                <?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                <a href="#" onclick="openTicket('<?= urlencode($t['ticket_number']) ?>')"
                                                    class="btn btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                    <span class="btn-text">Bearbeiten</span>
                                                </a>
                                                <a href="#" onclick="confirmDelete('<?= urlencode($t['ticket_number']) ?>')"
                                                    class="btn btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                    <span class="btn-text">Löschen</span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="ticket-cards">
                        <?php foreach ($openTickets as $t): ?>
                            <div class="ticket-card">
                                <div class="ticket-card-header">
                                    <span class="status-badge status-offen">
                                        <i class="fas fa-circle"></i>
                                        <?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <div>
                                        <div class="ticket-card-subject">
                                            <?= $t['subject'] ?>
                                        </div>
                                        <div class="ticket-card-meta">
                                            <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                    
                                </div>
                                <div class="ticket-card-actions">
                                    <a href="#" onclick="openTicket('<?= urlencode($t['ticket_number']) ?>')"
                                        class="btn btn-primary" style="flex: 1;">
                                        <i class="fas fa-edit"></i>
                                        Bearbeiten
                                    </a>
                                    <a href="#" onclick="confirmDelete('<?= urlencode($t['ticket_number']) ?>')"
                                        class="btn btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Keine offenen Tickets vorhanden</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- In Bearbeitung -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-spinner" style="color: var(--system-orange);"></i> In Bearbeitung</h2>
                    <span
                        class="card-count"><?= count($inProgressTickets = array_filter($tickets, fn($t) => $t['status'] === 'in Bearbeitung')) ?></span>
                </div>
                <?php if (!empty($inProgressTickets)): ?>
                    <div class="table-container">
                        <table class="ticket-table">
                            <thead>
                                <tr>
                                    <th>Ticketnummer</th>
                                    <th>Betreff</th>
                                    <th>Name</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inProgressTickets as $t): ?>
                                    <tr>
                                        
                                        <td>
                                            <span class="status-badge status-in-bearbeitung">
                                                <?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td><strong><?= $t['subject'] ?></strong></td>
                                        <td><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                                <a href="#" onclick="openTicket('<?= urlencode($t['ticket_number']) ?>')" class="btn btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                    <span class="btn-text"></span>
                                                </a>
                                                <a href="#" onclick="confirmDelete('<?= urlencode($t['ticket_number']) ?>')" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                    <span class="btn-text"></span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="ticket-cards">
                        <?php foreach ($inProgressTickets as $t): ?>
                            <div class="ticket-card">
                                <div class="ticket-card-header">
                                    <div>
                                        <div class="ticket-card-subject">
                                            <?= htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="ticket-card-meta">
                                            <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                    <span class="status-badge status-in-bearbeitung">
                                        <i class="fas fa-circle"></i>
                                        <?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <div class="ticket-card-actions">
                                    <a href="#" onclick="openTicket('<?= urlencode($t['ticket_number']) ?>')"
                                        class="btn btn-primary" style="flex: 1;">
                                        <i class="fas fa-edit"></i>
                                        Bearbeiten
                                    </a>
                                    <a href="#" onclick="confirmDelete('<?= urlencode($t['ticket_number']) ?>')"
                                        class="btn btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-spinner"></i>
                        <p>Keine Tickets in Bearbeitung</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column - Statistics -->
        <div>
            <!-- Statistics Card -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie" style="color: var(--system-purple);"></i> Übersicht</h2>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total ?></div>
                        <div class="stat-label">Gesamt</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $open ?></div>
                        <div class="stat-label">Offen</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $inProgress ?></div>
                        <div class="stat-label">In Bearbeitung</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $closed ?></div>
                        <div class="stat-label">Geschlossen</div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <div class="progress-label">
                            <i class="fas fa-circle" style="color: var(--system-green);"></i>
                            Offen
                        </div>
                        <div class="progress-percent"><?= $percentOpen ?>%</div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill progress-green" style="width: <?= $percentOpen ?>%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <div class="progress-label">
                            <i class="fas fa-circle" style="color: var(--system-orange);"></i>
                            In Bearbeitung
                        </div>
                        <div class="progress-percent"><?= $percentInProgress ?>%</div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill progress-orange" style="width: <?= $percentInProgress ?>%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <div class="progress-label">
                            <i class="fas fa-circle" style="color: var(--system-gray);"></i>
                            Geschlossen
                        </div>
                        <div class="progress-percent"><?= $percentClosed ?>%</div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill progress-gray" style="width: <?= $percentClosed ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Time Statistics -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-calendar-alt" style="color: var(--system-blue);"></i> Zeitliche Übersicht</h2>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $ticketsToday ?></div>
                        <div class="stat-label">Heute</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $ticketsThisWeek ?></div>
                        <div class="stat-label">Diese Woche</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $ticketsThisMonth ?></div>
                        <div class="stat-label">Dieser Monat</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $ticketsThisYear ?></div>
                        <div class="stat-label">Dieses Jahr</div>
                    </div>
                </div>

                <center>
                    <a href="#" class="btn btn-secondary" style="margin-top: 16px;">
                        <i class="fas fa-file-pdf"></i> PDF Export
                    </a>
                </center>
            </div>
            <!-- In der rechten Spalte des Dashboards -->
            <div class="card" hidden>
                <div class="card-header">
                    <h2><i class="fas fa-link" style="color: var(--system-blue);"></i> Schnellzugriff</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="closed.php" class="btn btn-secondary" style="justify-content: center;">
                        <i class="fas fa-archive"></i> Geschlossene Tickets anzeigen
                    </a>
                    <a href="search.php" class="btn btn-secondary" style="justify-content: center;">
                        <i class="fas fa-search"></i> Globale Suche
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="ticketModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ticket bearbeiten</h2>
                <button class="modal-close" onclick="closeTicket()">×</button>
            </div>
            <iframe id="ticketFrame" class="modal-iframe" src=""></iframe>
        </div>
    </div>

    <script>
        // Modal Functions
        function openTicket(token) {
            const modal = document.getElementById("ticketModal");
            const iframe = document.getElementById("ticketFrame");

            modal.style.display = "flex";
            setTimeout(() => {
                modal.classList.add("show");
            }, 10);

            iframe.src = "edit_ticket.php?ticket=" + token;
            document.body.style.overflow = "hidden";
        }

        function closeTicket() {
            const modal = document.getElementById("ticketModal");
            const iframe = document.getElementById("ticketFrame");

            modal.classList.remove("show");
            setTimeout(() => {
                modal.style.display = "none";
                iframe.src = "";
                document.body.style.overflow = "";
            }, 300);
        }

        // ESC key closes modal
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeTicket();
            }
        });

        // Click outside modal content closes it
        document.getElementById("ticketModal").addEventListener("click", function (e) {
            if (e.target.id === "ticketModal") {
                closeTicket();
            }
        });

        // Listen for messages from iframe
        window.addEventListener('message', function (event) {
            if (event.data === 'ticketSaved') {
                closeTicket();
                setTimeout(() => {
                    location.reload();
                }, 500);
            }
        });

        // Delete functions
        function confirmDelete(token) {
            if (confirm("Möchten Sie dieses Ticket wirklich löschen?")) {
                deleteTicket(token);
            }
        }

        function deleteTicket(token) {
            fetch("delete_ticket.php?ticket=" + token)
                .then(response => response.json())
                .then(data => {
                    showToast(data.message, data.status === 'success' ? 'success' : 'error');
                    if (data.status === 'success') {
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                })
                .catch(error => {
                    showToast('Fehler beim Löschen des Tickets', 'error');
                });
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toast-icon');
            const toastMessage = document.getElementById('toast-message');

            // Set icon and style
            if (type === 'success') {
                toastIcon.className = 'fas fa-check-circle toast-icon';
                toast.className = 'toast toast-success';
            } else {
                toastIcon.className = 'fas fa-exclamation-circle toast-icon';
                toast.className = 'toast toast-error';
            }

            toastMessage.textContent = message;
            toast.style.display = 'flex';

            setTimeout(() => {
                toast.classList.add('show');
            }, 10);

            // Hide after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 300);
            }, 3000);
        }

        // Animate progress bars on load
        document.addEventListener('DOMContentLoaded', function () {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });

        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function () {
            const mobileMenu = document.createElement('button');
            mobileMenu.className = 'mobile-menu';
            mobileMenu.innerHTML = '<i class="fas fa-bars"></i>';
            document.querySelector('.header-content').prepend(mobileMenu);

            mobileMenu.addEventListener('click', function () {
                document.querySelector('.user-info').classList.toggle('show');
            });
        });


    </script>
    <?php include 'includes/footer.php'; ?>