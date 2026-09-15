<?php
// File: album_detail.php
// Isi satu album: daftar foto di dalamnya

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: album.php');
    exit;
}

$stmt = $koneksi->prepare(
    'SELECT a.*, u.nama_lengkap FROM albums a JOIN users u ON a.user_id = u.id WHERE a.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$album = $stmt->get_result()->fetch_assoc();

if (!$album) {
    header('Location: album.php');
    exit;
}

// Foto-foto dalam album ini
$stmt = $koneksi->prepare(
    'SELECT p.*, u.nama_lengkap
     FROM photos p JOIN users u ON p.user_id = u.id
     WHERE p.album_id = ?
     ORDER BY p.created_at DESC'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$isOwner = ((int)$album['user_id'] === (int)$_SESSION['user_id']);

layout_header($album['nama']);
?>

<main class="main">
    <a href="album.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke album</a>

    <div class="page-head">
        <div>
            <h1 class="page-title">
                <?= icon('folder', 22) ?> <?= e($album['nama']) ?>
                <span class="pill"><?= count($photos) ?> foto</span>
            </h1>
            <p class="page-sub">
                oleh <?= e($album['nama_lengkap']) ?> · <?= date('d M Y', strtotime($album['created_at'])) ?>
                <?php if ($album['deskripsi']): ?> · <?= e($album['deskripsi']) ?><?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($isOwner): ?>
                <a href="album_edit.php?id=<?= $id ?>" class="btn btn-ghost"><?= icon('edit', 15) ?> Edit Album</a>
            <?php endif; ?>
            <?php if ($isOwner || isAdmin()): ?>
                <a href="album_hapus.php?id=<?= $id ?>" class="btn btn-ghost danger"
                   onclick="return confirm('Hapus album ini? Foto di dalamnya TIDAK ikut terhapus.')"><?= icon('trash', 15) ?> Hapus</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($photos) === 0): ?>
        <div class="empty">
            <?= icon('image', 44) ?>
            <h3>Album masih kosong</h3>
            <p>Tambahkan foto ke album ini saat upload atau edit foto.</p>
            <a href="tambah.php" class="btn btn-primary"><?= icon('plus', 15) ?> Tambah Foto</a>
        </div>
    <?php else: ?>
        <div class="photo-grid">
            <?php $cardI = 0; ?>
            <?php foreach ($photos as $photo): ?>
                <?php
                    $jmlLike     = countLikes($photo['id']);
                    $jmlKomentar = countComments($photo['id']);
                    $sudahLike   = hasLiked($photo['id']);
                ?>
                <article class="photo-card" style="--card-i: <?= $cardI++ ?>">
                    <a href="detail.php?id=<?= $photo['id'] ?>" class="photo-media">
                        <img src="uploads/<?= e($photo['filename']) ?>" alt="<?= e($photo['judul']) ?>" loading="lazy">
                    </a>
                    <div class="photo-body">
                        <h2 class="photo-title"><a href="detail.php?id=<?= $photo['id'] ?>"><?= e($photo['judul']) ?></a></h2>
                        <div class="photo-meta">
                            <span><?= icon('user', 13) ?> <?= e($photo['nama_lengkap']) ?></span>
                            <span><?= icon('calendar', 13) ?> <?= date('d M Y', strtotime($photo['created_at'])) ?></span>
                        </div>
                        <div class="photo-actions">
                            <form method="post" action="like.php" class="action-like-form">
                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                <input type="hidden" name="back" value="index.php">
                                <button type="submit" class="action-btn like <?= $sudahLike ? 'active' : '' ?>"
                                        title="<?= $sudahLike ? 'Batalkan like' : 'Suka foto ini' ?>"
                                        aria-pressed="<?= $sudahLike ? 'true' : 'false' ?>">
                                    <?= icon('heart', 16) ?>
                                    <span class="count"><?= $jmlLike > 0 ? $jmlLike : '' ?></span>
                                </button>
                            </form>
                            <a href="detail.php?id=<?= $photo['id'] ?>#komentar" class="action-btn" title="Lihat komentar">
                                <?= icon('message', 16) ?>
                                <span class="count"><?= $jmlKomentar > 0 ? $jmlKomentar : '' ?></span>
                            </a>
                            <span class="spacer"></span>
                            <a href="download.php?id=<?= $photo['id'] ?>" class="action-btn" title="Download gambar"><?= icon('download', 16) ?></a>
                            <?php if ((int)$photo['user_id'] === (int)$_SESSION['user_id']): ?>
                                <a href="edit.php?id=<?= $photo['id'] ?>" class="action-btn" title="Edit foto"><?= icon('edit', 15) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php layout_footer(); ?>
