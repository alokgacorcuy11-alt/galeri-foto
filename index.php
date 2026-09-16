<?php
// File: index.php
// Halaman utama: galeri foto + fasilitas pencarian (Search)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/notif.php';
requireLogin();

$search = trim($_GET['search'] ?? '');

// Filter pencarian: urutan, album, milik saya. Nilai sort masuk daftar putih,
// album harus angka/ID valid, mine hanya on/off agar URL tidak bisa diracuni.
$sortRaw = $_GET['sort'] ?? 'terbaru';
$sort = in_array($sortRaw, ['terbaru', 'terlama', 'populer', 'diskusi', 'dilihat'], true) ? $sortRaw : 'terbaru';
// Halaman galeri: 12 foto per halaman, ganti filter selalu kembali ke halaman 1
$perPage = 12;
$pageRaw = $_GET['page'] ?? '';
$page = (ctype_digit($pageRaw) && (int)$pageRaw > 0) ? (int)$pageRaw : 1;
$albumRaw = trim($_GET['album'] ?? '');
$albumId = (ctype_digit($albumRaw) && (int)$albumRaw > 0) ? (int)$albumRaw : 0;
$albumNone = ($albumRaw === 'none');
$mine = isset($_GET['mine']) && $_GET['mine'] !== '';
// Filter tambahan: waktu unggah, pengunggah tertentu, yang saya sukai/simpan
$waktuRaw = $_GET['waktu'] ?? '';
$waktu = in_array($waktuRaw, ['hari', 'minggu', 'bulan'], true) ? $waktuRaw : '';
$olehRaw = trim($_GET['oleh'] ?? '');
$olehId = (ctype_digit($olehRaw) && (int)$olehRaw > 0) ? (int)$olehRaw : 0;
$sukaSaya = isset($_GET['suka']) && $_GET['suka'] !== '';
$simpanSaya = isset($_GET['simpan']) && $_GET['simpan'] !== '';
// Filter tagar: hanya huruf/angka/underscore agar aman dirangkai ke REGEXP
$tagRaw = trim($_GET['tag'] ?? '');
$tag = preg_match('/^[0-9A-Za-z_]{1,30}$/', $tagRaw) ? strtolower($tagRaw) : '';

// Daftar album + pengunggah untuk dropdown (disembunyikan bila kosong)
$albums = $koneksi->query('SELECT id, nama FROM albums ORDER BY nama')->fetch_all(MYSQLI_ASSOC);
$pengunggah = $koneksi->query('SELECT DISTINCT u.id, u.nama_lengkap FROM users u JOIN photos p ON p.user_id = u.id ORDER BY u.nama_lengkap')->fetch_all(MYSQLI_ASSOC);

// URL dengan parameter filter yang saling menjaga (chip/link tidak menghapus filter lain)
function urlWith(array $over = []): string
{
    global $search, $sort, $albumRaw, $mine, $waktu, $olehRaw, $sukaSaya, $simpanSaya, $tag;
    $q = [];
    if ($search !== '')        $q['search'] = $search;
    if ($sort !== 'terbaru')   $q['sort'] = $sort;
    if ($albumRaw !== '')      $q['album'] = $albumRaw;
    if ($mine)                 $q['mine'] = '1';
    if ($waktu !== '')         $q['waktu'] = $waktu;
    if ($olehRaw !== '')       $q['oleh'] = $olehRaw;
    if ($sukaSaya)             $q['suka'] = '1';
    if ($simpanSaya)           $q['simpan'] = '1';
    if ($tag !== '')           $q['tag'] = $tag;
    foreach ($over as $k => $v) {
        if ($v === null) unset($q[$k]); else $q[$k] = $v;
    }
    $qs = http_build_query($q);
    return 'index.php' . ($qs !== '' ? '?' . $qs : '');
}

$sql = 'SELECT p.*, u.nama_lengkap, u.username, u.verified, al.nama AS album_nama,
               (SELECT COUNT(*) FROM likes l WHERE l.photo_id = p.id) AS jml_like,
               (SELECT COUNT(*) FROM comments c WHERE c.photo_id = p.id) AS jml_komentar,
               (SELECT COUNT(*) FROM reposts r WHERE r.photo_id = p.id) AS jml_repost,
               EXISTS (SELECT 1 FROM likes ul WHERE ul.photo_id = p.id AND ul.user_id = ?) AS sudah_like,
               EXISTS (SELECT 1 FROM saved sl WHERE sl.photo_id = p.id AND sl.user_id = ?) AS sudah_simpan
        FROM photos p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN albums al ON p.album_id = al.id';
$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(p.judul LIKE ? OR p.deskripsi LIKE ? OR u.nama_lengkap LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if ($albumId > 0) {
    $where[] = 'p.album_id = ?';
    $params[] = $albumId;
    $types .= 'i';
} elseif ($albumNone) {
    $where[] = 'p.album_id IS NULL';
}
if ($mine) {
    $where[] = 'p.user_id = ?';
    $params[] = (int)$_SESSION['user_id'];
    $types .= 'i';
}
if ($waktu === 'hari') {
    $where[] = 'DATE(p.created_at) = CURDATE()';
} elseif ($waktu === 'minggu') {
    $where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($waktu === 'bulan') {
    $where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}
if ($olehId > 0) {
    $where[] = 'p.user_id = ?';
    $params[] = $olehId;
    $types .= 'i';
}
if ($sukaSaya) {
    $where[] = 'EXISTS (SELECT 1 FROM likes l2 WHERE l2.photo_id = p.id AND l2.user_id = ?)';
    $params[] = (int)$_SESSION['user_id'];
    $types .= 'i';
}
if ($simpanSaya) {
    $where[] = 'EXISTS (SELECT 1 FROM saved s2 WHERE s2.photo_id = p.id AND s2.user_id = ?)';
    $params[] = (int)$_SESSION['user_id'];
    $types .= 'i';
}
if ($tag !== '') {
    // Batas kata: #senja cocok, #senjax tidak. Tag sudah divalidasi alnum di atas.
    $where[] = "CONCAT(' ', p.judul, ' ', COALESCE(p.deskripsi, ''), ' ') REGEXP ?";
    $params[] = '#' . $tag . '([^0-9A-Za-z_]|$)';
    $types .= 's';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$orders = [
    'terbaru' => 'p.created_at DESC',
    'terlama' => 'p.created_at ASC',
    'populer' => 'jml_like DESC, p.created_at DESC',
    'diskusi' => 'jml_komentar DESC, p.created_at DESC',
    'dilihat' => 'p.views DESC, p.created_at DESC',
];
$sql .= ' ORDER BY ' . $orders[$sort];
// Parameter WHERE disimpan terpisah agar query COUNT total bisa memakai ulang
$paramsWhere = $params;
$typesWhere = $types;
$offset = ($page - 1) * $perPage;
$sql .= ' LIMIT ? OFFSET ?';
// Dua placeholder SELECT (sudah_like/sudah_simpan) paling depan, lalu WHERE, lalu LIMIT
$viewerId = (int)$_SESSION['user_id'];
$typesAll = 'ii' . $types . 'ii';
$paramsAll = array_merge([$viewerId, $viewerId], $params, [$perPage, $offset]);
$stmt = $koneksi->prepare($sql);
$stmt->bind_param($typesAll, ...$paramsAll);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total hasil (tanpa LIMIT) untuk pill + tombol muat-lebih
$sqlCount = 'SELECT COUNT(*) AS total FROM photos p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN albums al ON p.album_id = al.id';
if ($where) {
    $sqlCount .= ' WHERE ' . implode(' AND ', $where);
}
$stmtC = $koneksi->prepare($sqlCount);
if ($paramsWhere) {
    $stmtC->bind_param($typesWhere, ...$paramsWhere);
}
$stmtC->execute();
$totalFoto = (int)$stmtC->get_result()->fetch_assoc()['total'];
$sisaFoto = $totalFoto - ($offset + count($photos));

// Teks penjelas hasil: pencarian + filter yang aktif
$deskripsiHasil = [];
if ($search !== '') {
    $deskripsiHasil[] = 'cocok dengan "' . $search . '"';
}
$ordersLabel = ['terbaru' => 'terbaru dulu', 'terlama' => 'terlama dulu', 'populer' => 'paling disukai', 'diskusi' => 'paling banyak dibahas', 'dilihat' => 'paling sering dilihat'];
if ($albumId > 0) {
    $namaAlbum = '';
    foreach ($albums as $al) {
        if ((int)$al['id'] === $albumId) $namaAlbum = $al['nama'];
    }
    $deskripsiHasil[] = 'album: ' . $namaAlbum;
} elseif ($albumNone) {
    $deskripsiHasil[] = 'tanpa album';
}
if ($mine) {
    $deskripsiHasil[] = 'foto saya';
}
$labelWaktu = ['hari' => 'hari ini', 'minggu' => '7 hari terakhir', 'bulan' => '30 hari terakhir'];
if ($waktu !== '') {
    $deskripsiHasil[] = $labelWaktu[$waktu];
}
if ($olehId > 0) {
    $namaOleh = '';
    foreach ($pengunggah as $pg) {
        if ((int)$pg['id'] === $olehId) $namaOleh = $pg['nama_lengkap'];
    }
    $deskripsiHasil[] = 'oleh ' . ($namaOleh !== '' ? $namaOleh : 'pengguna');
}
if ($sukaSaya) {
    $deskripsiHasil[] = 'yang saya sukai';
}
if ($simpanSaya) {
    $deskripsiHasil[] = 'simpanan saya';
}
if ($tag !== '') {
    $deskripsiHasil[] = 'tagar #' . $tag;
}
$adaFilter = $search !== '' || $sort !== 'terbaru' || $albumRaw !== '' || $mine || $waktu !== '' || $olehRaw !== '' || $sukaSaya || $simpanSaya || $tag !== '';
// URL kembali (like/simpan/repost): filter + halaman saat ini ikut terjaga
$backSini = urlWith(['page' => $page > 1 ? (string)$page : null]);
layout_header('Galeri');
?>

<main class="main">
    <div class="page-head">
        <div>
            <h1 class="page-title">
                <?= $adaFilter ? 'Hasil ' . ($search !== '' ? 'Pencarian' : 'Filter') : 'Galeri Foto' ?>
                <span class="pill"><?= $totalFoto ?> foto</span>
            </h1>
            <p class="page-sub"><?= $adaFilter ? 'Menampilkan foto ' . e(implode(', ', $deskripsiHasil)) . ' (' . e($ordersLabel[$sort]) . ')' : 'Semua foto yang telah diunggah pengguna' ?></p>
        </div>
    </div>

    <!-- Fasilitas Pencarian (Search): bar penuh sejajar judul -->
    <div class="search-bar search-inline">
        <?= icon('search', 17) ?>
        <form method="get" id="searchForm">
            <?php if ($sort !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
            <?php if ($albumRaw !== ''): ?><input type="hidden" name="album" value="<?= e($albumRaw) ?>"><?php endif; ?>
            <?php if ($mine): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
            <?php if ($waktu !== ''): ?><input type="hidden" name="waktu" value="<?= e($waktu) ?>"><?php endif; ?>
            <?php if ($olehRaw !== ''): ?><input type="hidden" name="oleh" value="<?= e($olehRaw) ?>"><?php endif; ?>
            <?php if ($sukaSaya): ?><input type="hidden" name="suka" value="1"><?php endif; ?>
            <?php if ($simpanSaya): ?><input type="hidden" name="simpan" value="1"><?php endif; ?>
            <?php if ($tag !== ''): ?><input type="hidden" name="tag" value="<?= e($tag) ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Cari judul, deskripsi, atau nama pengunggah..."
                   value="<?= e($search) ?>" <?= $search !== '' ? 'autofocus' : '' ?>>
        </form>
        <?php if ($search !== ''): ?>
                <a href="index.php" class="search-clear" title="Hapus pencarian" aria-label="Hapus pencarian"><?= icon('x', 15) ?></a>
        <?php endif; ?>
    </div>

    <!-- Filter pencarian: urutan + album + milik saya. Semua saling menjaga parameter URL. -->
    <!-- Filter pencarian: 3 grup (urutan, waktu, tampilkan). Semua saling menjaga parameter URL. -->
    <div class="filter-bar">
        <div class="filter-group">
            <span class="filter-label" id="lblUrut">Urutkan</span>
            <div class="filter-chips" role="group" aria-labelledby="lblUrut">
            <a class="fchip <?= $sort === 'terbaru' ? 'active' : '' ?>" <?= $sort === 'terbaru' ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['sort' => null])) ?>">Terbaru</a>
            <a class="fchip <?= $sort === 'terlama' ? 'active' : '' ?>" <?= $sort === 'terlama' ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['sort' => 'terlama'])) ?>">Terlama</a>
            <a class="fchip <?= $sort === 'populer' ? 'active' : '' ?>" <?= $sort === 'populer' ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['sort' => 'populer'])) ?>"><?= icon('heart', 13) ?> Populer</a>
            <a class="fchip <?= $sort === 'diskusi' ? 'active' : '' ?>" <?= $sort === 'diskusi' ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['sort' => 'diskusi'])) ?>"><?= icon('message', 13) ?> Diskusi</a>
            <a class="fchip <?= $sort === 'dilihat' ? 'active' : '' ?>" <?= $sort === 'dilihat' ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['sort' => 'dilihat'])) ?>"><?= icon('eye', 13) ?> Dilihat</a>
            </div>
        </div>
        <div class="filter-group">
            <span class="filter-label" id="lblWaktu">Waktu</span>
            <div class="filter-chips" role="group" aria-labelledby="lblWaktu">
            <a class="fchip <?= $waktu === 'hari' ? 'active' : '' ?>" <?= $waktu === 'hari' ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['waktu' => $waktu === 'hari' ? null : 'hari'])) ?>">Hari ini</a>
            <a class="fchip <?= $waktu === 'minggu' ? 'active' : '' ?>" <?= $waktu === 'minggu' ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['waktu' => $waktu === 'minggu' ? null : 'minggu'])) ?>">7 Hari</a>
            <a class="fchip <?= $waktu === 'bulan' ? 'active' : '' ?>" <?= $waktu === 'bulan' ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['waktu' => $waktu === 'bulan' ? null : 'bulan'])) ?>">30 Hari</a>
            </div>
        </div>
        <div class="filter-group">
            <span class="filter-label" id="lblTampil">Tampilkan</span>
            <div class="filter-chips" role="group" aria-labelledby="lblTampil">
<a class="fchip <?= $mine ? 'active' : '' ?>" <?= $mine ? 'aria-current="true"' : '' ?> href="<?= e($mine ? urlWith(['mine' => null]) : urlWith(['mine' => '1'])) ?>"><?= icon('user', 13) ?> Saya</a>
<a class="fchip <?= $sukaSaya ? 'active' : '' ?>" <?= $sukaSaya ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['suka' => $sukaSaya ? null : '1'])) ?>"><?= icon('heart', 13) ?> Suka</a>
<a class="fchip <?= $simpanSaya ? 'active' : '' ?>" <?= $simpanSaya ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['simpan' => $simpanSaya ? null : '1'])) ?>"><?= icon('bookmark', 13) ?> Simpan</a>
            </div>
            <?php if (!empty($pengunggah)): ?>
                <?php
                $labelOleh = 'Pengunggah';
                foreach ($pengunggah as $pg) {
                    if ($olehId === (int)$pg['id']) $labelOleh = $pg['nama_lengkap'];
                }
                ?>
                <div class="fselect">
                    <button type="button" class="fchip fselect-btn"
                            aria-haspopup="listbox" aria-expanded="false" aria-label="Saring berdasarkan pengunggah">
                        <span><?= e($labelOleh) ?></span>
                    </button>
                    <div class="fselect-panel" role="listbox" aria-label="Pilih pengunggah" hidden>
                        <a class="fselect-opt" role="option" aria-selected="<?= $olehRaw === '' ? 'true' : 'false' ?>" href="<?= e(urlWith(['oleh' => null])) ?>">Semua Pengunggah</a>
                        <?php foreach ($pengunggah as $pg): ?>
                            <a class="fselect-opt" role="option" aria-selected="<?= $olehId === (int)$pg['id'] ? 'true' : 'false' ?>" href="<?= e(urlWith(['oleh' => (string)$pg['id']])) ?>"><?= icon('user', 13) ?> <?= e($pg['nama_lengkap']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($albums)):
                // Label tombol = pilihan aktif (nama album polos, tanpa awalan)
                $labelAlbum = 'Semua Album';
                if ($albumNone) {
                    $labelAlbum = 'Tanpa Album';
                } else {
                    foreach ($albums as $al) {
                        if ($albumId === (int)$al['id']) $labelAlbum = $al['nama'];
                    }
                }
            ?>
                <div class="fselect">
                    <button type="button" class="fchip fselect-btn"
                            aria-haspopup="listbox" aria-expanded="false" aria-label="Saring berdasarkan album">
                        <span><?= e($labelAlbum) ?></span>
                    </button>
                    <div class="fselect-panel" role="listbox" aria-label="Pilih album" hidden>
                        <a class="fselect-opt" role="option" aria-selected="<?= $albumRaw === '' ? 'true' : 'false' ?>" href="<?= e(urlWith(['album' => null])) ?>">Semua Album</a>
                        <a class="fselect-opt" role="option" aria-selected="<?= $albumNone ? 'true' : 'false' ?>" href="<?= e(urlWith(['album' => 'none'])) ?>">Tanpa Album</a>
                        <?php foreach ($albums as $al): ?>
                            <a class="fselect-opt" role="option" aria-selected="<?= $albumId === (int)$al['id'] ? 'true' : 'false' ?>" href="<?= e(urlWith(['album' => (string)$al['id']])) ?>"><?= icon('folder', 13) ?> <?= e($al['nama']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php $tagPopuler = topTags(); ?>
    <?php if (!empty($tagPopuler)): ?>
        <div class="tag-row" aria-label="Tagar populer">
            <span class="filter-label">Populer</span>
            <?php foreach ($tagPopuler as $t): ?>
                <a class="fchip <?= $tag === $t ? 'active' : '' ?>" <?= $tag === $t ? 'aria-current="true"' : '' ?> href="<?= e(urlWith(['tag' => $tag === $t ? null : $t])) ?>">#<?= e($t) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'akses-ditolak'): ?>
        <?= layout_alert('Hapus foto hanya dapat dilakukan oleh <strong>Admin</strong>.', 'warning') ?>
    <?php endif; ?>

    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] === 'tambah-sukses'): ?>
            <?= layout_alert('Foto berhasil ditambahkan ke galeri.', 'success') ?>
        <?php elseif ($_GET['status'] === 'edit-sukses'): ?>
            <?= layout_alert('Perubahan foto berhasil disimpan.', 'success') ?>
<?php elseif ($_GET['status'] === 'hapus-sukses'): ?>
<?= layout_alert('Foto dihapus. Pemiliknya sudah diberi tahu lewat Pesan.', 'success') ?>
<?php elseif ($_GET['status'] === 'hapus-gagal'): ?>
<?= layout_alert('Foto tidak dihapus. Alasan wajib diisi di dialog.', 'danger') ?>
<?php elseif ($_GET['status'] === 'pin-salah'): ?>
<?= layout_alert('PIN salah, foto batal dihapus.', 'danger') ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (count($photos) === 0): ?>
        <div class="empty">
            <?= icon('image', 44) ?>
            <h3><?= $adaFilter ? 'Tidak ada hasil' : 'Galeri masih kosong' ?></h3>
            <p><?= $adaFilter ? 'Coba kata kunci lain, album lain, atau hapus semua filter.' : 'Belum ada foto yang diunggah. Jadilah yang pertama mengunggah.' ?></p>
            <?php if ($adaFilter): ?>
                <a href="index.php" class="btn btn-ghost"><?= icon('x', 15) ?> Hapus Semua Filter</a>
            <?php else: ?>
                <a href="tambah.php" class="btn btn-primary"><?= icon('plus', 15) ?> Tambah Foto Pertama</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="photo-grid">
            <?php $cardI = 0; ?>
            <?php foreach ($photos as $photo): ?>
                <?php
                    // Semua angka & status kartu sudah ikut terhitung di query utama (nol query per kartu)
                    $jmlLike     = (int)$photo['jml_like'];
                    $jmlKomentar = (int)$photo['jml_komentar'];
                    $jmlRepost   = (int)$photo['jml_repost'];
                    $sudahLike   = (bool)$photo['sudah_like'];
                    $sudahSimpan = (bool)$photo['sudah_simpan'];
                    $shareUrl    = 'http' . (($_SERVER['HTTPS'] ?? '') === 'on' ? 's' : '') . '://'
                                  . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'])
                                  . '/detail.php?id=' . $photo['id'];
                ?>
                <article class="photo-card" style="--card-i: <?= $cardI++ ?>">
                    <div class="photo-media">
                        <a href="detail.php?id=<?= $photo['id'] ?>" class="photo-media-link">
                            <img src="thumb.php?f=<?= urlencode($photo['filename']) ?>&w=640" alt="<?= e($photo['judul']) ?>" loading="lazy">
                        </a>
                        <?php /* Rail aksi melayang di foto (khusus HP, gaya TikTok) */ ?>
                        <div class="photo-rail">
                        <form method="post" action="like.php" class="action-like-form">
                            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                            <input type="hidden" name="back" value="<?= e($backSini) ?>">
                            <button type="submit" class="rail2 like <?= $sudahLike ? 'active' : '' ?>" title="Suka"><i><?= icon('heart', 20) ?></i><span><?= $jmlLike ?></span></button>
                        </form>
                        <a class="rail2" href="detail.php?id=<?= $photo['id'] ?>#komentar" title="Komentar"><i><?= icon('message', 20) ?></i><span><?= $jmlKomentar ?></span></a>
                        <button type="button" class="rail2 share-toggle" data-share="<?= $photo['id'] ?>" title="Bagikan"><i><?= icon('share', 19) ?></i><span><?= $jmlRepost > 0 ? $jmlRepost : 'Bagikan' ?></span></button>
                        <div class="more-wrap">
                            <button type="button" class="rail2 more-btn" aria-label="Opsi lainnya" aria-expanded="false" title="Opsi"><i><?= icon('dots', 20) ?></i></button>
                            <div class="more-panel">
                                <a href="download.php?id=<?= $photo['id'] ?>" class="action-btn" title="Download gambar"><?= icon('download', 16) ?> <span>Unduh</span></a>
                                <form method="post" action="save.php" class="action-like-form">
                                    <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                    <input type="hidden" name="back" value="<?= e($backSini) ?>">
                                    <button type="submit" class="action-btn save <?= $sudahSimpan ? 'active' : '' ?>" title="<?= $sudahSimpan ? 'Hapus dari simpanan' : 'Simpan foto' ?>" aria-pressed="<?= $sudahSimpan ? 'true' : 'false' ?>"><?= icon('bookmark', 16) ?> <span><?= $sudahSimpan ? 'Batal simpan' : 'Simpan' ?></span></button>
                                </form>
                                <?php if ((int)$photo['user_id'] === (int)$_SESSION['user_id']): ?>
                                    <a href="edit.php?id=<?= $photo['id'] ?>" class="action-btn" title="Edit foto"><?= icon('edit', 15) ?> <span>Edit</span></a>
                                <?php endif; ?>
                                <?php if (isAdmin()): ?>
                                    <button type="button" class="action-btn danger" data-del-id="<?= $photo['id'] ?>" data-del-judul="<?= e($photo['judul']) ?>" title="Hapus foto"><?= icon('trash', 15) ?> <span>Hapus</span></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="photo-body">
                        <h2 class="photo-title"><a href="detail.php?id=<?= $photo['id'] ?>"><?= e($photo['judul']) ?></a></h2>
                        <div class="photo-meta">
                            <span><?= icon('user', 13) ?> <a class="meta-link" href="profil.php?id=<?= (int)$photo['user_id'] ?>"><?= e($photo['nama_lengkap']) ?></a> <?= verifiedBadge((int)$photo['verified'] === 1, 12) ?></span>
                            <?php if (!empty($photo['album_nama'])): ?>
                                <a href="album_detail.php?id=<?= $photo['album_id'] ?>" class="meta-album"><?= icon('folder', 13) ?> <?= e($photo['album_nama']) ?></a>
                            <?php endif; ?>
                            <span><?= icon('calendar', 13) ?> <?= date('d M Y', strtotime($photo['created_at'])) ?></span>
                            <span><?= icon('eye', 13) ?> <?= (int)$photo['views'] ?> dilihat</span>
                        </div>
                        <?php if ($photo['deskripsi']): ?>
                            <p class="photo-desc"><?= tagify($photo['deskripsi'] ?? '') ?></p>
                        <?php endif; ?>

                        <!-- Action bar ala medsos: like, komentar, share -->
                        <div class="photo-actions">
                            <form method="post" action="like.php" class="action-like-form">
                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                <input type="hidden" name="back" value="<?= e($backSini) ?>">
                                <button type="submit" class="action-btn like <?= $sudahLike ? 'active' : '' ?>"
                                        title="<?= $sudahLike ? 'Batalkan like' : 'Suka foto ini' ?>"
                                        aria-pressed="<?= $sudahLike ? 'true' : 'false' ?>">
                                    <?= icon('heart', 16) ?>
                                    <span class="count"><?= $jmlLike > 0 ? $jmlLike : '' ?></span>
                                </button>
                            </form>
                            <a href="detail.php?id=<?= $photo['id'] ?>#komentar" class="action-btn" title="Lihat komentar">
                                <?= icon('message', 16) ?>
                                <span class="count"><?= $jmlKomentar > 0 ? $jmlKomentar : '' ?></span>
                            </a>
                            <div class="spacer"></div>
                            <button type="button" class="action-btn share-toggle" data-share="<?= $photo['id'] ?>" title="Bagikan">
                                <?= icon('share', 15) ?>
                            </button>
                            <div class="more-wrap">
                                <button type="button" class="action-btn more-btn" aria-label="Opsi lainnya" aria-expanded="false" title="Opsi lainnya"><?= icon('dots', 16) ?></button>
                                <div class="more-panel">
                                    <a href="download.php?id=<?= $photo['id'] ?>" class="action-btn" title="Download gambar"><?= icon('download', 16) ?> <span>Unduh</span></a>
                                    <form method="post" action="save.php" class="action-like-form">
                                        <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                        <input type="hidden" name="back" value="<?= e($backSini) ?>">
                                        <button type="submit" class="action-btn save <?= $sudahSimpan ? 'active' : '' ?>"
                                                title="<?= $sudahSimpan ? 'Hapus dari simpanan' : 'Simpan foto' ?>"
                                                aria-pressed="<?= $sudahSimpan ? 'true' : 'false' ?>"><?= icon('bookmark', 16) ?> <span><?= $sudahSimpan ? 'Batal simpan' : 'Simpan' ?></span></button>
                                    </form>
                                    <?php if ((int)$photo['user_id'] === (int)$_SESSION['user_id']): ?>
                                        <a href="edit.php?id=<?= $photo['id'] ?>" class="action-btn" title="Edit foto"><?= icon('edit', 15) ?> <span>Edit</span></a>
                                    <?php endif; ?>
                                    <?php if (isAdmin()): ?>
                                        <button type="button" class="action-btn danger" data-del-id="<?= $photo['id'] ?>" data-del-judul="<?= e($photo['judul']) ?>" title="Hapus foto"><?= icon('trash', 15) ?> <span>Hapus</span></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?= shareButtons($shareUrl, $photo['judul'], (int)$photo['id'], $backSini) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($sisaFoto > 0): ?>
            <div class="load-more">
                <p class="page-sub">Menampilkan <?= $offset + count($photos) ?> dari <?= $totalFoto ?> foto</p>
                <a class="btn btn-ghost" href="<?= e(urlWith(['page' => (string)($page + 1)])) ?>">Muat lebih (<?= $sisaFoto ?> lagi)</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script>
    // Toggle baris share di kartu (ala TikTok share sheet) - dari bar bawah ATAU rail foto
    document.querySelectorAll('.share-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = btn.closest('.photo-card').querySelector('.share-row');
            row.classList.toggle('open');
        });
    });

    // Dropdown custom (album, pengunggah): buka/tutup, Esc, klik luar, navigasi panah.
    // Opsi adalah link biasa sehingga tetap jalan walau JS parsial.
    (function() {
        var wraps = Array.prototype.slice.call(document.querySelectorAll('.fselect'));
        if (!wraps.length) return;
        function closeAll(refocusBtn) {
            wraps.forEach(function(w) {
                var b = w.querySelector('.fselect-btn');
                var p = w.querySelector('.fselect-panel');
                if (p.hidden) return;
                p.hidden = true;
                b.setAttribute('aria-expanded', 'false');
                p.style.position = ''; p.style.top = ''; p.style.left = ''; p.style.right = '';
                if (refocusBtn === b) b.focus();
            });
        }
        wraps.forEach(function(wrap) {
            var btn = wrap.querySelector('.fselect-btn');
            var panel = wrap.querySelector('.fselect-panel');
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var willOpen = panel.hidden;
                closeAll(null);
                if (willOpen) {
                    panel.hidden = false;
                    btn.setAttribute('aria-expanded', 'true');
                    // HP: baris chip bisa digeser (overflow) -> panel dipaku fixed
                    // mengikuti posisi tombol, supaya tidak terpotong wadah scroll.
                    if (window.matchMedia('(max-width: 640px)').matches) {
                        var r = btn.getBoundingClientRect();
                        panel.style.position = 'fixed';
                        panel.style.top = Math.round(r.bottom + 6) + 'px';
                        panel.style.right = 'auto';
                        var lx = Math.min(Math.max(12, r.left), Math.max(12, window.innerWidth - panel.offsetWidth - 12));
                        panel.style.left = Math.round(lx) + 'px';
                    }
                    var cur = panel.querySelector('[aria-selected="true"]') || panel.querySelector('a');
                    if (cur) cur.focus();
                }
            });
        });
        // Geser baris / resize jendela = tutup panel (posisi fixed tak ikut geser)
        var barEl = document.querySelector('.filter-bar');
        if (barEl) barEl.addEventListener('scroll', function() {
            if (document.querySelector('.fselect-panel:not([hidden])')) closeAll(null);
        });
        window.addEventListener('resize', function() { closeAll(null); });
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.fselect')) closeAll(null);
        });
        document.addEventListener('keydown', function(e) {
            var openPanel = document.querySelector('.fselect-panel:not([hidden])');
            if (e.key === 'Escape') {
                var w = openPanel ? openPanel.closest('.fselect') : null;
                closeAll(null);
                if (w) { var b = w.querySelector('.fselect-btn'); if (b) b.focus(); }
                return;
            }
            if (!openPanel) return;
            var items = Array.prototype.slice.call(openPanel.querySelectorAll('a'));
            var i = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') { e.preventDefault(); (items[i + 1] || items[0]).focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); (items[(i - 1 + items.length) % items.length]).focus(); }
        });
    })();
</script>

<?php delPhotoDialog(); ?>
<?php layout_footer(); ?>
