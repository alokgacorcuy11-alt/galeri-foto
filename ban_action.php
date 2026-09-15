<?php
// File: ban_action.php
// Admin: ban/unban satu akun. Alasan wajib saat ban dan dikirim ke pesan user.

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
$alasan   = trim((string)($_POST['alasan'] ?? ''));
$alasan   = mb_substr($alasan, 0, 255);

// Target harus ada, bukan diri sendiri, dan bukan sesama admin (anti lockout)
$stmt = $koneksi->prepare('SELECT id, username, level FROM users WHERE id = ?');
$stmt->bind_param('i', $targetId);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();

if (!$target || $targetId === (int)$_SESSION['user_id'] || $target['level'] === 'Admin' || !in_array($aksi, ['ban', 'unban'], true)) {
    header('Location: users.php?status=ditolak');
    exit;
}

// Hanya aksi destruktif (ban) yang wajib PIN; memulihkan (unban) tidak merusak apa pun.
if ($aksi === 'ban') {
    $pinHash = actorPinHash();
    if ($pinHash !== null && !pinValid($pinHash, $_POST['pin'] ?? '')) {
        header('Location: users.php?status=pin-salah');
        exit;
    }
}

if ($aksi === 'ban') {
    if ($alasan === '') {
        header('Location: users.php?status=alasan-wajib');
        exit;
    }
    $stmtU = $koneksi->prepare('UPDATE users SET banned = 1, alasan_ban = ?, remember_token = NULL WHERE id = ?');
    $stmtU->bind_param('si', $alasan, $targetId);
    $stmtU->execute();
    createNotification($targetId, 'banned', 'Akun Anda dibanned. Alasan: ' . $alasan);
    header('Location: users.php?status=ban-sukses&u=' . urlencode($target['username']));
    exit;
}

// unban
$stmtU = $koneksi->prepare('UPDATE users SET banned = 0, alasan_ban = NULL WHERE id = ?');
$stmtU->bind_param('i', $targetId);
$stmtU->execute();
createNotification($targetId, 'unbanned', 'Banned Anda dicabut. Silakan masuk lagi.');
header('Location: users.php?status=unban-sukses&u=' . urlencode($target['username']));
exit;
