<?php
// File: album.php
// Daftar album + buat album baru

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$error = '';

// Proses buat album
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($nama === '') {
        $error = 'Nama album wajib diisi.';
    } else {
        $stmt = $koneksi->prepare('INSERT INTO albums (user_id, nama, deskripsi) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $_SESSION['user_id'], $nama, $deskripsi);
        if ($stmt->execute()) {
            header('Location: album.php?status=tambah-sukses');
            exit;
        } else {
            $error = 'Gagal membuat album: ' . $koneksi->error;
        }
    }
}

// Ambil semua album + jumlah foto + cover (foto terbaru)
$albums = $koneksi->query(
    'SELECT a.*, u.nama_lengkap,
            (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) AS jml_foto,
            (SELECT p2.filename FROM photos p2 WHERE p2.album_id = a.id ORDER BY p2.created_at DESC LIMIT 1) AS cover
     FROM albums a JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC'
)->fetch_all(MYSQLI_ASSOC);

layout_header('Album');
?>

<main class="main">
    <div class="page-head">
        <div>
            <h1 class="page-title">
                Album <span class="pill"><?= count($albums) ?> album</span>
            </h1>
            <p class="page-sub">Kelompokkan foto ke dalam album</p>
        </div>
        <button type="button" class="btn btn-primary" id="btnBuatAlbum"><?= icon('plus', 15) ?> Buat Album</button>
    </div>

    <?php if ($error): ?>
        <?= layout_alert($error, 'danger') ?>
    <?php endif; ?>

    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] === 'tambah-sukses'): ?>
            <?= layout_alert('Album berhasil dibuat.', 'success') ?>
        <?php elseif ($_GET['status'] === 'edit-sukses'): ?>
            <?= layout_alert('Perubahan album berhasil disimpan.', 'success') ?>
        <?php elseif ($_GET['status'] === 'hapus-sukses'): ?>
            <?= layout_alert('Album dihapus. Foto di dalamnya tidak ikut terhapus.', 'success') ?>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Form buat album (disembunyikan, toggle via JS) -->
    <div class="form-card album-form-card" id="formAlbum" hidden>
        <h1>Buat Album Baru</h1>
        <form method="post" novalidate>
            <div class="field">
                <label for="nama">Nama Album <span style="color: var(--danger)">*</span></label>
                <input type="text" id="nama" name="nama" class="input" required maxlength="100"
                       placeholder="Contoh: Liburan Pantai 2024">
            </div>
            <div class="field">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" class="input"
                          placeholder="Ceritakan isi album ini..."></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> Simpan Album</button>
                <button type="button" class="btn btn-ghost" id="btnBatalAlbum">Batal</button>
            </div>
        </form>
    </div>

    <?php if (count($albums) === 0): ?>
        <div class="empty">
            <?= icon('folder', 44) ?>
            <h3>Belum ada album</h3>
            <p>Buat album untuk mengelompokkan foto-foto Anda.</p>
        </div>
    <?php else: ?>
        <div class="photo-grid">
            <?php foreach ($albums as $a): ?>
                <article class="photo-card">
                    <a href="album_detail.php?id=<?= $a['id'] ?>" class="photo-media album-cover">
                        <?php if ($a['cover']): ?>
                            <img src="uploads/<?= e($a['cover']) ?>" alt="<?= e($a['nama']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="album-cover-empty"><?= icon('folder', 40) ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="photo-body">
                        <h2 class="photo-title"><a href="album_detail.php?id=<?= $a['id'] ?>"><?= e($a['nama']) ?></a></h2>
                        <div class="photo-meta">
                            <span><?= icon('user', 13) ?> <?= e($a['nama_lengkap']) ?></span>
                            <span><?= icon('image', 13) ?> <?= (int)$a['jml_foto'] ?> foto</span>
                        </div>
                        <?php if ($a['deskripsi']): ?>
                            <p class="photo-desc"><?= e($a['deskripsi']) ?></p>
                        <?php endif; ?>
                        <div class="photo-actions">
                            <a href="album_detail.php?id=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">Buka Album</a>
                            <span class="spacer"></span>
                            <?php if ((int)$a['user_id'] === (int)$_SESSION['user_id']): ?>
                                <a href="album_edit.php?id=<?= $a['id'] ?>" class="btn-icon sm" title="Edit album"><?= icon('edit', 15) ?></a>
                            <?php endif; ?>
                            <?php if ((int)$a['user_id'] === (int)$_SESSION['user_id'] || isAdmin()): ?>
                                <a href="album_hapus.php?id=<?= $a['id'] ?>" class="btn-icon sm danger" title="Hapus album"
                                   onclick="return confirm('Hapus album ini? Foto di dalamnya TIDAK ikut terhapus.')"><?= icon('trash', 15) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    // Toggle form buat album
    var form = document.getElementById('formAlbum');
    document.getElementById('btnBuatAlbum').addEventListener('click', function() {
        form.hidden = !form.hidden;
        if (!form.hidden) document.getElementById('nama').focus();
    });
    document.getElementById('btnBatalAlbum').addEventListener('click', function() {
        form.hidden = true;
    });
    <?php if ($error): ?>
    form.hidden = false;
    <?php endif; ?>
</script>

<?php layout_footer(); ?>
