<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Set session timeout to 2 hours (7200 seconds)
$session_timeout = 7200;

// Check if session has expired
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Session has expired, destroy it
    session_unset();
    session_destroy();
    setcookie('user_id', '', time() - 3600, "/");
    header('Location: login.php?expired=1');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?> 