<?php
// File: tambah.php
// Tambah foto baru ke galeri

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

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
    } elseif (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Silakan pilih file foto terlebih dahulu.';
    } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload gagal, silakan coba lagi.';
    } elseif ($_FILES['foto']['size'] > MAX_FILE_SIZE) {
        $error = 'Ukuran file melebihi batas maksimal 5 MB.';
    } else {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ALLOWED_EXT, true)) {
            $error = 'Format tidak didukung. Gunakan: ' . implode(', ', ALLOWED_EXT) . '.';
        } else {
            $newName = uniqid('foto_') . '.' . $ext;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], UPLOAD_DIR . $newName)) {
                $albumNull = ($albumId > 0) ? $albumId : null;
                $stmt = $koneksi->prepare('INSERT INTO photos (user_id, album_id, judul, deskripsi, filename) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('iisss', $_SESSION['user_id'], $albumNull, $judul, $deskripsi, $newName);

                if ($stmt->execute()) {
                    header('Location: index.php?status=tambah-sukses');
                    exit;
                } else {
                    $error = 'Gagal menyimpan ke database: ' . $koneksi->error;
                }
            } else {
                $error = 'Gagal memindahkan file ke folder uploads.';
            }
        }
    }
}

layout_header('Tambah Foto');

// Album milik user untuk dropdown
$stmtAlb = $koneksi->prepare('SELECT id, nama FROM albums WHERE user_id = ? ORDER BY nama ASC');
$stmtAlb->bind_param('i', $_SESSION['user_id']);
$stmtAlb->execute();
$albumList = $stmtAlb->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<main class="main">
    <a href="index.php" class="back-link"><?= icon('arrow-left', 15) ?> Kembali ke galeri</a>

    <div class="form-card">
        <h1>Tambah Foto</h1>

        <?php if ($error): ?>
            <?= layout_alert($error, 'danger') ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" novalidate>
            <div class="field">
                <label for="judul">Judul Foto <span style="color: var(--danger)">*</span></label>
                <input type="text" id="judul" name="judul" class="input" required
                       value="<?= e($_POST['judul'] ?? '') ?>" placeholder="Contoh: Matahari Terbenam di Pantai">
            </div>
            <div class="field">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" class="input"
                          placeholder="Ceritakan sedikit tentang foto ini..."><?= e($_POST['deskripsi'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label for="album_id">Album (opsional)</label>
                <select id="album_id" name="album_id" class="input">
                    <option value="0">Tanpa Album</option>
                    <?php foreach ($albumList as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= (string)($_POST['album_id'] ?? '') === (string)$a['id'] ? 'selected' : '' ?>>
                            <?= e($a['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Belum punya album? <a href="album.php" style="color: var(--text)">Buat album dulu</a>.</div>
            </div>
            <div class="field">
                <label>File Foto <span style="color: var(--danger)">*</span></label>
                <label class="file-drop" id="fileDrop">
                    <?= icon('image', 26) ?>
                    <span class="file-drop-title" id="fileName">Klik atau seret foto ke sini</span>
                    <span class="hint">JPG, PNG, GIF, WEBP · Maksimal 5 MB</span>
                    <input type="file" name="foto" id="fotoInput" required
                           accept=".jpg,.jpeg,.png,.gif,.webp">
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('download', 16) ?> Unggah Foto</button>
                <a href="index.php" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
</main>

<script>
    // Tampilkan nama file yang dipilih pada drop zone
    const input = document.getElementById('fotoInput');
    const label = document.getElementById('fileName');
    const drop  = document.getElementById('fileDrop');

    input.addEventListener('change', () => {
        label.textContent = input.files.length
            ? input.files[0].name
            : 'Klik atau seret foto ke sini';
    });

    ['dragover', 'dragleave', 'drop'].forEach(evt => {
        drop.addEventListener(evt, (e) => {
            e.preventDefault();
            drop.classList.toggle('drag', evt === 'dragover');
        });
    });
</script>

<?php layout_footer(); ?>
