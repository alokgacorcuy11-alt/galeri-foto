<?php
// File: pengaturan.php
// Menu Pengaturan: edit profil, keamanan (ganti password + keluar semua perangkat),
// info akun, dan hapus akun sendiri (zona berbahaya, admin dikecualikan).

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();

// Semua aksi memakai pola PRG agar refresh tidak mengulang (seperti users.php/pesan.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $uid = (int)$_SESSION['user_id'];

    if ($aksi === 'profil') {
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        if ($nama === '') {
            header('Location: pengaturan.php?status=nama-kosong');
        } elseif (strlen($username) < 4) {
            header('Location: pengaturan.php?status=username-pendek');
        } else {
            $stmt = $koneksi->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
            $stmt->bind_param('si', $username, $uid);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                header('Location: pengaturan.php?status=username-dipakai');
            } else {
                // Foto profil opsional: validasi sama seperti upload foto (tambah.php)
                $avatarBaru = null;
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                        header('Location: pengaturan.php?status=avatar-gagal');
                        exit;
                    } elseif ($_FILES['avatar']['size'] > MAX_FILE_SIZE) {
                        header('Location: pengaturan.php?status=avatar-besar');
                        exit;
                    } else {
                        $extA = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                        if (!in_array($extA, ALLOWED_EXT, true) || !@getimagesize($_FILES['avatar']['tmp_name'])) {
                            header('Location: pengaturan.php?status=avatar-tipe');
                            exit;
                        }
                        $avatarBaru = 'avatar_' . $uid . '_' . time() . '.' . $extA;
                        if (!@move_uploaded_file($_FILES['avatar']['tmp_name'], AVATAR_DIR . $avatarBaru)) {
                            header('Location: pengaturan.php?status=avatar-gagal');
                            exit;
                        }
                    }
                }
                // Ambil avatar lama untuk dihapus setelah update sukses
                $stmtL = $koneksi->prepare('SELECT avatar FROM users WHERE id = ?');
                $stmtL->bind_param('i', $uid);
                $stmtL->execute();
                $avatarLama = $stmtL->get_result()->fetch_assoc()['avatar'] ?? null;
$namaDb = mb_substr($nama, 0, 100);
$bioDb = mb_substr(trim($_POST['bio'] ?? ''), 0, 160);
if ($avatarBaru !== null) {
$stmtU = $koneksi->prepare('UPDATE users SET nama_lengkap = ?, username = ?, bio = ?, avatar = ? WHERE id = ?');
$stmtU->bind_param('ssssi', $namaDb, $username, $bioDb, $avatarBaru, $uid);
} else {
$stmtU = $koneksi->prepare('UPDATE users SET nama_lengkap = ?, username = ?, bio = ? WHERE id = ?');
$stmtU->bind_param('sssi', $namaDb, $username, $bioDb, $uid);
}
                $stmtU->execute();
                if ($avatarBaru !== null) {
                    if ($avatarLama && is_file(AVATAR_DIR . basename($avatarLama))) {
                        unlink(AVATAR_DIR . basename($avatarLama));
                    }
                    $_SESSION['avatar'] = $avatarBaru;
                }
                $_SESSION['nama'] = $namaDb;
                $_SESSION['username'] = $username;
                header('Location: pengaturan.php?status=profil-ok');
            }
        }
        exit;
    }

    if ($aksi === 'password') {
        $lama = $_POST['pw_lama'] ?? '';
        $baru = $_POST['pw_baru'] ?? '';
        $baru2 = $_POST['pw_baru2'] ?? '';
        $stmt = $koneksi->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $hashLama = $stmt->get_result()->fetch_assoc()['password'] ?? '';
        if (!password_verify($lama, $hashLama)) {
            header('Location: pengaturan.php?status=pw-salah');
        } elseif (strlen($baru) < 6) {
            header('Location: pengaturan.php?status=pw-pendek');
        } elseif ($baru !== $baru2) {
            header('Location: pengaturan.php?status=pw-beda');
        } else {
            $stmtU = $koneksi->prepare('UPDATE users SET password = ? WHERE id = ?');
            $hashBaru = password_hash($baru, PASSWORD_DEFAULT);
            $stmtU->bind_param('si', $hashBaru, $uid);
            $stmtU->execute();
            header('Location: pengaturan.php?status=pw-ok');
        }
        exit;
    }

    if ($aksi === 'devices') {
        $stmt = $koneksi->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        setcookie('galeri_remember', '', ['expires' => time() - 3600, 'path' => '/']);
        header('Location: pengaturan.php?status=devices-ok');
        exit;
    }

    // PIN keamanan 6 digit ala dompet digital: format dicek server (JS hanya membantu).
    $pinFormatOk = function (string $pin): bool {
        return (bool)preg_match('/^\d{6}$/', $pin);
    };

    if ($aksi === 'pin-set') {
        $baru = $_POST['pin_baru'] ?? '';
        $baru2 = $_POST['pin_baru2'] ?? '';
        if (actorPinHash() !== null) {
            header('Location: pengaturan.php?status=pin-salah');
        } elseif (!$pinFormatOk($baru) || !$pinFormatOk($baru2)) {
            header('Location: pengaturan.php?status=pin-format');
        } elseif ($baru !== $baru2) {
            header('Location: pengaturan.php?status=pin-beda');
        } else {
            $stmtU = $koneksi->prepare('UPDATE users SET pin_hash = ? WHERE id = ?');
            $hashPin = password_hash($baru, PASSWORD_DEFAULT);
            $stmtU->bind_param('si', $hashPin, $uid);
            $stmtU->execute();
            header('Location: pengaturan.php?status=pin-ok');
        }
        exit;
    }

    if ($aksi === 'pin-ubah') {
        $lama = $_POST['pin_lama'] ?? '';
        $baru = $_POST['pin_baru'] ?? '';
        $baru2 = $_POST['pin_baru2'] ?? '';
        if (!pinValid(actorPinHash(), $lama)) {
            header('Location: pengaturan.php?status=pin-salah');
        } elseif (!$pinFormatOk($baru) || !$pinFormatOk($baru2)) {
            header('Location: pengaturan.php?status=pin-format');
        } elseif ($baru !== $baru2) {
            header('Location: pengaturan.php?status=pin-beda');
        } else {
            $stmtU = $koneksi->prepare('UPDATE users SET pin_hash = ? WHERE id = ?');
            $hashPin = password_hash($baru, PASSWORD_DEFAULT);
            $stmtU->bind_param('si', $hashPin, $uid);
            $stmtU->execute();
            header('Location: pengaturan.php?status=pin-ubah-ok');
        }
        exit;
    }

    if ($aksi === 'pin-mati') {
        if (!pinValid(actorPinHash(), $_POST['pin_mati'] ?? '')) {
            header('Location: pengaturan.php?status=pin-salah');
        } else {
            $stmtU = $koneksi->prepare('UPDATE users SET pin_hash = NULL WHERE id = ?');
            $stmtU->bind_param('i', $uid);
            $stmtU->execute();
            header('Location: pengaturan.php?status=pin-mati-ok');
        }
        exit;
    }

    if ($aksi === 'hapus-akun') {
        // Admin tidak boleh menghapus akunnya sendiri (anti lockout: galeri tanpa admin)
        if (isAdmin()) {
            header('Location: pengaturan.php?status=hapus-admin');
            exit;
        }
        $stmt = $koneksi->prepare('SELECT password, avatar FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $rowHapus = $stmt->get_result()->fetch_assoc();
        // Konfirmasi ala dompet digital: PIN bila sudah pasang, password bila belum
        $pinHash = actorPinHash();
        $bolehHapus = $pinHash !== null
            ? pinValid($pinHash, $_POST['pin_konfirmasi'] ?? '')
            : password_verify($_POST['pw_konfirmasi'] ?? '', $rowHapus['password'] ?? '');
        if (!$bolehHapus) {
            header('Location: pengaturan.php?status=hapus-salah');
            exit;
        }
        // Hapus: catat dulu nama file fisik, karena baris foto ikut CASCADE tapi file tidak
        $stmtF = $koneksi->prepare('SELECT filename FROM photos WHERE user_id = ?');
        $stmtF->bind_param('i', $uid);
        $stmtF->execute();
        $files = $stmtF->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtD = $koneksi->prepare('DELETE FROM users WHERE id = ?');
        $stmtD->bind_param('i', $uid);
        $stmtD->execute();
        if (!empty($rowHapus['avatar']) && is_file(AVATAR_DIR . basename($rowHapus['avatar']))) {
            unlink(AVATAR_DIR . basename($rowHapus['avatar']));
        }
        foreach ($files as $f) {
            if (is_file(UPLOAD_DIR . $f['filename'])) {
                unlink(UPLOAD_DIR . $f['filename']);
            }
        }
        session_unset();
        session_destroy();
        setcookie('galeri_remember', '', ['expires' => time() - 3600, 'path' => '/']);
        header('Location: login.php?status=akun-dihapus');
        exit;
    }

    header('Location: pengaturan.php');
    exit;
}

$status = $_GET['status'] ?? '';
$pesanStatus = [
    'profil-ok' => ['Profil disimpan.', 'success'],
    'nama-kosong' => ['Nama lengkap wajib diisi.', 'danger'],
    'username-pendek' => ['Username minimal 4 karakter.', 'danger'],
    'username-dipakai' => ['Username sudah dipakai, pilih yang lain.', 'danger'],
    'pw-ok' => ['Password diganti.', 'success'],
    'pw-salah' => ['Password lama salah.', 'danger'],
    'pw-pendek' => ['Password baru minimal 6 karakter.', 'danger'],
    'pw-beda' => ['Konfirmasi password baru tidak sama.', 'danger'],
    'devices-ok' => ['Semua perangkat lain dikeluarkan.', 'success'],
    'pin-ok' => ['PIN keamanan aktif.', 'success'],
    'pin-ubah-ok' => ['PIN diganti.', 'success'],
    'pin-mati-ok' => ['PIN dimatikan.', 'success'],
    'pin-salah' => ['PIN lama salah.', 'danger'],
    'pin-beda' => ['Konfirmasi PIN tidak sama.', 'danger'],
    'pin-format' => ['PIN harus 6 digit angka.', 'danger'],
    'avatar-besar' => ['Foto profil melebihi 5 MB.', 'danger'],
    'avatar-tipe' => ['Foto profil harus JPG, PNG, GIF, atau WEBP.', 'danger'],
    'avatar-gagal' => ['Foto profil gagal disimpan, coba lagi.', 'danger'],
    'hapus-admin' => ['Akun admin tidak bisa dihapus sendiri.', 'danger'],
    'hapus-salah' => ['PIN atau password salah, akun batal dihapus.', 'danger'],
];
if (isset($pesanStatus[$status])) {
    layout_alert($pesanStatus[$status][0], $pesanStatus[$status][1]);
}

// Data akun aktif + hitungan kontennya
$uid = (int)$_SESSION['user_id'];
$stmt = $koneksi->prepare('SELECT username, nama_lengkap, level, created_at, avatar, bio FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$saya = $stmt->get_result()->fetch_assoc();
$stmtF = $koneksi->prepare('SELECT COUNT(*) AS total FROM photos WHERE user_id = ?');
$stmtF->bind_param('i', $uid);
$stmtF->execute();
$jmlFotoSaya = (int)$stmtF->get_result()->fetch_assoc()['total'];
$pinSaya = actorPinHash();

layout_header('Pengaturan');
?>

<main class="main">
<a href="index.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke galeri</a>
<div class="page-head">
    <div>
        <h1 class="page-title">Pengaturan</h1>
        <p class="page-sub">Profil, keamanan akun, dan info akun Anda.</p>
    </div>
</div>

<!-- Profil -->
<section class="dash-card">
    <div class="section-label"><?= icon('user', 14) ?> Profil</div>
    <div class="set-profile">
        <?= userAvatar($saya['avatar'] ?? null, $saya['nama_lengkap']) ?>
        <form method="post" enctype="multipart/form-data" class="set-form">
            <input type="hidden" name="aksi" value="profil">
            <div class="field">
                <label for="setNama">Nama lengkap</label>
                <input type="text" id="setNama" name="nama_lengkap" class="input" required maxlength="100"
                       value="<?= e($saya['nama_lengkap']) ?>" autocomplete="name">
            </div>
            <div class="field">
                <label for="setUsername">Username</label>
                <input type="text" id="setUsername" name="username" class="input" required minlength="4" maxlength="50"
                       value="<?= e($saya['username']) ?>" autocomplete="username">
            </div>
            <div class="field">
                <label for="setBio">Bio (maks 160 karakter)</label>
                <textarea id="setBio" name="bio" class="input" rows="2" maxlength="160"
                          placeholder="Ceritakan sedikit tentang Anda..."><?= e($saya['bio'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label>Foto profil</label>
                <label class="file-drop file-drop-sm" id="avatarDrop">
                    <?= icon('camera', 20) ?>
                    <span class="file-drop-title" id="avatarName">Klik atau seret foto ke sini</span>
                    <input type="file" name="avatar" id="avatarInput" accept=".jpg,.jpeg,.png,.gif,.webp">
                </label>
                <div class="hint">JPG, PNG, GIF, WEBP. Maksimal 5 MB. Kosongkan jika tidak ganti foto.</div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Simpan profil</button>
        </form>
    </div>
</section>

<!-- Keamanan -->
<section class="dash-card">
    <div class="section-label"><?= icon('eye-off', 14) ?> Keamanan</div>
    <form method="post" class="set-form">
        <input type="hidden" name="aksi" value="password">
        <div class="field">
            <label for="pwLama">Password lama</label>
            <div class="pw-wrap">
                <input type="password" id="pwLama" name="pw_lama" class="input" required autocomplete="current-password"
                       placeholder="Masukkan password lama">
                <button type="button" class="pw-toggle" data-target="pwLama" title="Tampilkan password">
                    <span class="ic-show"><?= icon('eye', 17) ?></span>
                    <span class="ic-hide" hidden><?= icon('eye-off', 17) ?></span>
                </button>
            </div>
        </div>
        <div class="field">
            <label for="pwBaru">Password baru</label>
            <div class="pw-wrap">
                <input type="password" id="pwBaru" name="pw_baru" class="input" required minlength="6"
                       autocomplete="new-password" placeholder="Minimal 6 karakter">
                <button type="button" class="pw-toggle" data-target="pwBaru" title="Tampilkan password">
                    <span class="ic-show"><?= icon('eye', 17) ?></span>
                    <span class="ic-hide" hidden><?= icon('eye-off', 17) ?></span>
                </button>
            </div>
        </div>
        <div class="field">
            <label for="pwBaru2">Ulangi password baru</label>
            <div class="pw-wrap">
                <input type="password" id="pwBaru2" name="pw_baru2" class="input" required
                       autocomplete="new-password" placeholder="Sama dengan password baru">
                <button type="button" class="pw-toggle" data-target="pwBaru2" title="Tampilkan password">
                    <span class="ic-show"><?= icon('eye', 17) ?></span>
                    <span class="ic-hide" hidden><?= icon('eye-off', 17) ?></span>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Ganti password</button>
    </form>
    <form method="post" class="set-devices">
        <input type="hidden" name="aksi" value="devices">
        <div class="user-row">
            <div class="user-info">
                <strong>Perangkat lain</strong>
                <span class="user-meta">Keluarkan sesi "Ingat saya" di HP atau laptop lain. Sesi ini tetap masuk.</span>
            </div>
            <div class="user-actions">
                <button type="submit" class="btn btn-ghost btn-sm"><?= icon('logout', 14) ?> Keluarkan semua</button>
            </div>
        </div>
    </form>
</section>

<!-- PIN keamanan -->
<section class="dash-card">
    <div class="section-label"><?= icon('eye', 14) ?> PIN Keamanan <?php if ($pinSaya !== null): ?><span class="pill pill-ok">Aktif</span><?php endif; ?></div>
    <p class="dialog-sub">Kode 6 digit ala dompet digital untuk mengonfirmasi aksi penting (hapus akun, hapus foto, banned).</p>
    <?php if ($pinSaya === null): ?>
        <form method="post" class="set-form">
            <input type="hidden" name="aksi" value="pin-set">
            <?= pinWidget('pin_baru', 'PIN baru (6 digit)') ?>
            <?= pinWidget('pin_baru2', 'Ulangi PIN baru') ?>
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Aktifkan PIN</button>
        </form>
    <?php else: ?>
        <form method="post" class="set-form">
            <input type="hidden" name="aksi" value="pin-ubah">
            <?= pinWidget('pin_lama', 'PIN lama') ?>
            <?= pinWidget('pin_baru', 'PIN baru (6 digit)') ?>
            <?= pinWidget('pin_baru2', 'Ulangi PIN baru') ?>
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Ganti PIN</button>
        </form>
        <form method="post" class="set-devices">
            <input type="hidden" name="aksi" value="pin-mati">
            <div class="user-row">
                <div class="user-info">
                    <strong>Matikan PIN</strong>
                    <span class="user-meta">Aksi penting kembali dikonfirmasi dengan password.</span>
                </div>
                <div class="user-actions">
                    <button type="button" class="btn btn-ghost btn-sm" id="pinOffOpen"><?= icon('x', 14) ?> Matikan</button>
                </div>
            </div>
            <dialog id="pinOffDialog" class="glass-dialog">
                <?= pinWidget('pin_mati', 'PIN saat ini') ?>
                <div class="dialog-actions">
                    <button type="button" class="btn btn-ghost" id="pinOffCancel">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Matikan PIN</button>
                </div>
            </dialog>
        </form>
    <?php endif; ?>
</section>

<!-- Info akun -->
<section class="dash-card">
    <div class="section-label"><?= icon('image', 14) ?> Akun saya</div>
    <div class="set-info">
        <span>Username</span><strong><?= e($saya['username']) ?></strong>
        <span>Level</span><span class="pill"><?= e($saya['level']) ?></span>
        <span>Bergabung</span><strong><?= date('d M Y', strtotime($saya['created_at'])) ?></strong>
        <span>Foto diunggah</span><strong><?= $jmlFotoSaya ?> foto</strong>
    </div>
    <?php if (!isAdmin()): ?>
        <div class="set-danger">
            <div class="user-row">
                <div class="user-info">
                    <strong>Hapus akun permanen</strong>
                    <span class="user-meta">Foto, like, dan komentar Anda ikut terhapus dan tidak bisa kembali.</span>
                </div>
                <div class="user-actions">
                    <button type="button" class="btn btn-ghost btn-sm danger" id="delAccOpen"><?= icon('trash', 14) ?> Hapus akun</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
</main>

<dialog id="delAccDialog" class="glass-dialog">
    <form method="post" action="pengaturan.php">
        <input type="hidden" name="aksi" value="hapus-akun">
        <h2 class="dialog-title">Hapus akun Anda selamanya?</h2>
        <p class="dialog-sub">Semua foto, like, dan komentar Anda ikut hilang. <?= actorPinHash() !== null ? 'Masukkan PIN 6 digit untuk memastikan ini benar Anda.' : 'Ketik password untuk memastikan ini benar Anda.' ?></p>
        <?php if (actorPinHash() !== null): ?>
            <?= pinWidget('pin_konfirmasi', 'PIN keamanan (6 digit)') ?>
        <?php else: ?>
        <div class="field">
            <label for="pwKonfirmasi">Password Anda</label>
            <input type="password" id="pwKonfirmasi" name="pw_konfirmasi" class="input" required autocomplete="current-password"
                   placeholder="Masukkan password">
        </div>
        <?php endif; ?>
        <div class="dialog-actions">
            <button type="button" class="btn btn-ghost" id="delAccCancel">Batal</button>
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('trash', 14) ?> Hapus permanen</button>
        </div>
    </form>
</dialog>

<script>
// Nama file avatar yang dipilih + status drag (pola yang sama seperti tambah.php)
(function () {
    var input = document.getElementById('avatarInput');
    var label = document.getElementById('avatarName');
    var drop = document.getElementById('avatarDrop');
    if (!input || !label || !drop) return;
    input.addEventListener('change', function () {
        label.textContent = input.files.length ? input.files[0].name : 'Klik atau seret foto ke sini';
    });
    ['dragover', 'dragleave', 'drop'].forEach(function (evt) {
        drop.addEventListener(evt, function (e) {
            e.preventDefault();
            drop.classList.toggle('drag', evt === 'dragover');
        });
    });
})();
</script>

<script>
// Dialog hapus akun: pola dialog yang sama seperti ban/hapus foto (Esc + klik luar menutup)
(function () {
    var open = document.getElementById('delAccOpen');
    var dlg = document.getElementById('delAccDialog');
    if (!open || !dlg) return;
    open.addEventListener('click', function () {
        dlg.showModal();
        setTimeout(function () {
            var first = dlg.querySelector('.pin-boxes input') || document.getElementById('pwKonfirmasi');
            if (first) first.focus();
        }, 50);
    });
    document.getElementById('delAccCancel').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
})();

// Dialog matikan PIN: pola yang sama (Esc + klik luar menutup)
(function () {
    var open = document.getElementById('pinOffOpen');
    var dlg = document.getElementById('pinOffDialog');
    if (!open || !dlg) return;
    open.addEventListener('click', function () { dlg.showModal(); });
    document.getElementById('pinOffCancel').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
})();

// Nama file avatar yang dipilih + status drag (pola yang sama seperti tambah.php)
(function () {
    var input = document.getElementById('avatarInput');
    var label = document.getElementById('avatarName');
    var drop = document.getElementById('avatarDrop');
    if (!input || !label || !drop) return;
    input.addEventListener('change', function () {
        label.textContent = input.files.length ? input.files[0].name : 'Klik atau seret foto ke sini';
    });
    ['dragover', 'dragleave', 'drop'].forEach(function (evt) {
        drop.addEventListener(evt, function (e) {
            e.preventDefault();
            drop.classList.toggle('drag', evt === 'dragover');
        });
    });
})();
</script>

<?php layout_footer(); ?>
