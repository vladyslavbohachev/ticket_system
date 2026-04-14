<?php
// header.php
session_start();
if (!isset($_SESSION['admin_username'])) {
    header('Location: login.php');
    exit();
}

require 'config.php';

// Aktuellen Admin mit Profilbild laden
$stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
$stmt->execute([$_SESSION['admin_username']]);
$user = $stmt->fetch();

// Standard-Profilbild, falls keines gesetzt ist
$profileImage = $user['profile_image'] ?? 'default-avatar.png';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket</title>
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

        
        /* Header - Modern Design */
        .header {
            background: var(--system-background);
            border-bottom: 1px solid var(--system-separator);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.9);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1200px;
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

        .header-logo {
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

        /* Modern User Menu */
        .user-menu {
            position: relative;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .profile-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--system-blue), var(--system-purple));
            cursor: pointer;
            border: 3px solid var(--system-background);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            position: relative;
        }

        .profile-circle:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .profile-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-circle.initials {
            background: linear-gradient(135deg, var(--system-orange), var(--system-red));
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: var(--system-background);
            border-radius: 14px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--system-separator);
            min-width: 220px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
        }

        .dropdown-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 16px;
            border-bottom: 1px solid var(--system-separator);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dropdown-header .profile-circle {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
        }

        .user-info-small {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--system-label);
        }

        .user-email {
            font-size: 13px;
            color: var(--system-gray);
            margin-top: 2px;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--system-label);
            transition: all 0.2s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-size: 15px;
        }

        .dropdown-item:hover {
            background: var(--system-gray6);
        }

        .dropdown-item:first-child {
            border-radius: 14px 14px 0 0;
        }

        .dropdown-item:last-child {
            border-radius: 0 0 14px 14px;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--system-separator);
            margin: 4px 0;
        }

        .dropdown-item.logout {
            color: var(--system-red);
        }

        .dropdown-item.logout:hover {
            background: rgba(255, 59, 48, 0.1);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        /* Overlay für Klick außerhalb */
        .dropdown-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 999;
            display: none;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-action {
            padding: 8px 12px;
            border-radius: 10px;
            background: var(--system-gray6);
            color: var(--system-label);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .quick-action:hover {
            background: var(--system-separator);
            transform: translateY(-1px);
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .header-content {
                flex-wrap: wrap;
                gap: 12px;
            }
            
            .quick-actions {
                order: 3;
                width: 100%;
                justify-content: center;
                padding-top: 12px;
                border-top: 1px solid var(--system-separator);
            }
            
            .user-menu {
                margin-left: auto;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 12px 16px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .quick-action span {
                display: none;
            }
            
            .quick-action {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="dashboard.php" style="text-decoration: none;">
                <div class="header-logo">
                    <div class="logo-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <h1> Ticket</h1>
                </div>
            </a>

            <div class="user-menu">
                <!-- Quick Actions für wichtige Links -->
                <div class="quick-actions">
                    <a href="dashboard.php" class="quick-action" title="Dashboard">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="closed.php" class="quick-action" title="Geschlossene Tickets">
                        <i class="fas fa-archive"></i>
                        <span>Geschlossen</span>
                    </a>
                </div>

                <!-- Profil Circle mit Dropdown -->
                <div class="profile-circle <?= empty($user['profile_image']) ? 'initials' : '' ?>" id="profileToggle">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" alt="Profilbild" onerror="this.style.display='none'; this.parentElement.classList.add('initials');">
                    <?php endif; ?>
                    <?php if (empty($user['profile_image'])): ?>
                        <?= strtoupper(substr($user['username'], 0, 2)) ?>
                    <?php endif; ?>
                </div>

                <!-- Dropdown Menu -->
                <div class="dropdown-menu" id="dropdownMenu">
                    <div class="dropdown-header">
                        <div class="profile-circle <?= empty($user['profile_image']) ? 'initials' : '' ?>">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" alt="Profilbild" onerror="this.style.display='none'; this.parentElement.classList.add('initials');">
                            <?php endif; ?>
                            <?php if (empty($user['profile_image'])): ?>
                                <?= strtoupper(substr($user['username'], 0, 2)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info-small">
                            <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                    </div>
                    
                    <a href="edit_profile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        Profil bearbeiten
                    </a>
                    
                    <a href="user-configuration.php" class="dropdown-item">
                        <i class="fas fa-user-plus"></i>
                        Benutzerverwaltung
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <a href="logout.php" class="dropdown-item logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Abmelden
                    </a>
                </div>

                <!-- Overlay für Klick außerhalb -->
                <div class="dropdown-overlay" id="dropdownOverlay"></div>
            </div>
        </div>
    </div>