<?php
// File: register.php
// Registrasi user baru (level default: User) - gaya glassmorphism

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $password2   = $_POST['password2'] ?? '';
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');

    if ($username === '' || $password === '' || $namaLengkap === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (strlen($username) < 4) {
        $error = 'Username minimal 4 karakter.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $password2) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        $stmt = $koneksi->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Username sudah terdaftar, gunakan yang lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $level = 'User';

            $stmtInsert = $koneksi->prepare('INSERT INTO users (username, password, nama_lengkap, level) VALUES (?, ?, ?, ?)');
            $stmtInsert->bind_param('ssss', $username, $hash, $namaLengkap, $level);

            if ($stmtInsert->execute()) {
                // Sukses -> langsung lempar ke halaman login (username terisi otomatis)
                header('Location: login.php?registered=1&u=' . urlencode($username));
                exit;
            } else {
                $error = 'Registrasi gagal: ' . $koneksi->error;
            }
        }
    }
}

if ($error !== '') {
    layout_alert($error, 'danger');
}

layout_header('Registrasi', auth: true);
?>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="assets/logo.png?v=2" class="auth-logo-img" alt="NoxGallery">
        </div>
        <h1 class="auth-title">Buat akun baru!</h1>
        <p class="auth-sub">Daftar untuk membagikan momen<br>dan bergabung ke galeri</p>

        <form method="post" novalidate>
            <div class="field">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" class="input" required
                       value="<?= e($_POST['nama_lengkap'] ?? '') ?>" placeholder="Nama lengkap Anda">
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="input" required minlength="4" autocomplete="username"
                       value="<?= e($_POST['username'] ?? '') ?>" placeholder="Pilih username">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" class="input" required minlength="6" autocomplete="new-password"
                           placeholder="Minimal 6 karakter">
                    <button type="button" class="pw-toggle" data-target="password" title="Tampilkan password">
                        <span class="ic-show"><?= icon('eye', 17) ?></span>
                        <span class="ic-hide" hidden><?= icon('eye-off', 17) ?></span>
                    </button>
                </div>
            </div>
            <div class="field">
                <label for="password2">Konfirmasi Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password2" name="password2" class="input" required autocomplete="new-password"
                           placeholder="Ulangi password">
                    <button type="button" class="pw-toggle" data-target="password2" title="Tampilkan password">
                        <span class="ic-show"><?= icon('eye', 17) ?></span>
                        <span class="ic-hide" hidden><?= icon('eye-off', 17) ?></span>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-pill btn-lg"><?= icon('user-plus', 16) ?> Daftar</button>
        </form>

        <p class="auth-alt">Sudah punya akun? <a href="login.php">Masuk</a></p>
    </div>
</div>

<?php layout_footer(); ?>
