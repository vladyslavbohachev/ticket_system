<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    header('Location: login.php');
    exit();
}

require 'config.php';

// Fehler reporting für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$searchQuery = '';
$results = [];
$statusCounts = [
    'all' => 0,
    'offen' => 0,
    'in Bearbeitung' => 0,
    'geschlossen' => 0
];

try {
    if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
        $searchQuery = trim($_GET['q']);
        
        // Prepared Statement für die Suche - nur existierende Spalten
        $stmt = $pdo->prepare("
            SELECT * FROM tickets 
            WHERE 
                subject LIKE ? 
                OR name LIKE ? 
                OR ticket_number LIKE ? 
                OR email LIKE ?
                OR message LIKE ?
            ORDER BY 
                CASE 
                    WHEN status = 'offen' THEN 1
                    WHEN status = 'in Bearbeitung' THEN 2
                    WHEN status = 'geschlossen' THEN 3
                END,
                created_at DESC
        ");
        
        $searchTerm = "%$searchQuery%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Status-Zahlen berechnen
        $statusCounts['all'] = count($allResults);
        $statusCounts['offen'] = count(array_filter($allResults, fn($t) => $t['status'] === 'offen'));
        $statusCounts['in Bearbeitung'] = count(array_filter($allResults, fn($t) => $t['status'] === 'in Bearbeitung'));
        $statusCounts['geschlossen'] = count(array_filter($allResults, fn($t) => $t['status'] === 'geschlossen'));
        
        // Status-Filter anwenden
        $statusFilter = $_GET['status'] ?? 'all';
        if ($statusFilter !== 'all') {
            $results = array_filter($allResults, fn($t) => $t['status'] === $statusFilter);
        } else {
            $results = $allResults;
        }
    }
} catch (PDOException $e) {
    // Fehler logging
    error_log("Database error in search.php: " . $e->getMessage());
    $error = "Datenbankfehler: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suchergebnisse</title>
    <link rel="icon" href="" sizes="32x32" />
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

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, var(--system-gray6) 0%, #FFFFFF 100%);
            color: var(--system-label);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .header {
            background: var(--system-background);
            border-bottom: 1px solid var(--system-separator);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.8);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header h1 {
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
            background: linear-gradient(135deg, var(--system-blue), var(--system-purple));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
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
        }

        .user-actions a:hover {
            background: rgba(0, 122, 255, 0.1);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .card {
            background: var(--system-background);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--system-separator);
            margin-bottom: 24px;
        }

        .search-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-form {
            display: flex;
            gap: 12px;
            flex: 1;
            max-width: 500px;
        }

        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--system-separator);
            border-radius: 10px;
            font-size: 15px;
            background: var(--system-background);
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--system-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--system-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #0056CC;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--system-gray6);
            color: var(--system-label);
            border: 1px solid var(--system-separator);
        }

        .btn-secondary:hover {
            background: var(--system-separator);
        }

        .status-filters {
            display: flex;
            gap: 8px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .status-filter {
            padding: 8px 16px;
            border: 1px solid var(--system-separator);
            border-radius: 20px;
            background: var(--system-gray6);
            color: var(--system-gray);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .status-filter.active {
            background: var(--system-blue);
            color: white;
            border-color: var(--system-blue);
        }

        .status-filter:hover {
            transform: translateY(-1px);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
        }

        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .ticket-table th {
            text-align: left;
            padding: 16px 12px;
            font-size: 15px;
            font-weight: 600;
            color: var(--system-gray);
            border-bottom: 2px solid var(--system-separator);
            background: var(--system-gray6);
        }

        .ticket-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--system-separator);
            font-size: 15px;
        }

        .ticket-table tr:hover td {
            background: var(--system-gray6);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .error-message {
            background: #FFE6E6;
            border: 1px solid var(--system-red);
            color: var(--system-red);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            
            .search-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-form {
                max-width: none;
            }
            
            .status-filters {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-logo">
                <h1>Suchergebnisse</h1>
                <div class="logo-icon">
                    <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> zurück</a>
                </div>
            </div>
            <div class="user-info">
                <span>Angemeldet als: <strong><?= htmlspecialchars($_SESSION['admin_username']) ?></strong></span>
                <div class="user-actions">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="closed.php"><i class="fas fa-archive"></i> Geschlossen</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <strong>Fehler:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="search-header">
                <form method="GET" action="search.php" class="search-form">
                    <input type="text" 
                           name="q" 
                           class="search-input" 
                           placeholder="Tickets durchsuchen (Betreff, Name, Nummer, E-Mail, Inhalt)..."
                           value="<?= htmlspecialchars($searchQuery) ?>"
                           required>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Suchen
                    </button>
                </form>
                
                <?php if (!empty($searchQuery)): ?>
                    <div style="color: var(--system-gray); font-weight: 500;">
                        <?= count($results) ?> Ergebnis<?= count($results) !== 1 ? 'se' : '' ?> für "<?= htmlspecialchars($searchQuery) ?>"
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($searchQuery)): ?>
                <!-- Status Filter -->
                <div class="status-filters">
                    <a href="search.php?q=<?= urlencode($searchQuery) ?>&status=all" 
                       class="status-filter <?= ($_GET['status'] ?? 'all') === 'all' ? 'active' : '' ?>">
                       Alle (<?= $statusCounts['all'] ?>)
                    </a>
                    <a href="search.php?q=<?= urlencode($searchQuery) ?>&status=offen" 
                       class="status-filter <?= ($_GET['status'] ?? '') === 'offen' ? 'active' : '' ?>">
                       Offen (<?= $statusCounts['offen'] ?>)
                    </a>
                    <a href="search.php?q=<?= urlencode($searchQuery) ?>&status=in Bearbeitung" 
                       class="status-filter <?= ($_GET['status'] ?? '') === 'in Bearbeitung' ? 'active' : '' ?>">
                       In Bearbeitung (<?= $statusCounts['in Bearbeitung'] ?>)
                    </a>
                    <a href="search.php?q=<?= urlencode($searchQuery) ?>&status=geschlossen" 
                       class="status-filter <?= ($_GET['status'] ?? '') === 'geschlossen' ? 'active' : '' ?>">
                       Geschlossen (<?= $statusCounts['geschlossen'] ?>)
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!empty($results)): ?>
                <div class="table-container">
                    <table class="ticket-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Ticketnummer</th>
                                <th>Betreff</th>
                                <th>Name</th>
                                <th>E-Mail</th>
                                <th>Erstellt am</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $ticket): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        if ($ticket['status'] === 'offen') $statusClass = 'status-offen';
                                        if ($ticket['status'] === 'in Bearbeitung') $statusClass = 'status-in-bearbeitung';
                                        if ($ticket['status'] === 'geschlossen') $statusClass = 'status-geschlossen';
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <i class="fas fa-circle"></i>
                                            <?= htmlspecialchars($ticket['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($ticket['ticket_number'] ?? '') ?></td>
                                    <td><strong><?= htmlspecialchars($ticket['subject'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($ticket['name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($ticket['email'] ?? '') ?></td>
                                    <td><?= isset($ticket['created_at']) ? date('d.m.Y H:i', strtotime($ticket['created_at'])) : '' ?></td>
                                    <td>
                                        <a href="edit_ticket.php?ticket=<?= urlencode($ticket['ticket_number'] ?? '') ?>" class="btn btn-primary" target="_blank">
                                            <i class="fas fa-edit"></i> Bearbeiten
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (!empty($searchQuery)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Keine Tickets gefunden für "<?= htmlspecialchars($searchQuery) ?>"</p>
                    <p style="font-size: 14px; margin-top: 10px;">Versuchen Sie es mit einem anderen Suchbegriff</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Geben Sie einen Suchbegriff ein, um Tickets zu finden</p>
                    <p style="font-size: 14px; margin-top: 10px;">Suche in: Betreff, Name, Ticketnummer, E-Mail und Nachrichten</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>