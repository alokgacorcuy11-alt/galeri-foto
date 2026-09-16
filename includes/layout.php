<?php
// File: includes/layout.php
// Template bersama: <head>, navbar, footer. Mencegah duplikasi markup.

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/engagement.php';

/**
 * Output pembuka HTML + navbar.
 * $title  : isi <title>
 * $auth   : true jika halaman auth (login/register) -> tanpa navbar
 */
function layout_header(string $title, bool $auth = false): void
{
    $bodyClass = $auth ? ' class="auth-body"' : '';
    ?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> Â· NoxGallery</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.png?v=2">
    <link rel="stylesheet" href="assets/style.css?v=50">
</head>
<body<?= $bodyClass ?>>
<?php if (!$auth): ?>
<?php $halaman = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
<?php require_once __DIR__ . '/notif.php'; $notifBaru = unreadNotifCount(); ?>
<nav class="nav">
    <div class="container nav-inner">
        <div class="nav-left">
            <a href="index.php" class="brand">
                <img src="assets/logo.png?v=2" class="brand-logo" alt="NoxGallery">
                <span class="brand-name">Nox<em>Gallery</em></span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link <?= in_array($halaman, ['index.php', 'detail.php'], true) ? 'active' : '' ?>"><?= icon('image', 15) ?> <span>Galeri</span></a>
                <a href="album.php" class="nav-link <?= in_array($halaman, ['album.php', 'album_detail.php', 'album_edit.php'], true) ? 'active' : '' ?>"><?= icon('folder', 15) ?> <span>Album</span></a>
<?php if (isset($_SESSION['level'])): ?>
<a href="dashboard.php" class="nav-link <?= $halaman === 'dashboard.php' ? 'active' : '' ?>"><?= icon('layout', 15) ?> <span>Dashboard</span></a>
<?php endif; ?>
<a href="pesan.php" class="nav-link <?= $halaman === 'pesan.php' ? 'active' : '' ?>"><?= icon('message', 15) ?> <span>Pesan</span><?php if (!empty($notifBaru)): ?><span class="nav-badge"><?= (int)$notifBaru ?></span><?php endif; ?></a>
<?php if (isAdmin()): ?>
<a href="users.php" class="nav-link <?= $halaman === 'users.php' ? 'active' : '' ?>"><?= icon('user', 15) ?> <span>Pengguna</span></a>
<?php endif; ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="profil.php" class="chip" title="Profil saya">
                <?= userAvatar($_SESSION['avatar'] ?? null, $_SESSION['nama'] ?? '', 'xs') ?>
                <span class="chip-name"><?= e($_SESSION['nama'] ?? '') ?></span> <?= verifiedBadge(!empty($_SESSION['verified']), 12) ?>
                <?php if (isset($_SESSION['level'])): ?>
                    <span class="chip-level <?= strtolower(e($_SESSION['level'])) ?>"><?= e($_SESSION['level']) ?></span>
                <?php endif; ?>
            </a>
            <a href="tambah.php" class="btn btn-primary btn-sm"><?= icon('plus', 15) ?> Tambah Foto</a>
            <?php if (isset($_SESSION['level'])): ?>
            <a href="dashboard.php" class="btn-icon only-mobile <?= $halaman === 'dashboard.php' ? 'active' : '' ?>" title="Dashboard" aria-label="Dashboard"><?= icon('layout', 16) ?></a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a href="users.php" class="btn-icon only-mobile <?= $halaman === 'users.php' ? 'active' : '' ?>" title="Kelola Pengguna" aria-label="Kelola Pengguna"><?= icon('user', 16) ?></a>
            <?php endif; ?>
            <a href="pengaturan.php" class="btn-icon <?= $halaman === 'pengaturan.php' ? 'active' : '' ?>" title="Pengaturan" aria-label="Pengaturan"><?= icon('sliders', 16) ?></a>
            <a href="logout.php" class="btn-icon" title="Logout" aria-label="Logout"><?= icon('logout', 16) ?></a>
        </div>
    </div>
</nav>
<nav class="bnav" aria-label="Navigasi utama">
    <div class="bnav-inner">
        <a href="index.php" class="bnav-item <?= in_array($halaman, ['index.php', 'detail.php'], true) ? 'active' : '' ?>"><?= icon('image', 22) ?><span>Galeri</span></a>
        <a href="album.php" class="bnav-item <?= in_array($halaman, ['album.php', 'album_detail.php', 'album_edit.php'], true) ? 'active' : '' ?>"><?= icon('folder', 22) ?><span>Album</span></a>
        <a href="tambah.php" class="bnav-item bnav-create <?= $halaman === 'tambah.php' ? 'active' : '' ?>" aria-label="Tambah foto"><span class="bnav-plus"><?= icon('plus', 20) ?></span></a>
        <a href="pesan.php" class="bnav-item <?= $halaman === 'pesan.php' ? 'active' : '' ?>"><span class="bnav-icwrap"><?= icon('message', 22) ?><?php if (!empty($notifBaru)): ?><span class="nav-badge"><?= (int)$notifBaru ?></span><?php endif; ?></span><span>Pesan</span></a>
        <a href="profil.php" class="bnav-item <?= in_array($halaman, ['profil.php', 'pengaturan.php'], true) ? 'active' : '' ?>"><?= userAvatar($_SESSION['avatar'] ?? null, $_SESSION['nama'] ?? '', 'bnav-ava') ?><span>Profil</span></a>
    </div>
</nav>
<?php endif;
}

/**
 * Output penutup: footer + </body>.
 */
function layout_footer(): void
{
    ?>
<?= shareScript() ?>
<?= layout_toasts() ?>
<script>
    // Eye toggle: tampil/sembunyi password
    document.querySelectorAll('.pw-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = document.getElementById(btn.getAttribute('data-target'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('.ic-show').hidden = show;
            btn.querySelector('.ic-hide').hidden = !show;
            btn.setAttribute('title', show ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });
</script>
<script>
    // Transisi halus saat pindah/kembali antar fitur (fade keluar dulu, baru navigasi)
    (function() {
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a[href]');
            if (!link) return;
            var href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#' || link.target === '_blank'
                || link.hasAttribute('download') || link.hasAttribute('onclick')) return;
            if (/download\.php/.test(href)) return; // unduhan: jangan ganggu
            try {
                var url = new URL(href, window.location.href);
                if (url.origin !== window.location.origin) return; // link luar: biarkan
            } catch (err) { return; }
            e.preventDefault();
            document.body.classList.add('page-leaving');
            setTimeout(function() { window.location.href = link.href; }, 170);
        });
    })();
</script>
    <script>
        // Menu opsi "..." pada kartu/rail (mobile): buka/tutup panel; Esc & klik luar menutup
        (function () {
            var wraps = Array.prototype.slice.call(document.querySelectorAll('.more-wrap'));
            if (!wraps.length) return;
            function closeAll() {
                wraps.forEach(function (w) {
                    w.classList.remove('open');
                    var b = w.querySelector('.more-btn');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
            }
            wraps.forEach(function (w) {
                var btn = w.querySelector('.more-btn');
                if (!btn) return;
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var wasOpen = w.classList.contains('open');
                    closeAll();
                    if (!wasOpen) { w.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
                });
                w.addEventListener('click', function (e) { e.stopPropagation(); });
            });
            document.addEventListener('click', closeAll);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
        })();
    </script>
<script>
    // PIN 6 kotak ala dompet digital: satu digit per kotak, maju otomatis,
    // backspace mundur, tempel (paste) mengisi semua. Tidak jalan bila tidak ada widget.
    (function() {
        document.querySelectorAll('.pin-boxes').forEach(function(wrap) {
            var boxes = Array.prototype.slice.call(wrap.querySelectorAll('input[data-pin-box]'));
            var hidden = wrap.querySelector('input[type=hidden]');
            if (!boxes.length || !hidden) return;
            var sync = function() {
                hidden.value = boxes.map(function(b) { return b.value; }).join('');
                boxes.forEach(function(b) { b.classList.toggle('filled', b.value !== ''); });
            };
            boxes.forEach(function(box, i) {
                box.addEventListener('input', function() {
                    box.value = box.value.replace(/\D/g, '').slice(0, 1);
                    sync();
                    if (box.value !== '' && i + 1 < boxes.length) boxes[i + 1].focus();
                });
                box.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && box.value === '' && i > 0) boxes[i - 1].focus();
                });
                box.addEventListener('paste', function(e) {
                    e.preventDefault();
                    var t = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '').slice(0, boxes.length);
                    boxes.forEach(function(b, j) { b.value = t.charAt(j) || ''; });
                    sync();
                    var last = Math.min(t.length, boxes.length) - 1;
                    (boxes[last < 0 ? 0 : last]).focus();
                });
            });
            sync();
        });
    })();
</script>
</body>
</html><?php
}

/**
 * Notifikasi toast (dikumpulkan lalu tampil mengambang di bawah layar).
 * $type: success | danger | warning
 */
function layout_alert(string $message, string $type = 'success'): void
{
    $iconName = $type === 'success' ? 'check' : ($type === 'warning' ? 'alert' : 'x');
    $GLOBALS['__toasts'][] = ['msg' => $message, 'type' => $type, 'icon' => $iconName];
}

/**
 * Render semua toast yang dikumpulkan + JS auto-dismiss.
 * Dipanggil sekali di layout_footer().
 */
function layout_toasts(): void
{
    $toasts = $GLOBALS['__toasts'] ?? [];
    if (empty($toasts)) {
        return;
    }
    ?>
    <div class="toast-stack" id="toastStack">
        <?php foreach ($toasts as $t): ?>
            <div class="toast toast-<?= $t['type'] ?>" role="alert">
                <span class="toast-iconbox"><?= icon($t['icon'], 15) ?></span>
                <span class="toast-msg"><?= $t['msg'] ?></span>
                <button type="button" class="toast-close" aria-label="Tutup"><?= icon('x', 13) ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        // Toast: hilang otomatis setelah 4 detik, bisa ditutup manual
        (function() {
            var stack = document.getElementById('toastStack');
            if (!stack) return;
            stack.querySelectorAll('.toast').forEach(function(el) {
                var gone = false;
                var dismiss = function() {
                    if (gone) return;
                    gone = true;
                    el.classList.add('leaving');
                    setTimeout(function() { el.remove(); }, 320);
                };
                setTimeout(dismiss, 4000);
                el.querySelector('.toast-close').addEventListener('click', dismiss);
            });
        })();
    </script>
    <script>
        // Menu opsi "..." pada kartu/rail (mobile): buka/tutup panel; Esc & klik luar menutup
        (function () {
            var wraps = Array.prototype.slice.call(document.querySelectorAll('.more-wrap'));
            if (!wraps.length) return;
            function closeAll() {
                wraps.forEach(function (w) {
                    w.classList.remove('open');
                    var b = w.querySelector('.more-btn');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
            }
            wraps.forEach(function (w) {
                var btn = w.querySelector('.more-btn');
                if (!btn) return;
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var wasOpen = w.classList.contains('open');
                    closeAll();
                    if (!wasOpen) { w.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
                });
                w.addEventListener('click', function (e) { e.stopPropagation(); });
            });
            document.addEventListener('click', closeAll);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
        })();
    </script>
    <?php
}














