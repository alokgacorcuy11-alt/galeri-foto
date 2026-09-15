<?php
// File: repost.php
// Toggle posting-ulang/batal (satu user = satu repost per foto).
// Foto repost tampil di tab Posting Ulang profil. Pola sama seperti like.php.

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/engagement.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$photoId = (int)($_POST['photo_id'] ?? 0);

if ($photoId > 0) {
    // Pastikan foto ada
    $stmt = $koneksi->prepare('SELECT id FROM photos WHERE id = ?');
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        header('Location: index.php');
        exit;
    }

    if (hasReposted($photoId)) {
        $stmtDel = $koneksi->prepare('DELETE FROM reposts WHERE photo_id = ? AND user_id = ?');
        $stmtDel->bind_param('ii', $photoId, $_SESSION['user_id']);
        $stmtDel->execute();
    } else {
        // INSERT IGNORE mencegah duplikat race (kunci ganda user+foto)
        $stmtIns = $koneksi->prepare('INSERT IGNORE INTO reposts (photo_id, user_id) VALUES (?, ?)');
        $stmtIns->bind_param('ii', $photoId, $_SESSION['user_id']);
        $stmtIns->execute();
    }
}

header('Location: ' . backUrl($photoId));
exit;
