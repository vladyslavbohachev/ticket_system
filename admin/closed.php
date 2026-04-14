<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    header('Location: login.php');
    exit();
}

require 'config.php';

// Zeitraum-Filter (Standard: letzte 30 Tage)
$timeFilter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'last_30_days';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$employeeFilter = isset($_GET['employee']) ? $_GET['employee'] : 'all';

// Zeitfilter basierend auf created_at (da closed_at nicht existiert)
$dateConditions = "";
$dateParams = [];

switch ($timeFilter) {
    case 'today':
        $dateConditions = "DATE(t.created_at) = CURDATE()";
        break;
    case 'yesterday':
        $dateConditions = "DATE(t.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $dateConditions = "YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'last_week':
        $dateConditions = "YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)";
        break;
    case 'this_month':
        $dateConditions = "MONTH(t.created_at) = MONTH(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE())";
        break;
    case 'last_30_days':
        $dateConditions = "t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'last_90_days':
        $dateConditions = "t.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
        break;
    case 'this_year':
        $dateConditions = "YEAR(t.created_at) = YEAR(CURDATE())";
        break;
    case 'custom':
        if (!empty($startDate) && !empty($endDate)) {
            $dateConditions = "DATE(t.created_at) BETWEEN ? AND ?";
            $dateParams = [$startDate, $endDate];
        }
        break;
}

// Mitarbeiter-Filter
$employeeCondition = "";
if ($employeeFilter !== 'all' && $employeeFilter !== '') {
    $employeeCondition = "AND t.closed_by = ?";
    $dateParams[] = $employeeFilter;
}

// Statistiken abrufen (vereinfacht ohne closed_at)
$statsQuery = "
    SELECT 
        COUNT(*) as total_closed,
        COUNT(DISTINCT DATE(t.created_at)) as days_with_closures,
        COUNT(DISTINCT t.closed_by) as employees_involved,
        MIN(t.created_at) as first_closure,
        MAX(t.created_at) as last_closure
    FROM tickets t
    WHERE t.status = 'geschlossen' 
    " . (!empty($dateConditions) ? "AND $dateConditions" : "");
    
$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute($dateParams);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Geschlossene Tickets pro Monat (für Diagramm)
$monthlyQuery = "
    SELECT 
        DATE_FORMAT(t.created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM tickets t
    WHERE t.status = 'geschlossen' 
    " . (!empty($dateConditions) ? "AND $dateConditions" : "") . "
    " . (!empty($employeeCondition) ? $employeeCondition : "") . "
    GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";

$monthlyStmt = $pdo->prepare($monthlyQuery);
$monthlyStmt->execute($dateParams);
$monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

// Geschlossene Tickets pro Woche
$weeklyQuery = "
    SELECT 
        YEARWEEK(t.created_at, 1) as week,
        CONCAT('KW ', WEEK(t.created_at), ' (', DATE_FORMAT(MIN(t.created_at), '%d.%m.'), ')') as week_label,
        COUNT(*) as count
    FROM tickets t
    WHERE t.status = 'geschlossen' 
    " . (!empty($dateConditions) ? "AND $dateConditions" : "") . "
    " . (!empty($employeeCondition) ? $employeeCondition : "") . "
    GROUP BY YEARWEEK(t.created_at, 1)
    ORDER BY week DESC
    LIMIT 8
";

$weeklyStmt = $pdo->prepare($weeklyQuery);
$weeklyStmt->execute($dateParams);
$weeklyData = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

// KORREKTUR: Top Mitarbeiter (wer schließt die meisten Tickets)
$topEmployeesQuery = "
    SELECT 
        a.id,
        CONCAT(a.first_name, ' ', a.last_name) as employee_name,
        COUNT(t.id) as tickets_closed
    FROM admins a
    LEFT JOIN tickets t ON a.id = t.closed_by AND t.status = 'geschlossen'
    " . (!empty($dateConditions) ? "AND $dateConditions" : "") . "
    WHERE t.closed_by IS NOT NULL
    GROUP BY a.id
    ORDER BY tickets_closed DESC
    LIMIT 10
";

// Für die Top-Mitarbeiter-Abfrage müssen wir den Tabellenalias 't' in der WHERE-Klausel korrigieren
$topEmployeesQueryFixed = "
    SELECT 
        a.id,
        CONCAT(a.first_name, ' ', a.last_name) as employee_name,
        COUNT(t.id) as tickets_closed
    FROM admins a
    LEFT JOIN tickets t ON a.id = t.closed_by 
    WHERE t.status = 'geschlossen'
    " . (!empty($dateConditions) ? "AND " . str_replace('t.created_at', 't.created_at', $dateConditions) : "") . "
    " . (!empty($employeeCondition) ? str_replace('t.closed_by', 't.closed_by', $employeeCondition) : "") . "
    GROUP BY a.id
    ORDER BY tickets_closed DESC
    LIMIT 10
";

$topEmployeesStmt = $pdo->prepare($topEmployeesQueryFixed);
$topEmployeesStmt->execute($dateParams);
$topEmployees = $topEmployeesStmt->fetchAll(PDO::FETCH_ASSOC);

// Aktuelle geschlossene Tickets (für die Tabelle)
$ticketsQuery = "
    SELECT t.*, 
           CONCAT(a.first_name, ' ', a.last_name) as closed_by_name
    FROM tickets t
    LEFT JOIN admins a ON t.closed_by = a.id
    WHERE t.status = 'geschlossen'
    " . (!empty($dateConditions) ? "AND $dateConditions" : "") . "
    " . (!empty($employeeCondition) ? $employeeCondition : "") . "
    ORDER BY t.created_at DESC
    LIMIT 50
";

$ticketsStmt = $pdo->prepare($ticketsQuery);
$ticketsStmt->execute($dateParams);
$closedTickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

// Alle Mitarbeiter für Filter laden
$employees = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM admins ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket-Statistiken</title>
    <link rel="icon" href="" sizes="32x32" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.8);
            padding: 0 24px;
            height: 70px;
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
            margin: 0;
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

        .card-header {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--system-separator);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .card-header h2 {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--system-label);
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--system-gray6);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--system-separator);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0;
            color: var(--system-blue);
        }

        .stat-label {
            font-size: 14px;
            color: var(--system-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--system-label);
        }

        .form-control, select {
            padding: 12px 16px;
            border: 1px solid var(--system-separator);
            border-radius: 10px;
            font-size: 15px;
            background: var(--system-background);
            transition: all 0.2s ease;
        }

        .form-control:focus, select:focus {
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

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-container {
            position: relative;
            height: 300px;
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

        .status-geschlossen {
            background: linear-gradient(135deg, var(--system-gray), #8E8E93);
            color: white;
        }

        .employee-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: var(--system-orange);
            color: white;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 15px;
            color: var(--system-gray);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 9998;
            backdrop-filter: blur(8px);
            padding: 20px;
        }

        .modal-content {
            background: var(--system-background);
            border-radius: 20px;
            width: 100%;
            max-width: 1200px;
            height: 90vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transform: scale(0.9);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-overlay.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--system-separator);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--system-background);
        }

        .modal-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--system-label);
            margin: 0;
        }

        .modal-close {
            background: var(--system-gray6);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 20px;
            color: var(--system-gray);
        }

        .modal-close:hover {
            background: var(--system-separator);
            transform: rotate(90deg);
        }

        .modal-iframe {
            width: 100%;
            height: calc(100% - 89px);
            border: none;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--system-gray);
        }
        
        .no-data i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-content">
            <h1>
                <div class="logo-icon"><i class="fas fa-chart-bar"></i></div>
                Ticket-Statistiken
            </h1>
            <div class="user-info">
                <span>Angemeldet als: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zum Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filter-Formular -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-filter"></i> Filter</h2>
            </div>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label>Zeitraum:</label>
                    <select name="time_filter" onchange="toggleCustomDate()">
                        <option value="today" <?= $timeFilter == 'today' ? 'selected' : '' ?>>Heute</option>
                        <option value="yesterday" <?= $timeFilter == 'yesterday' ? 'selected' : '' ?>>Gestern</option>
                        <option value="this_week" <?= $timeFilter == 'this_week' ? 'selected' : '' ?>>Diese Woche</option>
                        <option value="last_week" <?= $timeFilter == 'last_week' ? 'selected' : '' ?>>Letzte Woche</option>
                        <option value="this_month" <?= $timeFilter == 'this_month' ? 'selected' : '' ?>>Dieser Monat</option>
                        <option value="last_30_days" <?= $timeFilter == 'last_30_days' ? 'selected' : '' ?>>Letzte 30 Tage</option>
                        <option value="last_90_days" <?= $timeFilter == 'last_90_days' ? 'selected' : '' ?>>Letzte 90 Tage</option>
                        <option value="this_year" <?= $timeFilter == 'this_year' ? 'selected' : '' ?>>Dieses Jahr</option>
                        <option value="custom" <?= $timeFilter == 'custom' ? 'selected' : '' ?>>Benutzerdefiniert</option>
                    </select>
                </div>

                <div class="form-group" id="custom-date-group" style="display: <?= $timeFilter == 'custom' ? 'flex' : 'none' ?>">
                    <label>Benutzerdefinierter Zeitraum:</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                        <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Mitarbeiter:</label>
                    <select name="employee">
                        <option value="all" <?= $employeeFilter == 'all' ? 'selected' : '' ?>>Alle Mitarbeiter</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= $employee['id'] ?>" <?= $employeeFilter == $employee['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($employee['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i> Statistiken anzeigen
                    </button>
                    <a href="closed.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Zurücksetzen
                    </a>
                </div>
            </form>
        </div>

        <!-- Übersichtsstatistiken -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Geschlossene Tickets</div>
                <div class="stat-value"><?= $stats['total_closed'] ?? 0 ?></div>
                <small>Gesamt im Zeitraum</small>
            </div>

            <div class="stat-card">
                <div class="stat-label">Beteiligte Mitarbeiter</div>
                <div class="stat-value"><?= $stats['employees_involved'] ?? 0 ?></div>
                <small>Anzahl</small>
            </div>

            <div class="stat-card">
                <div class="stat-label">Tage mit Schließungen</div>
                <div class="stat-value"><?= $stats['days_with_closures'] ?? 0 ?></div>
                <small>In Zeitraum</small>
            </div>

            <div class="stat-card">
                <div class="stat-label">Zeitraum</div>
                <div class="stat-value" style="font-size: 16px;">
                    <?php if ($stats['first_closure'] && $stats['last_closure']): ?>
                        <?= date('d.m.Y', strtotime($stats['first_closure'])) ?> -<br>
                        <?= date('d.m.Y', strtotime($stats['last_closure'])) ?>
                    <?php else: ?>
                        Keine Daten
                    <?php endif; ?>
                </div>
                <small>Von - Bis</small>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Geschlossene Tickets pro Monat</h2>
                </div>
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-bar"></i> Top Mitarbeiter</h2>
                </div>
                <div class="chart-container">
                    <canvas id="employeeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Mitarbeiter Liste -->
        <?php if (!empty($topEmployees)): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-medal"></i> Top 10 Mitarbeiter</h2>
            </div>
            <div class="table-container">
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Mitarbeiter</th>
                            <th>Geschlossene Tickets</th>
                            <th>Prozentsatz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $totalTickets = array_sum(array_column($topEmployees, 'tickets_closed')); ?>
                        <?php $rank = 1; ?>
                        <?php foreach ($topEmployees as $employee): ?>
                            <tr>
                                <td>
                                    <span class="employee-rank"><?= $rank++ ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($employee['employee_name']) ?></strong></td>
                                <td><?= $employee['tickets_closed'] ?></td>
                                <td>
                                    <div style="background: var(--system-gray6); height: 20px; border-radius: 10px; overflow: hidden;">
                                        <div style="background: var(--system-blue); height: 100%; width: <?= $totalTickets > 0 ? ($employee['tickets_closed'] / $totalTickets) * 100 : 0 ?>%"></div>
                                    </div>
                                    <?= $totalTickets > 0 ? round(($employee['tickets_closed'] / $totalTickets) * 100, 1) : 0 ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif (!empty($dateParams)): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-medal"></i> Top 10 Mitarbeiter</h2>
            </div>
            <div class="no-data">
                <i class="fas fa-users-slash"></i>
                <p>Keine Mitarbeiterdaten im gewählten Zeitraum verfügbar</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Letzte geschlossene Tickets -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history"></i> Zuletzt geschlossene Tickets</h2>
            </div>
            <?php if (!empty($closedTickets)): ?>
            <div class="table-container">
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th>Ticketnummer</th>
                            <th>Betreff</th>
                            <th>Geschlossen von</th>
                            <th>Erstellt am</th>
							<th>Erstellt von</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($closedTickets as $ticket): ?>
                            <tr>
                                <td>
                                    <span class="status-badge status-geschlossen">
                                        <i class="fas fa-check-circle"></i>
                                        <?= htmlspecialchars($ticket['ticket_number']) ?>
                                    </span>
                                </td>
                                <td><strong><?= $ticket['subject'] ?></strong></td>
                                <td><?= htmlspecialchars($ticket['closed_by_name'] ?? 'Unbekannt') ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></td>
								<td><?= htmlspecialchars($ticket['name'] ?? 'Unbekannt') ?></td>
                                <td>
                                    <a href="#" onclick="openTicket('<?= urlencode($ticket['ticket_number']) ?>')"
                                        class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Ansehen
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <p>Keine geschlossenen Tickets im gewählten Zeitraum</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal für Ticket-Anzeige -->
    <div id="ticketModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ticket ansehen</h2>
                <button class="modal-close" onclick="closeTicket()">×</button>
            </div>
            <iframe id="ticketFrame" class="modal-iframe" src=""></iframe>
        </div>
    </div>

    <script>
        // Toggle custom date fields
        function toggleCustomDate() {
            const select = document.querySelector('select[name="time_filter"]');
            const customGroup = document.getElementById('custom-date-group');
            customGroup.style.display = select.value === 'custom' ? 'flex' : 'none';
        }

        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Chart
            const monthlyCtx = document.getElementById('monthlyChart');
            if (monthlyCtx) {
                const monthlyData = <?= json_encode(array_reverse($monthlyData)) ?>;
                
                if (monthlyData.length > 0) {
                    new Chart(monthlyCtx, {
                        type: 'line',
                        data: {
                            labels: monthlyData.map(d => {
                                const [year, month] = d.month.split('-');
                                return new Date(year, month-1).toLocaleDateString('de-DE', { month: 'short', year: 'numeric' });
                            }),
                            datasets: [{
                                label: 'Geschlossene Tickets',
                                data: monthlyData.map(d => d.count),
                                borderColor: 'rgb(0, 122, 255)',
                                backgroundColor: 'rgba(0, 122, 255, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true
                                }
                            }
                        }
                    });
                } else {
                    monthlyCtx.parentElement.innerHTML = '<div class="no-data"><i class="fas fa-chart-line"></i><p>Keine Daten für diesen Zeitraum verfügbar</p></div>';
                }
            }

            // Employee Chart
            const employeeCtx = document.getElementById('employeeChart');
            if (employeeCtx) {
                const employeeData = <?= json_encode($topEmployees) ?>;
                
                if (employeeData.length > 0) {
                    new Chart(employeeCtx, {
                        type: 'bar',
                        data: {
                            labels: employeeData.map(e => e.employee_name.split(' ')[0]),
                            datasets: [{
                                label: 'Geschlossene Tickets',
                                data: employeeData.map(e => e.tickets_closed),
                                backgroundColor: [
                                    'rgba(0, 122, 255, 0.8)',
                                    'rgba(52, 199, 89, 0.8)',
                                    'rgba(255, 149, 0, 0.8)',
                                    'rgba(255, 59, 48, 0.8)',
                                    'rgba(175, 82, 222, 0.8)',
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                } else {
                    employeeCtx.parentElement.innerHTML = '<div class="no-data"><i class="fas fa-users"></i><p>Keine Mitarbeiterdaten verfügbar</p></div>';
                }
            }
        });

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
        const modal = document.getElementById("ticketModal");
        if (modal) {
            modal.addEventListener("click", function (e) {
                if (e.target.id === "ticketModal") {
                    closeTicket();
                }
            });
        }
    </script>
</body>
</html>