# Ticket System

Ein einfaches PHP-basiertes Ticketsystem fuer Support-Anfragen mit oeffentlichem Ticketformular, Kundenansicht, Admin-Bereich und E-Mail-Benachrichtigungen.

## Funktionen

- Ticket-Erstellung ueber ein oeffentliches Formular
- Upload von Anhaengen (`JPG`, `PNG`, `PDF`)
- Automatische Vergabe einer Ticketnummer
- Kundenansicht ueber einen eindeutigen Ticket-Link
- Antworten durch Kunden direkt im Ticketverlauf
- Admin-Login mit Session-basierter Authentifizierung
- Dashboard mit Ticket-Uebersicht und Statusverteilung
- Bearbeitung, Statuswechsel und Schliessen von Tickets
- Benutzerverwaltung fuer Admins
- SMTP-Mailversand fuer Benachrichtigungen

## Projektstruktur

```text
/
|-- index.php                  # Oeffentliches Ticketformular
|-- view_ticket.php            # Kundenansicht eines Tickets
|-- datenschutz.php            # Datenschutz-Seite
|-- upload/                    # Hochgeladene Dateien
|-- mailer/
|   |-- send_mail.php          # SMTP-Mailversand
|   `-- PHPMailer/             # Mitgelieferte Bibliothek
`-- admin/
    |-- login.php              # Admin-Login
    |-- dashboard.php          # Ticket-Dashboard
    |-- edit_ticket.php        # Ticket bearbeiten / beantworten
    |-- user-configuration.php # Benutzerverwaltung
    |-- register.php           # Admin anlegen
    |-- config.php             # Datenbankverbindung
    `-- cron_notify_admins.php # Cron-Skript fuer Benachrichtigungen
```

## Voraussetzungen

- PHP 8.x empfohlen
- MySQL oder MariaDB
- XAMPP, LAMP oder vergleichbare PHP-Umgebung
- Aktivierte PHP-Erweiterungen:
  - `pdo`
  - `pdo_mysql`
  - `mbstring`
  - `fileinfo`

## Installation

1. Projekt in das Webroot-Verzeichnis kopieren, z. B. nach `htdocs/ticket_system`.
2. Eine Datenbank in MySQL/MariaDB anlegen.
3. Die Datenbankverbindung in [`admin/config.php`](/C:/xampp/htdocs/ticket_system/admin/config.php) eintragen.
4. SMTP-Zugangsdaten in [`mailer/send_mail.php`](/C:/xampp/htdocs/ticket_system/mailer/send_mail.php) anpassen.
5. Sicherstellen, dass der Ordner `upload/` beschreibbar ist.
6. Optional einen ersten Admin direkt in der Datenbank anlegen oder die vorhandene Registrierungsseite nutzen.

## Konfiguration

### Datenbank

In [`admin/config.php`](/C:/xampp/htdocs/ticket_system/admin/config.php) muessen folgende Werte gesetzt werden:

```php
$host = "localhost";
$dbname = "ticket_system";
$user = "root";
$password = "";
```

### Mailversand

In [`mailer/send_mail.php`](/C:/xampp/htdocs/ticket_system/mailer/send_mail.php) bitte diese Werte anpassen:

```php
$smtpServer   = 'SMTP';
$smtpPort     = 587;
$smtpUser     = 'ticket@domain.tld';
$smtpPassword = '';
$from         = 'ticket@domain.tld';
$nameFrom     = 'Support System';
```

Zusatzlich sollten die im Projekt hart codierten Links auf die eigene Domain angepasst werden, zum Beispiel in:

- [`index.php`](/C:/xampp/htdocs/ticket_system/index.php)
- [`view_ticket.php`](/C:/xampp/htdocs/ticket_system/view_ticket.php)
- [`admin/edit_ticket.php`](/C:/xampp/htdocs/ticket_system/admin/edit_ticket.php)
- [`admin/cron_notify_admins.php`](/C:/xampp/htdocs/ticket_system/admin/cron_notify_admins.php)

## Datenbankstruktur

Die Anwendung erwartet mindestens folgende Tabellen:

### `tickets`

Empfohlene Felder:

- `id`
- `ticket_number`
- `subject`
- `name`
- `email`
- `message`
- `file_path`
- `status`
- `updated_by`
- `closed_by`
- `created_at`
- `updated_at`

### `ticket_updates`

Empfohlene Felder:

- `id`
- `ticket_id`
- `update_text`
- `updated_by`
- `created_at`

### `admins`

Je nach verwendetem Bereich werden unter anderem diese Felder genutzt:

- `id`
- `username`
- `password_hash`
- `email`
- `first_name`
- `last_name`
- `role`
- `status`
- `last_login`
- `last_login_ip`
- `created_at`
- `updated_at`

## Nutzung

### Kunde

1. Ticket ueber [`index.php`](/C:/xampp/htdocs/ticket_system/index.php) erstellen.
2. Ticketnummer und Link aus der E-Mail verwenden.
3. Ticketstatus und Antworten ueber [`view_ticket.php`](/C:/xampp/htdocs/ticket_system/view_ticket.php) verfolgen.

### Admin

1. Ueber [`admin/login.php`](/C:/xampp/htdocs/ticket_system/admin/login.php) anmelden.
2. Tickets im Dashboard einsehen.
3. Tickets bearbeiten, beantworten oder schliessen.
4. Benutzer im Bereich `user-configuration.php` verwalten.

## Cron-Job

Das Skript [`admin/cron_notify_admins.php`](/C:/xampp/htdocs/ticket_system/admin/cron_notify_admins.php) ist fuer periodische Benachrichtigungen vorgesehen.

Beispiel unter Linux:

```bash
* * * * * /usr/bin/php /pfad/zum/projekt/admin/cron_notify_admins.php
```

Beispiel unter Windows Aufgabenplanung:

```powershell
php C:\xampp\htdocs\ticket_system\admin\cron_notify_admins.php
```

## Sicherheitshinweise

- Zugangsdaten niemals im Repository lassen.
- Fuer Produktion sollten Konfigurationswerte in Umgebungsvariablen oder eine nicht versionierte Konfigurationsdatei ausgelagert werden.
- Der `upload/`-Ordner sollte serverseitig zusaetzlich abgesichert werden.
- Dateiuploads sollten in Produktion auf Dateigroesse, Dateiendung und Schadcode geprueft werden.
- Domain-Links und Mailabsender muessen vor dem Live-Betrieb angepasst werden.

## Bekannte Hinweise

- Im Projekt sind noch Platzhalter wie `ticket.domain.tld` vorhanden.
- Die konkrete SQL-Datei fuer das Datenbankschema ist aktuell nicht Bestandteil des Repositories.
- Einige Dateien enthalten bereits feste Layout- und Stildefinitionen direkt in den PHP-Dateien.

## Lizenz

Aktuell ist keine Lizenzdatei im Repository enthalten. Fuer eine Veroeffentlichung auf GitHub empfiehlt sich eine passende `LICENSE`.
