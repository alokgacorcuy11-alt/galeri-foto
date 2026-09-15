<?php
// File: komentar_hapus.php
// Hapus komentar: pemilik komentar atau Admin. Kembali ke foto asal.

require_once __DIR__ . '/includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['comment_id'] ?? 0);

if ($id > 0) {
    $stmt = $koneksi->prepare('SELECT photo_id, user_id FROM comments WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $komen = $stmt->get_result()->fetch_assoc();

    if ($komen && (isAdmin() || (int)$komen['user_id'] === (int)$_SESSION['user_id'])) {
        $stmtDel = $koneksi->prepare('DELETE FROM comments WHERE id = ?');
        $stmtDel->bind_param('i', $id);
        $stmtDel->execute();
    }

    if ($komen) {
        header('Location: detail.php?id=' . (int)$komen['photo_id'] . '#komentar');
        exit;
    }
}

header('Location: index.php');
exit;
