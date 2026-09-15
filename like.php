<?php
// File: like.php
// Toggle like/unlike foto (satu user = satu like per foto)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/engagement.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$photoId = (int)($_POST['photo_id'] ?? 0);

if ($photoId > 0) {
    // Pastikan foto ada (sekalian ambil pemilik + judul untuk notifikasi)
    $stmt = $koneksi->prepare('SELECT id, user_id, judul FROM photos WHERE id = ?');
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $fotoLike = $stmt->get_result()->fetch_assoc();
    if (!$fotoLike) {
        header('Location: index.php');
        exit;
    }

    if (hasLiked($photoId)) {
        // Sudah like -> UNLIKE
        $stmtDel = $koneksi->prepare('DELETE FROM likes WHERE photo_id = ? AND user_id = ?');
        $stmtDel->bind_param('ii', $photoId, $_SESSION['user_id']);
        $stmtDel->execute();
    } else {
        // Belum like -> LIKE (INSERT IGNORE mencegah duplikat race)
        $stmtIns = $koneksi->prepare('INSERT IGNORE INTO likes (photo_id, user_id) VALUES (?, ?)');
        $stmtIns->bind_param('ii', $photoId, $_SESSION['user_id']);
        $stmtIns->execute();
        // Beri tahu pemilik foto bila like benar-benar baru (bukan milik sendiri)
        if ($stmtIns->affected_rows > 0 && (int)$fotoLike['user_id'] !== (int)$_SESSION['user_id']) {
            createNotification((int)$fotoLike['user_id'], 'like', $_SESSION['nama'] . ' menyukai foto "' . $fotoLike['judul'] . '".');
        }
    }
}

// Kembali ke halaman asal (detail, atau index beserta filternya).
header('Location: ' . backUrl($photoId));
exit;
