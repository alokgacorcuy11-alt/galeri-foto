<?php
// File: pesan.php
// Halaman "Pesan": notifikasi sistem untuk user (dibanned, dipulihkan, foto dihapus).

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();

// Aksi tandai dibaca: pola PRG (proses -> redirect) agar refresh tidak mengulang aksi
if (isset($_GET['readall'])) {
    markAllNotifRead();
    header('Location: pesan.php?status=dibaca');
    exit;
}
if (isset($_GET['read'])) {
    markNotifRead((int)$_GET['read']);
    header('Location: pesan.php');
    exit;
}
if (($_GET['status'] ?? '') === 'dibaca') {
    layout_alert('Semua pesan ditandai dibaca.', 'success');
}

$daftar = getMyNotifications();
$belum  = 0;
foreach ($daftar as $n) {
    if ((int)$n['is_read'] === 0) $belum++;
}

layout_header('Pesan');
?>

<main class="main">
<a href="index.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke galeri</a>
<div class="page-head">
    <div>
<h1 class="page-title">Pesan <?php if ($belum > 0): ?><span class="pill pill-danger"><?= $belum ?> baru</span><?php endif; ?></h1>
<p class="page-sub">Pesan dari admin soal akun dan foto Anda, beserta alasannya.</p>
    </div>
    <?php if ($belum > 0): ?>
        <a href="pesan.php?readall=1" class="btn btn-ghost"><?= icon('check', 15) ?> Tandai semua dibaca</a>
    <?php endif; ?>
</div>

<section class="dash-card notif-list">
    <?php if (empty($daftar)): ?>
<div class="empty">
<?= icon('message', 28) ?>
<h3>Belum ada pesan</h3>
<p>Kabar dari admin soal akun atau foto Anda akan muncul di sini.</p>
</div>
    <?php else: ?>
        <?php foreach ($daftar as $n): $meta = notifMeta($n['tipe']); ?>
            <article class="notif-card <?= (int)$n['is_read'] === 0 ? 'unread' : '' ?>">
                <div class="notif-icon notif-<?= e($n['tipe']) ?>"><?= icon($meta['icon'], 18) ?></div>
                <div class="notif-body">
                    <div class="notif-head">
                        <strong><?= e($meta['title']) ?></strong>
                        <span class="comment-time"><?= timeAgo($n['created_at']) ?></span>
                    </div>
                    <p class="notif-text"><?= e($n['pesan']) ?></p>
                </div>
                <?php if ((int)$n['is_read'] === 0): ?>
                    <a href="pesan.php?read=<?= (int)$n['id'] ?>" class="notif-read" title="Tandai dibaca">
                        <?= icon('check', 15) ?> <span>Dibaca</span>
                    </a>
                <?php else: ?>
                    <span class="notif-seen"><?= icon('check', 15) ?></span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
</main>

<?php layout_footer(); ?>
