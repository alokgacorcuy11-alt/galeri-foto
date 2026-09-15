<?php
// File: hapus.php
// Hapus foto - HANYA Admin. Wajib POST + alasan; alasan dikirim ke Pesan pemilik foto.

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();
requireAdmin(); // redirect jika bukan admin

// Hapus foto sensitif: wajib PIN bila admin sudah pasang
$pinHash = actorPinHash();
if ($pinHash !== null && !pinValid($pinHash, $_POST['pin'] ?? '')) {
    header('Location: index.php?status=pin-salah');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id     = (int)($_POST['photo_id'] ?? 0);
$alasan = trim((string)($_POST['alasan'] ?? ''));
$alasan = mb_substr($alasan, 0, 255);

if ($id > 0 && $alasan !== '') {
    // Ambil data sebelum dihapus (untuk notifikasi + file fisik)
    $stmt = $koneksi->prepare('SELECT filename, judul, user_id FROM photos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $photo = $stmt->get_result()->fetch_assoc();

    if ($photo) {
        $stmtDel = $koneksi->prepare('DELETE FROM photos WHERE id = ?');
        $stmtDel->bind_param('i', $id);

        if ($stmtDel->execute()) {
            if (is_file(UPLOAD_DIR . $photo['filename'])) {
                unlink(UPLOAD_DIR . $photo['filename']);
            }
            // Beri tahu pemilik foto (admin yang hapus foto miliknya sendiri tidak dinotifikasi)
            if ((int)$photo['user_id'] !== (int)$_SESSION['user_id']) {
                createNotification(
                    (int)$photo['user_id'],
                    'photo_deleted',
                    'Foto "' . $photo['judul'] . '" dihapus. Alasan: ' . $alasan
                );
            }
            header('Location: index.php?status=hapus-sukses');
            exit;
        }
    }
}

header('Location: index.php?status=hapus-gagal');
exit;
