<?php
// File: komentar.php
// Tambah komentar pada foto

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$photoId = (int)($_POST['photo_id'] ?? 0);
$isi     = trim($_POST['isi'] ?? '');
$back    = ($photoId > 0) ? 'detail.php?id=' . $photoId : 'index.php';

if ($photoId > 0 && $isi !== '') {
    // Batasi panjang komentar sesuai kolom VARCHAR(500)
    if (mb_strlen($isi) > 500) {
        $isi = mb_substr($isi, 0, 500);
    }

    // Pastikan foto ada (sekalian ambil pemilik + judul untuk notifikasi)
    $stmt = $koneksi->prepare('SELECT id, user_id, judul FROM photos WHERE id = ?');
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $foto = $stmt->get_result()->fetch_assoc();
    if ($foto) {
        $stmtIns = $koneksi->prepare('INSERT INTO comments (photo_id, user_id, isi) VALUES (?, ?, ?)');
        $stmtIns->bind_param('iis', $photoId, $_SESSION['user_id'], $isi);
        $stmtIns->execute();
        // Beri tahu pemilik foto (kecuali mengomentari foto sendiri)
        if ((int)$foto['user_id'] !== (int)$_SESSION['user_id']) {
            $cuplik = mb_substr($isi, 0, 80);
            createNotification((int)$foto['user_id'], 'comment', $_SESSION['nama'] . ' mengomentari foto "' . $foto['judul'] . '": ' . $cuplik);
        }
    }
}

header('Location: ' . $back . '#komentar');
exit;
