<?php
// File: profil.php
// Halaman profil ala TikTok: header (avatar, nama, bio), statistik real (tanpa angka
// palsu), tab Postingan / Posting Ulang / Disukai / Disimpan. Dua tab terakhir hanya
// untuk pemiliknya (semi-privat). Tanpa sistem follow, jadi tanpa angka follower.

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();

$lihatId = isset($_GET['id']) ? (int)$_GET['id'] : (int)$_SESSION['user_id'];
if ($lihatId <= 0) {
    header('Location: index.php');
    exit;
}
$isSelf = $lihatId === (int)$_SESSION['user_id'];

$stmt = $koneksi->prepare('SELECT id, username, nama_lengkap, level, bio, banned, avatar, verified, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $lihatId);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc();
if (!$profil) {
    header('Location: index.php');
    exit;
}

// Akun dibanned: hanya admin/pemilik yang boleh intip isinya
$bannedOrang = (int)$profil['banned'] === 1 && !$isSelf && !isAdmin();

// Tab whitelist; Disukai/Disimpan khusus pemilik
$tabRaw = $_GET['tab'] ?? 'postingan';
$boleh = $isSelf ? ['postingan', 'repost', 'disukai', 'disimpan'] : ['postingan', 'repost'];
$tab = in_array($tabRaw, $boleh, true) ? $tabRaw : 'postingan';

function tabUrl(int $uid, string $tab): string
{
    return 'profil.php?id=' . $uid . ($tab === 'postingan' ? '' : '&tab=' . $tab);
}

// Statistik real: postingan, suka diterima, album
$stmt = $koneksi->prepare('SELECT COUNT(*) AS total FROM photos WHERE user_id = ?');
$stmt->bind_param('i', $lihatId);
$stmt->execute();
$statFoto = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt = $koneksi->prepare('SELECT COUNT(l.id) AS total FROM likes l JOIN photos p ON p.id = l.photo_id WHERE p.user_id = ?');
$stmt->bind_param('i', $lihatId);
$stmt->execute();
$statSuka = (int)$stmt->get_result()->fetch_assoc()['total'];
$statPengikut = countFollowers($lihatId);
$statMengikuti = countFollowing($lihatId);

// Isi grid per tab (kosong bila akun dibanned-orang)
$items = [];
if (!$bannedOrang) {
    if ($tab === 'postingan') {
        $stmt = $koneksi->prepare('SELECT id, judul, filename, pinned FROM photos WHERE user_id = ? ORDER BY pinned DESC, created_at DESC');
        $stmt->bind_param('i', $lihatId);
    } elseif ($tab === 'repost') {
        $stmt = $koneksi->prepare('SELECT p.id, p.judul, p.filename, r.created_at AS waktu_repost FROM photos p JOIN reposts r ON r.photo_id = p.id WHERE r.user_id = ? ORDER BY r.created_at DESC');
        $stmt->bind_param('i', $lihatId);
    } elseif ($tab === 'disukai') {
        $stmt = $koneksi->prepare('SELECT p.id, p.judul, p.filename FROM photos p JOIN likes l ON l.photo_id = p.id WHERE l.user_id = ? ORDER BY l.created_at DESC');
        $stmt->bind_param('i', $lihatId);
    } else {
        $stmt = $koneksi->prepare('SELECT p.id, p.judul, p.filename FROM photos p JOIN saved s ON s.photo_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC');
        $stmt->bind_param('i', $lihatId);
    }
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Daftar pengikut/mengikuti (?lihat=): baris user sederhana ala TikTok
$lihatRaw = $_GET['lihat'] ?? '';
$lihat = in_array($lihatRaw, ['pengikut', 'mengikuti'], true) ? $lihatRaw : '';
$daftarFollow = [];
if ($lihat !== '' && !$bannedOrang) {
    if ($lihat === 'pengikut') {
        $stmt = $koneksi->prepare('SELECT u.id, u.username, u.nama_lengkap, u.avatar, u.verified FROM users u JOIN follows f ON f.follower_id = u.id WHERE f.following_id = ? ORDER BY f.created_at DESC');
    } else {
        $stmt = $koneksi->prepare('SELECT u.id, u.username, u.nama_lengkap, u.avatar, u.verified FROM users u JOIN follows f ON f.following_id = u.id WHERE f.follower_id = ? ORDER BY f.created_at DESC');
    }
    $stmt->bind_param('i', $lihatId);
    $stmt->execute();
    $daftarFollow = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$tabMeta = [
    'postingan' => ['label' => 'Postingan', 'icon' => 'image', 'kosong' => 'Belum ada postingan'],
    'repost'    => ['label' => 'Posting Ulang', 'icon' => 'share', 'kosong' => 'Belum ada posting ulang'],
    'disukai'   => ['label' => 'Disukai', 'icon' => 'heart', 'kosong' => 'Belum ada yang disukai'],
    'disimpan'  => ['label' => 'Disimpan', 'icon' => 'bookmark', 'kosong' => 'Belum ada simpanan'],
];

layout_header($isSelf ? 'Profil Saya' : 'Profil ' . $profil['username']);

$statusProfil = $_GET['status'] ?? '';
if ($statusProfil === 'pin-ok') layout_alert('Disematkan ke profil.', 'success');
elseif ($statusProfil === 'pin-penuh') layout_alert('Maksimal 3 postingan disematkan.', 'warning');
elseif ($statusProfil === 'pin-batal') layout_alert('Sematan dilepas.', 'success');
?>

<main class="main">
<a href="index.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke galeri</a>

<!-- Kepala profil -->
<header class="prof-head">
    <?= userAvatar($profil['avatar'] ?? null, $profil['nama_lengkap'], 'lg') ?>
    <h1 class="prof-name"><?= e($profil['nama_lengkap']) ?> <?= verifiedBadge((int)$profil['verified'] === 1, 20) ?>
        <?php if ((int)$profil['banned'] === 1): ?><span class="pill pill-danger">Banned</span><?php endif; ?>
    </h1>
    <p class="prof-username">@<?= e($profil['username']) ?> &middot; <?= e($profil['level']) ?></p>
    <?php if (!empty($profil['bio'])): ?>
        <p class="prof-bio"><?= e($profil['bio']) ?></p>
    <?php elseif ($isSelf): ?>
        <p class="prof-bio"><a href="pengaturan.php" class="meta-link">+ Tambah bio</a></p>
    <?php endif; ?>
    <p class="prof-stats">
        <strong><?= $statFoto ?></strong> Postingan
        <span class="prof-dot">&middot;</span> <a class="meta-link" href="<?= e(tabUrl($lihatId, 'postingan') . '&lihat=pengikut') ?>"><strong><?= $statPengikut ?></strong> Pengikut</a>
        <span class="prof-dot">&middot;</span> <a class="meta-link" href="<?= e(tabUrl($lihatId, 'postingan') . '&lihat=mengikuti') ?>"><strong><?= $statMengikuti ?></strong> Mengikuti</a>
        <span class="prof-dot">&middot;</span> <strong><?= $statSuka ?></strong> Suka
    </p>
    <?php if ($isSelf): ?>
        <a href="pengaturan.php" class="btn btn-ghost btn-sm"><?= icon('edit', 14) ?> Edit Profil</a>
    <?php else: ?>
        <?php $ikut = isFollowing($lihatId); ?>
        <form method="post" action="follow.php">
            <input type="hidden" name="user_id" value="<?= $lihatId ?>">
            <input type="hidden" name="back" value="<?= e('profil.php?id=' . $lihatId . ($tab !== 'postingan' ? '&tab=' . $tab : '')) ?>">
            <button type="submit" class="btn <?= $ikut ? 'btn-ghost' : 'btn-primary' ?> btn-sm"><?= $ikut ? 'Mengikuti' : 'Ikuti' ?></button>
        </form>
    <?php endif; ?>
</header>

<?php if ($bannedOrang): ?>
    <div class="empty">
        <?= icon('alert', 28) ?>
        <h3>Akun dibanned</h3>
        <p>Profil ini tidak tersedia.</p>
    </div>
<?php elseif ($lihat !== ''): ?>
    <div class="page-head">
        <div>
            <h1 class="page-title"><?= $lihat === 'pengikut' ? 'Pengikut' : 'Mengikuti' ?> <span class="pill"><?= count($daftarFollow) ?></span></h1>
        </div>
        <a href="<?= e(tabUrl($lihatId, 'postingan')) ?>" class="btn btn-ghost"><?= icon('arrow-left', 15) ?> Profil</a>
    </div>
    <?php if (empty($daftarFollow)): ?>
        <div class="empty">
            <?= icon('user', 28) ?>
            <h3><?= $lihat === 'pengikut' ? 'Belum ada pengikut' : 'Belum mengikuti siapa pun' ?></h3>
        </div>
    <?php else: ?>
        <section class="dash-card user-list">
            <?php foreach ($daftarFollow as $f): ?>
                <div class="user-row">
                    <?= userAvatar($f['avatar'] ?? null, $f['nama_lengkap'], 'sm') ?>
                    <div class="user-info">
                        <strong><a class="meta-link" href="profil.php?id=<?= (int)$f['id'] ?>"><?= e($f['nama_lengkap']) ?></a> <?= verifiedBadge((int)$f['verified'] === 1, 13) ?></strong>
                        <span class="user-meta">@<?= e($f['username']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php else: ?>
    <!-- Tab gaya TikTok: ikon + label, garis bawah = aktif -->
    <nav class="prof-tabs" aria-label="Tab profil">
        <?php foreach ($boleh as $t): ?>
            <a class="ptab <?= $tab === $t ? 'active' : '' ?>" <?= $tab === $t ? 'aria-current="true"' : '' ?>
               href="<?= e(tabUrl($lihatId, $t)) ?>" title="<?= e($tabMeta[$t]['label']) ?>" aria-label="<?= e($tabMeta[$t]['label']) ?>">
                <?= icon($tabMeta[$t]['icon'], 18) ?><span><?= e($tabMeta[$t]['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($items)): ?>
        <div class="empty">
            <?= icon($tabMeta[$tab]['icon'], 28) ?>
            <h3><?= e($tabMeta[$tab]['kosong']) ?></h3>
            <p><?= $isSelf
                ? ($tab === 'postingan' ? 'Bagikan foto pertama Anda ke galeri.' : ($tab === 'repost' ? 'Posting ulang foto favorit lewat tombol bagikan.' : ($tab === 'disukai' ? 'Ketuk hati pada foto yang Anda suka.' : 'Ketuk bookmark untuk menyimpan foto.')))
                : 'Belum ada isi di tab ini.' ?></p>
            <?php if ($isSelf && $tab === 'postingan'): ?>
                <a href="tambah.php" class="btn btn-primary"><?= icon('plus', 15) ?> Tambah Foto</a>
            <?php elseif ($isSelf && $tab !== 'postingan'): ?>
                <a href="index.php" class="btn btn-ghost"><?= icon('image', 15) ?> Lihat galeri</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="prof-grid">
            <?php foreach ($items as $it): ?>
                <a class="prof-thumb" href="detail.php?id=<?= (int)$it['id'] ?>" title="<?= e($it['judul']) ?>">
                    <img src="uploads/<?= e($it['filename']) ?>" alt="<?= e($it['judul']) ?>" loading="lazy">
                    <?php if ($tab === 'repost'): ?>
                        <span class="prof-badge"><?= icon('share', 11) ?> Ulang</span>
                    <?php elseif ($tab === 'postingan' && (int)($it['pinned'] ?? 0) === 1): ?>
                        <span class="prof-badge"><?= icon('pin', 11) ?> Semat</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</main>

<?php layout_footer(); ?>
