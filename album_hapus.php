<?php
// File: album_hapus.php
// Hapus album - pemilik atau Admin.
// Foto di dalamnya TIDAK ikut terhapus (FK ON DELETE SET NULL -> jadi "Tanpa Album").

require_once __DIR__ . '/includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $koneksi->prepare('SELECT user_id FROM albums WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $album = $stmt->get_result()->fetch_assoc();

    if ($album && ((int)$album['user_id'] === (int)$_SESSION['user_id'] || isAdmin())) {
        $stmtDel = $koneksi->prepare('DELETE FROM albums WHERE id = ?');
        $stmtDel->bind_param('i', $id);
        $stmtDel->execute();

        header('Location: album.php?status=hapus-sukses');
        exit;
    }
}

header('Location: album.php');
exit;
