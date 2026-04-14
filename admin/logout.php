<?php
// logout.php

// 1. Session starten
session_start();

// 2. Alle Session-Daten löschen
$_SESSION = []; // Leere alle Session-Variablen

// 3. Session-Cookie löschen (falls vorhanden)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 4. Session zerstören
session_destroy();

// 5. Weiterleitung zur Login-Seite
header("Location: login.php");
exit();