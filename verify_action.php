<?php
// File: verify_action.php
// Admin: memberi/mencabut centang biru terverifikasi. Hanya untuk user biasa
// (bukan diri sendiri, bukan sesama admin): badge adalah pemberian, bukan klaim sendiri.

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

$targetId = (int)($_POST['target_id'] ?? 0);
$aksi     = $_POST['aksi'] ?? '';

$stmt = $koneksi->prepare('SELECT id, username, level FROM users WHERE id = ?');
$stmt->bind_param('i', $targetId);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();

if (!$target || $targetId === (int)$_SESSION['user_id'] || $target['level'] === 'Admin' || !in_array($aksi, ['verify', 'unverify'], true)) {
    header('Location: users.php?status=ditolak');
    exit;
}

$nilai = $aksi === 'verify' ? 1 : 0;
$stmtU = $koneksi->prepare('UPDATE users SET verified = ? WHERE id = ?');
$stmtU->bind_param('ii', $nilai, $targetId);
$stmtU->execute();
if ($aksi === 'verify') {
    createNotification($targetId, 'verified', 'Selamat! Akun Anda sudah terverifikasi oleh admin.');
    header('Location: users.php?status=verifikasi-ok&u=' . urlencode($target['username']));
} else {
    header('Location: users.php?status=verifikasi-cabut&u=' . urlencode($target['username']));
}
exit;
