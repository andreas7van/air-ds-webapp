<?php
session_start();

// Clear session data, cookie, and the session itself.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();

header('Location: home.php', true, 302);
exit;
