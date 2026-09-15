<?php
// File: login.php
// Login user (Admin/User) - gaya glassmorphism

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Alasan ban dari guard config.php (user ditendang saat sesi aktif)
$bannedNotice = '';
if (isset($_SESSION['flash_banned'])) {
$bannedNotice = (string)$_SESSION['flash_banned'];
unset($_SESSION['flash_banned']);
}

// Notifikasi setelah registrasi berhasil
$registeredUser = isset($_GET['registered']) ? ($_GET['u'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

if ($username === '' || $password === '') {
$error = 'Username dan password wajib diisi.';
} else {
        $stmt = $koneksi->prepare('SELECT id, username, password, nama_lengkap, level, banned, alasan_ban, avatar, verified FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
if ((int)$user['banned'] === 1) {
// Akun banned: banner alasan di kartu sudah cukup, tanpa toast duplikat
$bannedNotice = $user['alasan_ban'] ?: 'Tanpa alasan dari admin.';
} else {
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['nama'] = $user['nama_lengkap'];
$_SESSION['level'] = $user['level'];
$_SESSION['avatar'] = $user['avatar'];
$_SESSION['verified'] = (int)$user['verified'] === 1;

// Remember me: simpan token acak (hashed) 30 hari
if ($remember) {
$token = bin2hex(random_bytes(32));
$hash = hash('sha256', $token);
$stmtT = $koneksi->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
$stmtT->bind_param('si', $hash, $user['id']);
$stmtT->execute();
setcookie('galeri_remember', $user['id'] . ':' . $token, [
'expires' => time() + 30 * 86400,
'path' => '/',
'httponly' => true,
'samesite' => 'Lax',
]);
}

header('Location: index.php');
exit;
}
} else {
$error = 'Username atau password salah.';
}
}
}

if ($error !== '') {
layout_alert($error, 'danger');
} elseif ($registeredUser !== '') {
layout_alert('Registrasi berhasil! Silakan login dengan akun Anda.', 'success');
} elseif (($_GET['status'] ?? '') === 'akun-dihapus') {
layout_alert('Akun Anda sudah dihapus. Sampai jumpa lagi.', 'success');
} elseif (($_GET['status'] ?? '') === 'sesi-habis') {
layout_alert('Sesi berakhir karena 30 menit tidak aktif. Silakan masuk lagi.', 'warning');
}

layout_header('Login', auth: true);
?>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="assets/logo.png?v=2" class="auth-logo-img" alt="NoxGallery">
        </div>
<h1 class="auth-title">Selamat datang kembali!</h1>
<p class="auth-sub">Masuk untuk membuka galeri foto<br>dan dashboard pribadi Anda</p>

<?php if ($bannedNotice !== ''): ?>
<div class="auth-banned" role="alert">
<?= icon('alert', 16) ?>
<div>
<strong>Akun Anda dibanned.</strong><br>
Alasan: <?= e($bannedNotice) ?><br>
Bila Anda merasa ini salah, hubungi admin.
</div>
</div>
<?php endif; ?>

        <form method="post" novalidate>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="input" required autocomplete="username"
                       value="<?= e($_POST['username'] ?? $registeredUser) ?>" placeholder="Masukkan username">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" class="input" required autocomplete="current-password"
                           placeholder="Masukkan password">
                    <button type="button" class="pw-toggle" data-target="password" title="Tampilkan password">
                        <span class="ic-show"><?= icon('eye', 17) ?></span>
                        <span class="ic-hide" hidden><?= icon('eye-off', 17) ?></span>
                    </button>
                </div>
            </div>
            <div class="auth-row">
                <label class="remember">
                    <input type="checkbox" name="remember" value="1"> Ingat saya
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-pill btn-lg">Masuk</button>
            <div class="divider"><span>atau</span></div>
            <button type="button" class="btn btn-glass btn-block btn-pill" id="demoBtn">
                <?= icon('spark', 16) ?> Coba akun demo
            </button>
        </form>

        <p class="auth-alt">Belum punya akun? <a href="register.php">Daftar</a></p>
    </div>
</div>

<script>
    // Isi otomatis kredensial demo (user / user123)
    document.getElementById('demoBtn').addEventListener('click', function() {
        document.getElementById('username').value = 'user';
        document.getElementById('password').value = 'user123';
        document.getElementById('password').focus();
    });
</script>

<?php layout_footer(); ?>
