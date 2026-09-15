<?php
// File: dashboard.php
// Dashboard: Admin melihat statistik global, User melihat statistik pribadi

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$isAdmin = isAdmin();
$userId  = (int)$_SESSION['user_id'];

if ($isAdmin) {
    // ===== Statistik global (Admin) =====
    $stats = [
        'foto'      => (int)$koneksi->query('SELECT COUNT(*) AS c FROM photos')->fetch_assoc()['c'],
        'pengguna'  => (int)$koneksi->query('SELECT COUNT(*) AS c FROM users')->fetch_assoc()['c'],
        'like'      => (int)$koneksi->query('SELECT COUNT(*) AS c FROM likes')->fetch_assoc()['c'],
        'komentar'  => (int)$koneksi->query('SELECT COUNT(*) AS c FROM comments')->fetch_assoc()['c'],
    ];

    // Foto terbaru (semua user)
    $recent = $koneksi->query(
        'SELECT p.id, p.judul, p.filename, p.created_at, u.nama_lengkap,
                (SELECT COUNT(*) FROM likes l WHERE l.photo_id = p.id) AS jml_like,
                (SELECT COUNT(*) FROM comments c WHERE c.photo_id = p.id) AS jml_komentar
         FROM photos p JOIN users u ON p.user_id = u.id
         ORDER BY p.created_at DESC LIMIT 6'
    )->fetch_all(MYSQLI_ASSOC);

    // Komentar terbaru
    $recentComments = $koneksi->query(
        'SELECT c.isi, c.created_at, u.nama_lengkap, u.avatar, u.verified, p.judul, p.id AS photo_id
         FROM comments c
         JOIN users u ON c.user_id = u.id
         JOIN photos p ON c.photo_id = p.id
         ORDER BY c.created_at DESC LIMIT 5'
    )->fetch_all(MYSQLI_ASSOC);

    // Foto terpopuler (berdasarkan like)
    $popular = $koneksi->query(
        'SELECT p.id, p.judul, p.filename, COUNT(l.id) AS jml_like
         FROM photos p LEFT JOIN likes l ON l.photo_id = p.id
         GROUP BY p.id ORDER BY jml_like DESC, p.created_at DESC LIMIT 3'
    )->fetch_all(MYSQLI_ASSOC);
} else {
    // ===== Statistik pribadi (User) =====
    $stmt = $koneksi->prepare('SELECT COUNT(*) AS c FROM photos WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats['foto'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    $stmt = $koneksi->prepare(
        'SELECT COUNT(*) AS c FROM likes l JOIN photos p ON l.photo_id = p.id WHERE p.user_id = ?'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats['like'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    $stmt = $koneksi->prepare(
        'SELECT COUNT(*) AS c FROM comments c JOIN photos p ON c.photo_id = p.id WHERE p.user_id = ?'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats['komentar'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    $stmt = $koneksi->prepare('SELECT COUNT(*) AS c FROM likes WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats['likeDiberikan'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    // Foto milik user terbaru
    $stmt = $koneksi->prepare(
        'SELECT p.id, p.judul, p.filename, p.created_at, u.nama_lengkap,
                (SELECT COUNT(*) FROM likes l WHERE l.photo_id = p.id) AS jml_like,
                (SELECT COUNT(*) FROM comments c WHERE c.photo_id = p.id) AS jml_komentar
         FROM photos p JOIN users u ON p.user_id = u.id
         WHERE p.user_id = ?
         ORDER BY p.created_at DESC LIMIT 6'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Foto milik user terpopuler
    $stmt = $koneksi->prepare(
        'SELECT p.id, p.judul, p.filename, COUNT(l.id) AS jml_like
         FROM photos p LEFT JOIN likes l ON l.photo_id = p.id
         WHERE p.user_id = ?
         GROUP BY p.id ORDER BY jml_like DESC, p.created_at DESC LIMIT 3'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $popular = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Komentar terbaru pada foto milik user
    $stmt = $koneksi->prepare(
        'SELECT c.isi, c.created_at, u.nama_lengkap, u.avatar, u.verified, p.judul, p.id AS photo_id
         FROM comments c
         JOIN users u ON c.user_id = u.id
         JOIN photos p ON c.photo_id = p.id
         WHERE p.user_id = ?
         ORDER BY c.created_at DESC LIMIT 5'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $recentComments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

layout_header('Dashboard');
?>

<main class="main">
    <a href="index.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke galeri</a>
    <div class="page-head">
        <div>
            <h1 class="page-title">
                Dashboard <span class="pill"><?= $isAdmin ? 'Admin' : 'User' ?></span>
            </h1>
            <p class="page-sub">
                <?= $isAdmin ? 'Statistik global seluruh galeri' : 'Statistik aktivitas foto Anda' ?>
            </p>
        </div>
        <a href="tambah.php" class="btn btn-primary"><?= icon('plus', 15) ?> Tambah Foto</a>
    </div>

    <!-- Kartu statistik -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon"><?= icon('image', 20) ?></div>
            <div class="stat-value"><?= $stats['foto'] ?></div>
            <div class="stat-label"><?= $isAdmin ? 'Total Foto' : 'Foto Saya' ?></div>
        </div>
        <?php if ($isAdmin): ?>
            <div class="stat-card">
                <div class="stat-icon"><?= icon('user', 20) ?></div>
                <div class="stat-value"><?= $stats['pengguna'] ?></div>
                <div class="stat-label">Pengguna</div>
            </div>
        <?php else: ?>
            <div class="stat-card">
                <div class="stat-icon"><?= icon('heart', 20) ?></div>
                <div class="stat-value"><?= $stats['likeDiberikan'] ?></div>
                <div class="stat-label">Like Diberikan</div>
            </div>
        <?php endif; ?>
        <div class="stat-card">
            <div class="stat-icon"><?= icon('heart', 20) ?></div>
            <div class="stat-value"><?= $stats['like'] ?></div>
            <div class="stat-label"><?= $isAdmin ? 'Total Like' : 'Like Diterima' ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><?= icon('message', 20) ?></div>
            <div class="stat-value"><?= $stats['komentar'] ?></div>
            <div class="stat-label"><?= $isAdmin ? 'Total Komentar' : 'Komentar Diterima' ?></div>
        </div>
    </div>

    <div class="dash-cols">
        <!-- Kolom kiri: foto terbaru + terpopuler -->
        <section class="dash-card">
            <div class="section-label"><?= icon('calendar', 14) ?> <?= $isAdmin ? 'Foto Terbaru' : 'Foto Saya Terbaru' ?></div>
            <?php if (empty($recent)): ?>
                <p class="comments-empty">Belum ada foto. <a href="tambah.php" style="color: var(--text)">Unggah sekarang</a>.</p>
            <?php else: ?>
                <div class="dash-photo-list">
                    <?php foreach ($recent as $p): ?>
                        <a href="detail.php?id=<?= $p['id'] ?>" class="dash-photo-item">
                            <img src="uploads/<?= e($p['filename']) ?>" alt="" loading="lazy">
                            <div class="dash-photo-info">
                                <strong><?= e($p['judul']) ?></strong>
                                <span><?= e($p['nama_lengkap']) ?> · <?= timeAgo($p['created_at']) ?></span>
                            </div>
                            <div class="dash-photo-stats">
                                <span><?= icon('heart', 13) ?> <?= (int)$p['jml_like'] ?></span>
                                <span><?= icon('message', 13) ?> <?= (int)$p['jml_komentar'] ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Kolom kanan: terpopuler + komentar terbaru -->
        <div class="dash-side">
            <section class="dash-card">
                <div class="section-label"><?= icon('heart', 14) ?> Terpopuler</div>
                <?php if (empty($popular)): ?>
                    <p class="comments-empty">Belum ada foto.</p>
                <?php else: ?>
                    <div class="dash-photo-list">
                        <?php foreach ($popular as $p): ?>
                            <a href="detail.php?id=<?= $p['id'] ?>" class="dash-photo-item">
                                <img src="uploads/<?= e($p['filename']) ?>" alt="" loading="lazy">
                                <div class="dash-photo-info">
                                    <strong><?= e($p['judul']) ?></strong>
                                    <span><?= (int)$p['jml_like'] ?> like</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dash-card">
                <div class="section-label"><?= icon('message', 14) ?> Komentar Terbaru<?= $isAdmin ? '' : ' di Foto Saya' ?></div>
                <?php if (empty($recentComments)): ?>
                    <p class="comments-empty">Belum ada komentar.</p>
                <?php else: ?>
                    <div class="dash-comment-list">
                        <?php foreach ($recentComments as $c): ?>
                            <div class="dash-comment-item">
                                <?= userAvatar($c['avatar'] ?? null, $c['nama_lengkap'], 'sm') ?>
                                <div class="comment-body">
                                    <div class="comment-head">
                                        <strong><?= e($c['nama_lengkap']) ?></strong> <?= verifiedBadge((int)$c['verified'] === 1, 12) ?>
                                        <span class="comment-time"><?= timeAgo($c['created_at']) ?></span>
                                    </div>
                                    <p class="dash-comment-text">“<?= e($c['isi']) ?>”</p>
                                    <a href="detail.php?id=<?= $c['photo_id'] ?>" class="dash-comment-target">pada “<?= e($c['judul']) ?>”</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php layout_footer(); ?>
