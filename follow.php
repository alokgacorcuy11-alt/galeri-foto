<?php
// File: follow.php
// Toggle ikuti/berhenti mengikuti (satu relasi per pasangan user).
// Pengikut baru dikirimi notifikasi. Pola sama seperti like.php.

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/engagement.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$targetId = (int)($_POST['user_id'] ?? 0);

// Target harus ada dan bukan diri sendiri
$stmt = $koneksi->prepare('SELECT id, nama_lengkap FROM users WHERE id = ?');
$stmt->bind_param('i', $targetId);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();

if (!$target || $targetId === (int)$_SESSION['user_id']) {
    header('Location: index.php');
    exit;
}

if (isFollowing($targetId)) {
    $stmtDel = $koneksi->prepare('DELETE FROM follows WHERE follower_id = ? AND following_id = ?');
    $stmtDel->bind_param('ii', $_SESSION['user_id'], $targetId);
    $stmtDel->execute();
} else {
    // INSERT IGNORE mencegah duplikat race (kunci ganda follower+following)
    $stmtIns = $koneksi->prepare('INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)');
    $stmtIns->bind_param('ii', $_SESSION['user_id'], $targetId);
    $stmtIns->execute();
    if ($stmtIns->affected_rows > 0) {
        createNotification($targetId, 'follow', $_SESSION['nama'] . ' mulai mengikuti Anda.');
    }
}

header('Location: ' . backUrl(0));
exit;
