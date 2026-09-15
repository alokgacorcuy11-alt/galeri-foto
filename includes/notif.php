<?php
// File: includes/notif.php
// Helper pesan sistem: buat, baca, tandai dibaca (ban/unban/foto dihapus)

require_once __DIR__ . '/config.php';

/**
 * Buat satu notifikasi untuk user. Pesan harus sudah final (berisi alasan).
 */
function createNotification(int $userId, string $tipe, string $pesan): void
{
    global $koneksi;
    $tipe = in_array($tipe, ['banned', 'unbanned', 'photo_deleted', 'follow', 'verified', 'comment', 'like'], true) ? $tipe : 'photo_deleted';
    $stmt = $koneksi->prepare('INSERT INTO notifications (user_id, tipe, pesan) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $tipe, $pesan);
    $stmt->execute();
}

/**
 * Jumlah pesan yang belum dibaca user aktif (dipakai badge navbar).
 */
function unreadNotifCount(): int
{
    global $koneksi;
    if (!isset($_SESSION['user_id'])) {
        return 0;
    }
    $stmt = $koneksi->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0');
    $uid = (int)$_SESSION['user_id'];
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Daftar seluruh pesan milik user aktif, terbaru di atas.
 */
function getMyNotifications(): array
{
    global $koneksi;
    $stmt = $koneksi->prepare(
        'SELECT id, tipe, pesan, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC'
    );
    $uid = (int)$_SESSION['user_id'];
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Tandai satu pesan sebagai dibaca (hanya milik sendiri).
 */
function markNotifRead(int $notifId): void
{
    global $koneksi;
    $stmt = $koneksi->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $uid = (int)$_SESSION['user_id'];
    $stmt->bind_param('ii', $notifId, $uid);
    $stmt->execute();
}

/**
 * Tandai semua pesan user aktif sebagai dibaca.
 */
function markAllNotifRead(): void
{
    global $koneksi;
    $stmt = $koneksi->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $uid = (int)$_SESSION['user_id'];
    $stmt->bind_param('i', $uid);
    $stmt->execute();
}

/**
 * Label + ikon per tipe notifikasi, untuk tampilan kartu pesan.
 */
function notifMeta(string $tipe): array
{
    return match ($tipe) {
        'banned'        => ['title' => 'Akun dibanned', 'icon' => 'alert'],
        'unbanned'      => ['title' => 'Banned dicabut', 'icon' => 'check'],
        'follow'        => ['title' => 'Pengikut baru', 'icon' => 'user-plus'],
        'verified'      => ['title' => 'Akun terverifikasi', 'icon' => 'check'],
        'comment'       => ['title' => 'Komentar baru', 'icon' => 'message'],
        'like'          => ['title' => 'Suka baru', 'icon' => 'heart'],
        default         => ['title' => 'Foto dihapus', 'icon' => 'trash'],
    };
}

/**
 * Dialog hapus foto (dipakai index.php & detail.php). Wajib alasan, alurnya POST ke hapus.php.
 */
function delPhotoDialog(): void
{
    if (!isAdmin()) {
        return; // hanya admin yang punya tombol hapus
    }
    ?>
<dialog id="delDialog" class="glass-dialog">
    <form method="post" action="hapus.php">
        <input type="hidden" name="photo_id" id="delPhotoId">
<h2 class="dialog-title">Hapus foto "<span id="delPhotoTitle"></span>"?</h2>
<p class="dialog-sub">Alasan ini dikirim ke Pesan pemilik foto.</p>
        <div class="field">
            <label for="delAlasan">Alasan penghapusan</label>
            <textarea id="delAlasan" name="alasan" class="input" rows="3" maxlength="255" required
                      placeholder="Contoh: foto duplikat atau tidak sesuai aturan galeri"></textarea>
        </div>
        <?php if (actorPinHash() !== null): ?>
            <?= pinWidget('pin', 'PIN keamanan (6 digit)') ?>
        <?php endif; ?>
        <div class="dialog-actions">
            <button type="button" class="btn btn-ghost" id="delCancel">Batal</button>
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('trash', 14) ?> Hapus permanen</button>
        </div>
    </form>
</dialog>
<script>
// Dialog hapus: isi foto target lalu buka. Tutup dengan Esc / klik area luar.
(function () {
    var dlg = document.getElementById('delDialog');
    if (!dlg) return;
    document.querySelectorAll('[data-del-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('delPhotoId').value = btn.getAttribute('data-del-id');
            document.getElementById('delPhotoTitle').textContent = btn.getAttribute('data-del-judul');
            dlg.showModal();
            setTimeout(function () { document.getElementById('delAlasan').focus(); }, 50);
        });
    });
    document.getElementById('delCancel').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
})();
</script>
    <?php
}
