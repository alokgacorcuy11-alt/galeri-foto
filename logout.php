<?php
// File: logout.php
// Logout: hapus semua session lalu redirect ke login

require_once __DIR__ . '/includes/config.php';

// Hapus token remember-me dari database + cookie
if (isset($_SESSION['user_id'])) {
    $stmt = $koneksi->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
}
setcookie('galeri_remember', '', time() - 3600, '/');

$_SESSION = [];

// Hapus cookie session juga
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']);
}

session_destroy();

header('Location: login.php');
exit;
