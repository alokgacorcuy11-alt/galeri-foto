<?php
// File: users.php
// Halaman admin: kelola pengguna (banned/unban dengan alasan -> dikirim ke Pesan user).

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/notif.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();
requireAdmin();

// Umpan balik dari ban_action.php
$status = $_GET['status'] ?? '';
if ($status === 'ban-sukses')   layout_alert(e($_GET['u'] ?? '') . ' dibanned. Alasan dikirim ke Pesan-nya.', 'warning');
if ($status === 'unban-sukses') layout_alert(e($_GET['u'] ?? '') . ' aktif kembali.', 'success');
if ($status === 'alasan-wajib') layout_alert('Alasan banned belum diisi.', 'danger');
if ($status === 'pin-salah') layout_alert('PIN salah, aksi batal.', 'danger');
if ($status === 'verifikasi-ok') layout_alert(e($_GET['u'] ?? '') . ' terverifikasi.', 'success');
if ($status === 'verifikasi-cabut') layout_alert('Verifikasi ' . e($_GET['u'] ?? '') . ' dicabut.', 'warning');
if ($status === 'ditolak')      layout_alert('Admin tidak bisa dibanned.', 'danger');

$stmt = $koneksi->query(
    'SELECT u.id, u.username, u.nama_lengkap, u.level, u.created_at, u.banned, u.alasan_ban, u.avatar, u.verified, u.last_active,
            COUNT(p.id) AS jml_foto
     FROM users u LEFT JOIN photos p ON p.user_id = u.id
     GROUP BY u.id ORDER BY u.banned DESC, u.created_at DESC'
);
$daftar = $stmt->fetch_all(MYSQLI_ASSOC);

layout_header('Kelola Pengguna');
?>

<main class="main">
<a href="index.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke galeri</a>
<div class="page-head">
    <div>
        <h1 class="page-title">Kelola Pengguna <span class="pill">Admin</span></h1>
        <p class="page-sub">Ban dan pulihkan akun pengguna. Setiap ban wajib ada alasannya, dan alasan itu dikirim ke Pesan pengguna.</p>
    </div>
    <a href="dashboard.php" class="btn btn-ghost"><?= icon('layout', 15) ?> Dashboard</a>
</div>

<section class="dash-card user-list">
    <div class="section-label"><?= icon('user', 14) ?> <?= count($daftar) ?> pengguna terdaftar</div>

    <?php foreach ($daftar as $u): ?>
        <?php $diri = (int)$u['id'] === (int)$_SESSION['user_id']; ?>
        <div class="user-row">
            <?= userAvatar($u['avatar'] ?? null, $u['nama_lengkap'], 'sm') ?>
            <div class="user-info">
                <strong><a class="meta-link" href="profil.php?id=<?= (int)$u['id'] ?>"><?= e($u['nama_lengkap']) ?></a> <?= verifiedBadge((int)$u['verified'] === 1, 13) ?> <?php if ($diri): ?><span class="pill">Anda</span><?php endif; ?></strong>
                <span class="user-meta"><?= e($u['username']) ?> &middot; <?= (int)$u['jml_foto'] ?> foto &middot; bergabung <?= date('d M Y', strtotime($u['created_at'])) ?> &middot; aktif <?= $u['last_active'] ? e(timeAgo($u['last_active'])) : 'belum tercatat' ?></span>
                <?php if ((int)$u['banned'] === 1 && !empty($u['alasan_ban'])): ?>
                    <span class="user-reason"><?= icon('alert', 12) ?> <?= e($u['alasan_ban']) ?></span>
                <?php endif; ?>
            </div>
            <div class="user-actions">
                <?php if ((int)$u['banned'] === 1): ?>
                    <span class="pill pill-danger">Banned</span>
                    <?php if ($u['level'] !== 'Admin'): ?>
                        <form method="post" action="ban_action.php">
                            <input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="aksi" value="unban">
                            <button type="submit" class="btn btn-ghost btn-sm"><?= icon('check', 14) ?> Cabut Banned</button>
                        </form>
                    <?php endif; ?>
                <?php elseif ($u['level'] === 'Admin'): ?>
                    <span class="pill">Admin</span>
                <?php else: ?>
                    <button type="button" class="btn btn-ghost btn-sm danger"
                            data-ban-id="<?= (int)$u['id'] ?>" data-ban-name="<?= e($u['username']) ?>">
                        <?= icon('alert', 14) ?> Banned
                    </button>
                <?php endif; ?>
                <?php if (!$diri && $u['level'] !== 'Admin'): ?>
                    <form method="post" action="verify_action.php" title="<?= (int)$u['verified'] === 1 ? 'Cabut centang biru' : 'Beri centang biru terverifikasi' ?>">
                        <input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="aksi" value="<?= (int)$u['verified'] === 1 ? 'unverify' : 'verify' ?>">
                        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('check', 14) ?> <?= (int)$u['verified'] === 1 ? 'Cabut' : 'Verifikasi' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>
</main>

<dialog id="banDialog" class="glass-dialog">
    <form method="post" action="ban_action.php">
        <input type="hidden" name="aksi" value="ban">
        <input type="hidden" name="target_id" id="banTargetId">
<h2 class="dialog-title">Banned <span id="banTargetName"></span>?</h2>
<p class="dialog-sub">Alasan ini dikirim ke Pesan miliknya dan muncul saat dia coba login.</p>
        <div class="field">
            <label for="banAlasan">Alasan banned</label>
            <textarea id="banAlasan" name="alasan" class="input" rows="3" maxlength="255" required
                      placeholder="Contoh: mengunggah foto yang melanggar aturan galeri"></textarea>
        </div>
        <?php if (actorPinHash() !== null): ?>
            <?= pinWidget('pin', 'PIN keamanan (6 digit)') ?>
        <?php endif; ?>
        <div class="dialog-actions">
            <button type="button" class="btn btn-ghost" id="banCancel">Batal</button>
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('alert', 14) ?> Banned akun</button>
        </div>
    </form>
</dialog>

<script>
// Dialog ban: isi target lalu buka. Tutup dengan Esc / klik luar (native dialog)
(function () {
    var dlg = document.getElementById('banDialog');
    document.querySelectorAll('[data-ban-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('banTargetId').value = btn.getAttribute('data-ban-id');
            document.getElementById('banTargetName').textContent = btn.getAttribute('data-ban-name');
            dlg.showModal();
            setTimeout(function () { document.getElementById('banAlasan').focus(); }, 50);
        });
    });
    document.getElementById('banCancel').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
})();
</script>

<?php layout_footer(); ?>
