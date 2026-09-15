<?php
// File: edit.php
// Edit judul/deskripsi/foto

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $koneksi->prepare('SELECT * FROM photos WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$photo = $stmt->get_result()->fetch_assoc();

if (!$photo) {
    header('Location: index.php');
    exit;
}

// Aturan kepemilikan: hanya pengupload yang boleh edit fotonya
if ((int)$photo['user_id'] !== (int)$_SESSION['user_id']) {
    header('Location: detail.php?id=' . $id);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $albumId   = (int)($_POST['album_id'] ?? 0);

    // Validasi album milik sendiri (atau 0 = tanpa album)
    if ($albumId > 0) {
        $stmtA = $koneksi->prepare('SELECT id FROM albums WHERE id = ? AND user_id = ?');
        $stmtA->bind_param('ii', $albumId, $_SESSION['user_id']);
        $stmtA->execute();
        if ($stmtA->get_result()->num_rows === 0) {
            $albumId = 0;
        }
    }

    if ($judul === '') {
        $error = 'Judul foto wajib diisi.';
    } else {
        $newName = $photo['filename'];

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['foto']['size'] > MAX_FILE_SIZE) {
                $error = 'Ukuran file melebihi batas maksimal 5 MB.';
            } else {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, ALLOWED_EXT, true)) {
                    $error = 'Format tidak didukung. Gunakan: ' . implode(', ', ALLOWED_EXT) . '.';
                } else {
                    $newName = uniqid('foto_') . '.' . $ext;

                    if (move_uploaded_file($_FILES['foto']['tmp_name'], UPLOAD_DIR . $newName)) {
                        if ($photo['filename'] !== $newName && is_file(UPLOAD_DIR . $photo['filename'])) {
                            unlink(UPLOAD_DIR . $photo['filename']);
                        }
                    } else {
                        $error = 'Gagal mengunggah file baru.';
                    }
                }
            }
        }

        if ($error === '') {
            $albumNull = ($albumId > 0) ? $albumId : null;
            $stmtUpdate = $koneksi->prepare('UPDATE photos SET judul = ?, deskripsi = ?, filename = ?, album_id = ? WHERE id = ?');
            $stmtUpdate->bind_param('sssii', $judul, $deskripsi, $newName, $albumNull, $id);

            if ($stmtUpdate->execute()) {
                header('Location: index.php?status=edit-sukses');
                exit;
            } else {
                $error = 'Gagal menyimpan perubahan: ' . $koneksi->error;
            }
        }
    }
}

layout_header('Edit Foto');

// Album milik user untuk dropdown
$stmtAlb = $koneksi->prepare('SELECT id, nama FROM albums WHERE user_id = ? ORDER BY nama ASC');
$stmtAlb->bind_param('i', $_SESSION['user_id']);
$stmtAlb->execute();
$albumList = $stmtAlb->get_result()->fetch_all(MYSQLI_ASSOC);
$albumAktif = (int)($_POST['album_id'] ?? $photo['album_id'] ?? 0);
?>

<main class="main">
    <a href="index.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke galeri</a>

    <div class="form-card">
        <h1>Edit Foto</h1>

        <?php if ($error): ?>
            <?= layout_alert($error, 'danger') ?>
        <?php endif; ?>

        <div class="current-preview">
            <img src="uploads/<?= e($photo['filename']) ?>" alt="Foto saat ini">
        </div>

        <form method="post" enctype="multipart/form-data" novalidate>
            <div class="field">
                <label for="judul">Judul Foto <span style="color: var(--danger)">*</span></label>
                <input type="text" id="judul" name="judul" class="input" required
                       value="<?= e($_POST['judul'] ?? $photo['judul']) ?>">
            </div>
            <div class="field">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" class="input"
                          placeholder="Ceritakan sedikit tentang foto ini..."><?= e($_POST['deskripsi'] ?? $photo['deskripsi']) ?></textarea>
            </div>
            <div class="field">
                <label for="album_id">Album</label>
                <select id="album_id" name="album_id" class="input">
                    <option value="0" <?= $albumAktif === 0 ? 'selected' : '' ?>>Tanpa Album</option>
                    <?php foreach ($albumList as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $albumAktif === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= e($a['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Ganti Gambar (opsional)</label>
                <label class="file-drop" id="fileDrop">
                    <?= icon('image', 26) ?>
                    <span class="file-drop-title" id="fileName">Klik atau seret foto baru ke sini</span>
                    <span class="hint">Kosongkan jika tidak ingin mengganti gambar</span>
                    <input type="file" name="foto" id="fotoInput"
                           accept=".jpg,.jpeg,.png,.gif,.webp">
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> Simpan Perubahan</button>
                <a href="index.php" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
</main>

<script>
    const input = document.getElementById('fotoInput');
    const label = document.getElementById('fileName');
    const drop  = document.getElementById('fileDrop');

    input.addEventListener('change', () => {
        label.textContent = input.files.length
            ? input.files[0].name
            : 'Klik atau seret foto baru ke sini';
    });

    ['dragover', 'dragleave', 'drop'].forEach(evt => {
        drop.addEventListener(evt, (e) => {
            e.preventDefault();
            drop.classList.toggle('drag', evt === 'dragover');
        });
    });
</script>

<?php layout_footer(); ?>
