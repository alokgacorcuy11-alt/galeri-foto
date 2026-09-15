<?php
// File: pin.php
// Toggle sematkan/lepas foto di profil (khusus pemilik, maks 3 sematan).

require_once __DIR__ . '/includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$photoId = (int)($_POST['photo_id'] ?? 0);

if ($photoId > 0) {
    $stmt = $koneksi->prepare('SELECT id, user_id, pinned FROM photos WHERE id = ?');
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $foto = $stmt->get_result()->fetch_assoc();

    // Hanya pemilik foto yang boleh menyematkan
    if ($foto && (int)$foto['user_id'] === (int)$_SESSION['user_id']) {
        if ((int)$foto['pinned'] === 1) {
            $stmtU = $koneksi->prepare('UPDATE photos SET pinned = 0 WHERE id = ?');
            $stmtU->bind_param('i', $photoId);
            $stmtU->execute();
            header('Location: profil.php?status=pin-batal');
            exit;
        }
        $stmtC = $koneksi->prepare('SELECT COUNT(*) AS total FROM photos WHERE user_id = ? AND pinned = 1');
        $stmtC->bind_param('i', $_SESSION['user_id']);
        $stmtC->execute();
        if ((int)$stmtC->get_result()->fetch_assoc()['total'] >= 3) {
            header('Location: profil.php?status=pin-penuh');
            exit;
        }
        $stmtU = $koneksi->prepare('UPDATE photos SET pinned = 1 WHERE id = ?');
        $stmtU->bind_param('i', $photoId);
        $stmtU->execute();
        header('Location: profil.php?status=pin-ok');
        exit;
    }
}

header('Location: index.php');
exit;
