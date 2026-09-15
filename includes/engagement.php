<?php
// File: includes/engagement.php
// Helper like, komentar, dan share link

require_once __DIR__ . '/config.php';

/**
 * Hitung total like satu foto
 */
function countLikes(int $photoId): int
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT COUNT(*) AS total FROM likes WHERE photo_id = ?');
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Apakah user saat ini sudah like foto ini?
 */
function hasLiked(int $photoId): bool
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT id FROM likes WHERE photo_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $photoId, $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * URL kembali setelah aksi toggle (like/simpan/posting-ulang/ikuti): detail,
 * profil, atau index beserta filternya. Query disaring via whitelist (anti open-redirect).
 */
function backUrl(int $photoId): string
{
    $raw = $_POST['back'] ?? '';
    if ($raw === 'detail.php' && $photoId > 0) {
        return 'detail.php?id=' . $photoId;
    }
    if (str_starts_with($raw, 'profil.php')) {
        parse_str((string)parse_url($raw, PHP_URL_QUERY), $q);
        $allowP = [];
        if (isset($q['id']) && ctype_digit((string)$q['id']) && (int)$q['id'] > 0) {
            $allowP['id'] = (string)$q['id'];
        }
        if (isset($q['tab']) && in_array($q['tab'], ['postingan', 'repost', 'disukai', 'disimpan'], true)) {
            $allowP['tab'] = $q['tab'];
        }
        if (isset($q['lihat']) && in_array($q['lihat'], ['pengikut', 'mengikuti'], true)) {
            $allowP['lihat'] = $q['lihat'];
        }
        return 'profil.php' . ($allowP ? '?' . http_build_query($allowP) : '');
    }
    parse_str((string)parse_url($raw, PHP_URL_QUERY), $q);
    $allow = [];
    if (isset($q['search']) && is_string($q['search']) && trim($q['search']) !== '') {
        $allow['search'] = mb_substr(trim($q['search']), 0, 100);
    }
    if (isset($q['sort']) && in_array($q['sort'], ['terbaru', 'terlama', 'populer', 'diskusi', 'dilihat'], true)) {
        $allow['sort'] = $q['sort'];
    }
    if (isset($q['album']) && ($q['album'] === 'none' || (ctype_digit((string)$q['album']) && (int)$q['album'] > 0))) {
        $allow['album'] = (string)$q['album'];
    }
    if (isset($q['mine']) && $q['mine'] !== '') {
        $allow['mine'] = '1';
    }
    if (isset($q['waktu']) && in_array($q['waktu'], ['hari', 'minggu', 'bulan'], true)) {
        $allow['waktu'] = $q['waktu'];
    }
    if (isset($q['oleh']) && ctype_digit((string)$q['oleh']) && (int)$q['oleh'] > 0) {
        $allow['oleh'] = (string)$q['oleh'];
    }
    if (isset($q['suka']) && $q['suka'] !== '') {
        $allow['suka'] = '1';
    }
    if (isset($q['simpan']) && $q['simpan'] !== '') {
        $allow['simpan'] = '1';
    }
    if (isset($q['tag']) && preg_match('/^[0-9A-Za-z_]{1,30}$/', (string)($q['tag'] ?? ''))) {
        $allow['tag'] = strtolower((string)$q['tag']);
    }
    if (isset($q['page']) && ctype_digit((string)$q['page']) && (int)$q['page'] > 0) {
        $allow['page'] = (string)$q['page'];
    }
    return $allow ? 'index.php?' . http_build_query($allow) : 'index.php';
}

/**
 * Apakah user aktif mengikuti user ini?
 */
function isFollowing(int $userId): bool
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT follower_id FROM follows WHERE follower_id = ? AND following_id = ?');
    $stmt->bind_param('ii', $_SESSION['user_id'], $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Jumlah pengikut / diikuti satu user.
 */
function countFollowers(int $userId): int
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT COUNT(*) AS total FROM follows WHERE following_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'];
}

function countFollowing(int $userId): int
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT COUNT(*) AS total FROM follows WHERE follower_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Centang biru terverifikasi (lingkaran biru + cek putih). Kosong bila tidak verified.
 */
function verifiedBadge(bool $on, int $size = 14): string
{
    if (!$on) {
        return '';
    }
    return '<span class="verified-badge" title="Terverifikasi" style="width:' . $size . 'px;height:' . $size . 'px">'
        . icon('check', (int)round($size * 0.62)) . '</span>';
}

/**
 * Ubah #tagar dalam teks menjadi link filter (teks di-escape dulu agar aman XSS).
 * Tag valid: huruf/angka/underscore, maks 30 karakter.
 */
function tagify(string $text): string
{
    $aman = e($text);
    $hasil = preg_replace_callback(
        '/#([0-9A-Za-z_]{1,30})/u',
        function ($m) {
            return '<a class="tag-link" href="index.php?tag=' . $m[1] . '">#' . $m[1] . '</a>';
        },
        $aman
    );
    return $hasil ?? $aman;
}

/**
 * Tagar populer (maks 8): diekstrak dari semua judul+deskripsi, urut terbanyak.
 * Huruf kecil semua agar #Senja dan #senja terhitung satu.
 */
function topTags(int $batas = 8): array
{
    global $koneksi;
    $rows = $koneksi->query('SELECT judul, deskripsi FROM photos')->fetch_all(MYSQLI_ASSOC);
    $hitung = [];
    foreach ($rows as $r) {
        $teks = ($r['judul'] ?? '') . ' ' . ($r['deskripsi'] ?? '');
        if (preg_match_all('/#([0-9A-Za-z_]{1,30})/u', $teks, $m)) {
            foreach ($m[1] as $tag) {
                $tag = strtolower($tag);
                $hitung[$tag] = ($hitung[$tag] ?? 0) + 1;
            }
        }
    }
    arsort($hitung);
    return array_slice(array_keys($hitung), 0, $batas);
}

/**
 * Apakah user saat ini menyimpan foto ini? (tab Disimpan di profil)
 */
function hasSaved(int $photoId): bool
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT photo_id FROM saved WHERE photo_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $photoId, $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Apakah user saat ini memposting ulang foto ini? (tab Posting Ulang di profil)
 */
function hasReposted(int $photoId): bool
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT photo_id FROM reposts WHERE photo_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $photoId, $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Hitung total komentar satu foto
 */
function countComments(int $photoId): int
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT COUNT(*) AS total FROM comments WHERE photo_id = ?');
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Ambil daftar komentar satu foto (terbaru di bawah)
 */
function getComments(int $photoId): array
{
    global $koneksi;
    $stmt = $koneksi->prepare(
        'SELECT c.id, c.user_id, c.isi, c.created_at, u.nama_lengkap, u.username, u.level, u.avatar, u.verified
         FROM comments c
         JOIN users u ON c.user_id = u.id
         WHERE c.photo_id = ?
         ORDER BY c.created_at ASC'
    );
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Inisial user dari nama (dipakai untuk avatar): 2 huruf pertama
 */function avatarInitials(string $nama): string
{
    $parts = preg_split('/\s+/', trim($nama));
    $inisial = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $inisial .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $inisial !== '' ? $inisial : '?';
}

/**
 * Avatar user: foto profil jika ada, fallback inisial. $size: '' (38px) atau 'sm' (30px).
 * File selalu basename (tanpa path) agar tidak bisa traversal ke luar uploads/avatars.
 */
function userAvatar(?string $file, string $nama, string $size = ''): string
{
    $cls = trim('avatar-img ' . $size);
    $file = $file ? basename($file) : '';
    if ($file !== '' && is_file(AVATAR_DIR . $file)) {
        return '<img src="uploads/avatars/' . e($file) . '" class="' . $cls . '" alt="">';
    }
    $sm = $size !== '' ? ' ' . $size : '';
    return '<div class="avatar' . $sm . '">' . e(avatarInitials($nama)) . '</div>';
}

/**
 * Widget 6 kotak PIN ala dompet digital. Nilai gabungan dikirim via hidden input
 * bernama $name; JS global di layout_footer() mengisi + memajukan fokus otomatis.
 */
function pinWidget(string $name, string $label): string
{
    $boxes = '';
    for ($i = 1; $i <= 6; $i++) {
        $boxes .= '<input type="password" data-pin-box inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" required aria-label="' . e($label) . ' digit ' . $i . '">';
    }
    return '<div class="field"><span class="pin-label" id="lbl_' . e($name) . '">' . e($label) . '</span>'
        . '<div class="pin-boxes" role="group" aria-labelledby="lbl_' . e($name) . '">' . $boxes
        . '<input type="hidden" name="' . e($name) . '"></div></div>';
}

/**
 * Format waktu relatif ala medsos: "2 mnt lalu", "3 jam lalu", "5 hari lalu"
 */
function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);

    if ($diff < 60)      return 'baru saja';
    if ($diff < 3600)    return floor($diff / 60) . ' mnt lalu';
    if ($diff < 86400)   return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800)  return floor($diff / 86400) . ' hari lalu';
    return date('d M Y', strtotime($datetime));
}

/**
 * Daftar tombol share: WhatsApp, Telegram, Instagram, Copy Link, + Posting Ulang
 * (repost ke tab profil sendiri, ala TikTok). Instagram tidak punya web share API
 * -> buka Instagram + copy caption ke clipboard.
 */
function shareButtons(string $url, string $title, int $photoId = 0, string $back = 'index.php'): string
{
    $url   = rawurlencode($url);
    $title = rawurlencode($title . ' - NoxGallery');
    $plainUrl = htmlspecialchars($url, ENT_QUOTES);

    // Tombol posting ulang: toggle via repost.php, status ikut tombol
    $repost = '';
    if ($photoId > 0) {
        $sudah = hasReposted($photoId);
        $repost = '<form method="post" action="repost.php">'
            . '<input type="hidden" name="photo_id" value="' . $photoId . '">'
            . '<input type="hidden" name="back" value="' . htmlspecialchars($back, ENT_QUOTES) . '">'
            . '<button type="submit" class="share-btn' . ($sudah ? ' active' : '') . '"'
            . ' title="' . ($sudah ? 'Batalkan posting ulang' : 'Posting ulang ke profil') . '">'
            . icon('share', 18) . '<span>' . ($sudah ? 'Diulang' : 'Ulangi') . '</span></button></form>';
    }

    return '
    <div class="share-row" data-share-url="' . $plainUrl . '">
        <a class="share-btn" target="_blank" rel="noopener"
           href="https://wa.me/?text=' . $title . '%20' . $url . '"
           title="Bagikan ke WhatsApp">' . icon('whatsapp', 18) . '<span>WhatsApp</span></a>
        <a class="share-btn" target="_blank" rel="noopener"
           href="https://t.me/share/url?url=' . $url . '&text=' . $title . '"
           title="Bagikan ke Telegram">' . icon('telegram', 18) . '<span>Telegram</span></a>
        <button type="button" class="share-btn" data-share-instagram
           data-caption="' . htmlspecialchars($title, ENT_QUOTES) . ' ' . $plainUrl . '"
           title="Bagikan ke Instagram">' . icon('instagram', 18) . '<span>Instagram</span></button>
        <button type="button" class="share-btn" data-copy-url="' . $plainUrl . '"
           title="Salin link">' . icon('link', 17) . '<span>Salin Link</span></button>
        ' . $repost . '
    </div>';
}

/**
 * JavaScript untuk tombol share (dipanggil sekali di layout_footer).
 */
function shareScript(): string
{
    return <<<'JS'
<script>
function galeriShare() {
  // Instagram: tidak ada web share -> salin caption lalu buka instagram.com
  document.querySelectorAll('[data-share-instagram]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var caption = btn.getAttribute('data-caption') || '';
      navigator.clipboard.writeText(decodeURIComponent(caption)).catch(function() {});
      window.open('https://www.instagram.com/', '_blank');
    });
  });

  // Copy link + feedback "Tersalin!"
  document.querySelectorAll('[data-copy-url]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var url = btn.getAttribute('data-copy-url');
      var label = btn.querySelector('span');
      navigator.clipboard.writeText(decodeURIComponent(url)).then(function() {
        if (label) {
          var old = label.textContent;
          label.textContent = 'Tersalin!';
          setTimeout(function() { label.textContent = old; }, 1600);
        }
      }).catch(function() {
        prompt('Salin link berikut:', decodeURIComponent(url));
      });
    });
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', galeriShare);
} else {
  galeriShare();
}
</script>
JS;
}
