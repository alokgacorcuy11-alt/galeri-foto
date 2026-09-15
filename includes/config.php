<?php
// File: includes/config.php
// Konfigurasi koneksi database MySQL (client-server)

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_galeri_foto');

// Konfigurasi upload
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('AVATAR_DIR', __DIR__ . '/../uploads/avatars/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Membuat koneksi mysqli
$koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($koneksi->connect_error) {
    die('Koneksi database gagal: ' . $koneksi->connect_error);
}

$koneksi->set_charset('utf8mb4');

// Mulai session jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-login via cookie "Remember me"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['galeri_remember'])) {
    $parts = explode(':', $_COOKIE['galeri_remember'], 2);
    if (count($parts) === 2 && ctype_digit($parts[0]) && strlen($parts[1]) === 64) {
        $stmt = $koneksi->prepare('SELECT id, username, nama_lengkap, level, avatar, verified FROM users WHERE id = ? AND remember_token = ? AND banned = 0');
        $hash = hash('sha256', $parts[1]);
        $stmt->bind_param('is', $parts[0], $hash);
        $stmt->execute();
        $remembered = $stmt->get_result()->fetch_assoc();
        if ($remembered) {
            $_SESSION['user_id']   = $remembered['id'];
            $_SESSION['username'] = $remembered['username'];
            $_SESSION['nama']      = $remembered['nama_lengkap'];
            $_SESSION['level']     = $remembered['level'];
            $_SESSION['avatar']    = $remembered['avatar'];
            $_SESSION['verified']  = (int)$remembered['verified'] === 1;
        }
    }
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn()
{
return isset($_SESSION['user_id']);
}

// Zona waktu aplikasi (WIB): php.ini mesin boleh apa saja, tampilan tanggal
// dan hitungan waktu relatif harus konsisten dengan jam database.
date_default_timezone_set('Asia/Jakarta');

// Batas idle sesi 30 menit (sliding): aktivitas terakhir dicatat di DB setiap request
// agar tegas walau cookie ingat-saya masih ada. Kadaluwarsa = keluar total + token dicabut.
define('BATAS_IDLE_DETIK', 30 * 60);

// Guard sesi: banned ditendang + alasan; idle 30 menit ditendang + token dicabut;
// selain itu catat waktu aktivitas sekarang.
if (isset($_SESSION['user_id'])) {
$batasIdle = BATAS_IDLE_DETIK;
// Perbandingan kadaluwarsa dihitung di SQL (satu sumber jam: database) agar kebal
// beda zona waktu php.ini antar mesin.
$stmtB = $koneksi->prepare('SELECT banned, alasan_ban, last_active, (last_active IS NOT NULL AND last_active < DATE_SUB(NOW(), INTERVAL ? SECOND)) AS idle_habis FROM users WHERE id = ?');
$stmtB->bind_param('ii', $batasIdle, $_SESSION['user_id']);
$stmtB->execute();
$sesiState = $stmtB->get_result()->fetch_assoc();
if ($sesiState && (int)$sesiState['banned'] === 1) {
$_SESSION['flash_banned'] = $sesiState['alasan_ban'] ?: 'Tanpa alasan dari admin.';
unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['nama'], $_SESSION['level'], $_SESSION['avatar']);
setcookie('galeri_remember', '', ['expires' => time() - 3600, 'path' => '/']);
if (!str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'login.php')) {
header('Location: login.php');
exit;
}
} elseif ($sesiState && (int)$sesiState['idle_habis'] === 1) {
$stmtM = $koneksi->prepare('UPDATE users SET remember_token = NULL, last_active = NULL WHERE id = ?');
$stmtM->bind_param('i', $_SESSION['user_id']);
$stmtM->execute();
unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['nama'], $_SESSION['level'], $_SESSION['avatar']);
setcookie('galeri_remember', '', ['expires' => time() - 3600, 'path' => '/']);
if (!str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'login.php')) {
header('Location: login.php?status=sesi-habis');
exit;
}
} else {
$stmtA = $koneksi->prepare('UPDATE users SET last_active = NOW() WHERE id = ?');
$stmtA->bind_param('i', $_SESSION['user_id']);
$stmtA->execute();
}
}

/**
 * Cek apakah user adalah Admin
 */
function isAdmin()
{
    return isset($_SESSION['level']) && $_SESSION['level'] === 'Admin';
}

/**
 * Redirect jika belum login
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Redirect jika bukan admin
 */
function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: index.php?error=akses-ditolak');
        exit;
    }
}

/**
 * Sanitasi output untuk mencegah XSS
 */
function e($str)
{
return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Hash PIN milik akun aktif (null jika belum pasang PIN).
 */
function actorPinHash(): ?string
{
global $koneksi;
$stmt = $koneksi->prepare('SELECT pin_hash FROM users WHERE id = ?');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$h = $stmt->get_result()->fetch_assoc()['pin_hash'] ?? null;
return ($h === null || $h === '') ? null : $h;
}

/**
 * PIN valid hanya jika 6 digit angka dan cocok dengan hash.
 */
function pinValid(?string $hash, string $pin): bool
{
return $hash !== null && $hash !== '' && preg_match('/^\d{6}$/', $pin) && password_verify($pin, $hash);
}
