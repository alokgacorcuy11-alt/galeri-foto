<?php
// File: album_edit.php
// Edit album - hanya pemilik album

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: album.php');
    exit;
}

$stmt = $koneksi->prepare('SELECT * FROM albums WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$album = $stmt->get_result()->fetch_assoc();

if (!$album) {
    header('Location: album.php');
    exit;
}

// Aturan kepemilikan: hanya pemilik album yang boleh edit
if ((int)$album['user_id'] !== (int)$_SESSION['user_id']) {
    header('Location: album_detail.php?id=' . $id);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($nama === '') {
        $error = 'Nama album wajib diisi.';
    } else {
        $stmtUp = $koneksi->prepare('UPDATE albums SET nama = ?, deskripsi = ? WHERE id = ?');
        $stmtUp->bind_param('ssi', $nama, $deskripsi, $id);

        if ($stmtUp->execute()) {
            header('Location: album.php?status=edit-sukses');
            exit;
        } else {
            $error = 'Gagal menyimpan perubahan: ' . $koneksi->error;
        }
    }
}

layout_header('Edit Album');
?>

<main class="main">
    <a href="album_detail.php?id=<?= $id ?>" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke album</a>

    <div class="form-card">
        <h1>Edit Album</h1>

        <?php if ($error): ?>
            <?= layout_alert($error, 'danger') ?>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="field">
                <label for="nama">Nama Album <span style="color: var(--danger)">*</span></label>
                <input type="text" id="nama" name="nama" class="input" required maxlength="100"
                       value="<?= e($_POST['nama'] ?? $album['nama']) ?>">
            </div>
            <div class="field">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" class="input"><?= e($_POST['deskripsi'] ?? $album['deskripsi']) ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> Simpan Perubahan</button>
                <a href="album_detail.php?id=<?= $id ?>" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
</main>

<?php layout_footer(); ?>
