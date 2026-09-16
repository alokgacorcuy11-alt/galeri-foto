<?php
// File: detail.php
// Detail satu foto: like, komentar, share (ala TikTok)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $koneksi->prepare(
    'SELECT p.*, u.nama_lengkap, u.avatar, u.verified, al.nama AS album_nama
     FROM photos p JOIN users u ON p.user_id = u.id
     LEFT JOIN albums al ON p.album_id = al.id
     WHERE p.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$photo = $stmt->get_result()->fetch_assoc();

if (!$photo) {
    header('Location: index.php');
    exit;
}

// Hitung dilihat: tiap buka halaman detail +1 (termasuk pemilik, seperti umumnya)
$stmtV = $koneksi->prepare('UPDATE photos SET views = views + 1 WHERE id = ?');
$stmtV->bind_param('i', $id);
$stmtV->execute();

$jmlLike     = countLikes($id);
$jmlKomentar = countComments($id);
$sudahLike   = hasLiked($id);$sudahSimpan = hasSaved($id);$komentar    = getComments($id);
$shareUrl    = 'http' . (($_SERVER['HTTPS'] ?? '') === 'on' ? 's' : '') . '://'
              . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'])
              . '/detail.php?id=' . $id;

layout_header($photo['judul']);
?>

<main class="main">
    <div class="detail-wrap">
        <a href="index.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke galeri</a>

        <div class="feed-layout">
            <!-- Kolom kiri: media foto -->
            <article class="detail-card feed-media-card">
                <div class="detail-media">
                    <img src="uploads/<?= e($photo['filename']) ?>" alt="<?= e($photo['judul']) ?>">
                </div>
            </article>

            <!-- Action rail vertikal ala TikTok -->
            <div class="action-rail">
                <form method="post" action="like.php">
                    <input type="hidden" name="photo_id" value="<?= $id ?>">
                    <input type="hidden" name="back" value="detail.php">
                    <button type="submit" class="rail-btn like <?= $sudahLike ? 'active' : '' ?>"
                            title="<?= $sudahLike ? 'Batalkan like' : 'Suka foto ini' ?>"
                            aria-pressed="<?= $sudahLike ? 'true' : 'false' ?>">
                        <?= icon('heart', 22) ?>
                        <span class="rail-count"><?= $jmlLike ?></span>
                    </button>
                </form>
                <a href="#komentar" class="rail-btn" title="Komentar">
                    <?= icon('message', 21) ?>
                    <span class="rail-count"><?= $jmlKomentar ?></span>
                </a>
                <a href="download.php?id=<?= $id ?>" class="rail-btn" title="Download gambar">
                    <?= icon('download', 21) ?>
                    <span class="rail-count">Unduh</span>
                </a>
                <div class="more-wrap">
                    <button type="button" class="rail-btn more-btn" aria-label="Opsi lainnya" aria-expanded="false" title="Opsi lainnya">
                        <?= icon('dots', 21) ?>
                        <span class="rail-count">Lainnya</span>
                    </button>
                    <div class="more-panel">
                        <form method="post" action="save.php" class="action-like-form">
                            <input type="hidden" name="photo_id" value="<?= $id ?>">
                            <input type="hidden" name="back" value="detail.php">
                            <button type="submit" class="action-btn save <?= $sudahSimpan ? 'active' : '' ?>"
                                    title="<?= $sudahSimpan ? 'Hapus dari simpanan' : 'Simpan foto' ?>"
                                    aria-pressed="<?= $sudahSimpan ? 'true' : 'false' ?>"><?= icon('bookmark', 16) ?> <span><?= $sudahSimpan ? 'Batal simpan' : 'Simpan' ?></span></button>
                        </form>
                        <?php /* Edit + semat: hanya pemilik foto */ ?>
                        <?php if ((int)$photo['user_id'] === (int)$_SESSION['user_id']): ?>
                        <a href="edit.php?id=<?= $id ?>" class="action-btn" title="Edit foto"><?= icon('edit', 15) ?> <span>Edit</span></a>
                        <form method="post" action="pin.php" class="action-like-form">
                            <input type="hidden" name="photo_id" value="<?= $id ?>">
                            <button type="submit" class="action-btn" title="<?= (int)$photo['pinned'] === 1 ? 'Lepas sematan' : 'Sematkan ke profil' ?>">
                                <?= icon('pin', 16) ?> <span><?= (int)$photo['pinned'] === 1 ? 'Lepas sematan' : 'Sematkan' ?></span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                        <button type="button" class="action-btn danger" data-del-id="<?= $id ?>" data-del-judul="<?= e($photo['judul']) ?>"
                                title="Hapus foto"><?= icon('trash', 15) ?> <span>Hapus</span></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Kolom kanan: info + komentar -->
            <div class="feed-panel">
                <div class="feed-head">
                    <?= userAvatar($photo['avatar'] ?? null, $photo['nama_lengkap']) ?>
                    <div>
                        <div class="feed-uploader"><a class="meta-link" href="profil.php?id=<?= (int)$photo['user_id'] ?>"><?= e($photo['nama_lengkap']) ?></a> <?= verifiedBadge((int)$photo['verified'] === 1, 13) ?></div>
                        <div class="feed-time"><?= timeAgo($photo['created_at']) ?></div>
                    </div>
                </div>

                <h1 class="detail-title"><?= e($photo['judul']) ?></h1>
                <div class="detail-meta" style="margin-bottom: 14px;">
                    <span class="meta-stat"><?= icon('eye', 14) ?> <?= (int)$photo['views'] ?> dilihat</span>
                    <?php if (!empty($photo['album_nama'])): ?>
                        <a href="album_detail.php?id=<?= $photo['album_id'] ?>" class="meta-album"><?= icon('folder', 14) ?> <?= e($photo['album_nama']) ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($photo['deskripsi']): ?>
                    <p class="detail-desc"><?= tagify($photo['deskripsi'] ?? '') ?></p>
                <?php endif; ?>

                <!-- Share ke WhatsApp / Telegram / Instagram / Copy -->
                <div class="section-label"><?= icon('share', 14) ?> Bagikan ke</div>
                        <?= shareButtons($shareUrl, $photo['judul'], $id, 'detail.php') ?>

                <div class="comments-section" id="komentar">
                    <div class="section-label"><?= icon('message', 14) ?> Komentar (<?= $jmlKomentar ?>)</div>

                    <!-- Form komentar -->
                    <form method="post" action="komentar.php" class="comment-form">
                        <input type="hidden" name="photo_id" value="<?= $id ?>">
                        <?= userAvatar($_SESSION['avatar'] ?? null, $_SESSION['nama'] ?? '', 'sm') ?>
                        <input type="text" name="isi" class="input" maxlength="500"
                               placeholder="Tulis komentar..." required>
                        <button type="submit" class="btn btn-primary btn-sm" title="Kirim"><?= icon('send', 15) ?></button>
                    </form>

                    <!-- Daftar komentar -->
                    <?php if (empty($komentar)): ?>
                        <p class="comments-empty">Belum ada komentar. Jadilah yang pertama berkomentar.</p>
                    <?php else: ?>
                        <div class="comment-list">
                            <?php foreach ($komentar as $k): ?>
                                <div class="comment-item">
                                    <?= userAvatar($k['avatar'] ?? null, $k['nama_lengkap'], 'sm') ?>
                                    <div class="comment-body">
                                        <div class="comment-head">
                                            <strong><?= e($k['nama_lengkap']) ?></strong> <?= verifiedBadge((int)$k['verified'] === 1, 12) ?>
                                            <?php if ($k['level'] === 'Admin'): ?>
                                                <span class="chip-level admin">Admin</span>
                                            <?php endif; ?>
                                            <span class="comment-time"><?= timeAgo($k['created_at']) ?></span>
                                            <?php if (isAdmin() || (int)$k['user_id'] === (int)$_SESSION['user_id']): ?>
                                                <form method="post" action="komentar_hapus.php" class="comment-del">
                                                    <input type="hidden" name="comment_id" value="<?= (int)$k['id'] ?>">
                                                    <button type="submit" class="comment-del-btn" title="Hapus komentar" aria-label="Hapus komentar"><?= icon('trash', 13) ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                        <p><?= e($k['isi']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php delPhotoDialog(); ?>
<?php layout_footer(); ?>
