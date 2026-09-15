<?php
// File: install.php
// Jalankan SEKALI untuk membuat database, tabel, dan akun default.
// Setelah selesai, HAPUS file ini dari server.

// Konfigurasi (harus sama dengan includes/config.php)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_galeri_foto');

echo '<h2>Instalasi NoxGallery (Website Galeri Foto)</h2><pre>';

// 1. Koneksi TANPA memilih database dulu (karena database belum ada)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die('Koneksi gagal: ' . $conn->connect_error);
}

// 2. Buat database
$conn->query('CREATE DATABASE IF NOT EXISTS ' . DB_NAME . ' CHARACTER SET utf8mb4');
$conn->select_db(DB_NAME);

// 3. Buat tabel users
$sqlUsers = 'CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    level ENUM("Admin", "User") NOT NULL DEFAULT "User",
    remember_token VARCHAR(64) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB';

if (!$conn->query($sqlUsers)) {
    die('Gagal membuat tabel users: ' . $conn->error);
}
echo "Tabel users dibuat.\n";

// 3b. Migrasi: tambah kolom remember_token jika DB lama belum punya
$cekKolom = $conn->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
if ($cekKolom && $cekKolom->num_rows === 0) {
$conn->query('ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) NULL DEFAULT NULL');
echo "Kolom remember_token ditambahkan (migrasi).\n";
}

// 3c. Migrasi: kolom ban untuk fitur banned admin
$cekKolom = $conn->query("SHOW COLUMNS FROM users LIKE 'banned'");
if ($cekKolom && $cekKolom->num_rows === 0) {
$conn->query('ALTER TABLE users ADD COLUMN banned TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN alasan_ban VARCHAR(255) NULL');
echo "Kolom banned + alasan_ban ditambahkan (migrasi).\n";
}

// 3c2. Migrasi: kolom avatar untuk foto profil
$cekKolom = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($cekKolom && $cekKolom->num_rows === 0) {
$conn->query('ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL');
echo "Kolom avatar ditambahkan (migrasi).\n";
}
// 3c3. Migrasi: kolom pin_hash untuk PIN keamanan ala dompet digital
$cekKolom = $conn->query("SHOW COLUMNS FROM users LIKE 'pin_hash'");
if ($cekKolom && $cekKolom->num_rows === 0) {
$conn->query('ALTER TABLE users ADD COLUMN pin_hash VARCHAR(255) NULL');
echo "Kolom pin_hash ditambahkan (migrasi).\n";
}
// 3c4. Migrasi: kolom last_active untuk batas idle sesi 30 menit
$cekKolom = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if ($cekKolom && $cekKolom->num_rows === 0) {
$conn->query('ALTER TABLE users ADD COLUMN last_active DATETIME NULL');
echo "Kolom last_active ditambahkan (migrasi).\n";
}
// 3c5. Migrasi: kolom bio untuk halaman profil
$cekKolom = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
if ($cekKolom && $cekKolom->num_rows === 0) {
$conn->query('ALTER TABLE users ADD COLUMN bio TEXT NULL');
echo "Kolom bio ditambahkan (migrasi).\n";
}
// 3c6. Migrasi: kolom verified untuk centang biru dari admin
$cekKolom = $conn->query("SHOW COLUMNS FROM users LIKE 'verified'");
if ($cekKolom && $cekKolom->num_rows === 0) {
$conn->query('ALTER TABLE users ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0');
echo "Kolom verified ditambahkan (migrasi).\n";
}
// 3c7. Migrasi: tipe notif follow + verified (abaikan bila gagal = versi lama)
$conn->query("ALTER TABLE notifications MODIFY tipe ENUM('banned','unbanned','photo_deleted','follow','verified') NOT NULL DEFAULT 'photo_deleted'");
// 3c8. Migrasi: tipe notif comment + like, kolom views + pinned di photos
$conn->query("ALTER TABLE notifications MODIFY tipe ENUM('banned','unbanned','photo_deleted','follow','verified','comment','like') NOT NULL DEFAULT 'photo_deleted'");
$cekKolom = $conn->query("SHOW COLUMNS FROM photos LIKE 'views'");
if ($cekKolom && $cekKolom->num_rows === 0) {
$conn->query('ALTER TABLE photos ADD COLUMN views INT NOT NULL DEFAULT 0, ADD COLUMN pinned TINYINT(1) NOT NULL DEFAULT 0');
echo "Kolom views + pinned ditambahkan (migrasi).\n";
}
// 3e. Tabel saved (simpanan) + reposts (posting ulang) + follows (pengikut):
// kunci ganda, CASCADE dua arah agar ikut hilang saat akun/foto dihapus
$sqlFollow = 'CREATE TABLE IF NOT EXISTS follows (
follower_id INT NOT NULL,
following_id INT NOT NULL,
created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (follower_id, following_id),
FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
if (!$conn->query($sqlFollow)) {
die('Gagal membuat tabel follows: ' . $conn->error);
}
echo "Tabel follows siap.\n";
foreach (['saved', 'reposts'] as $tabelRelasi) {$sqlRelasi = "CREATE TABLE IF NOT EXISTS $tabelRelasi (
user_id INT NOT NULL,
photo_id INT NOT NULL,
created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (user_id, photo_id),
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (!$conn->query($sqlRelasi)) {
die('Gagal membuat tabel ' . $tabelRelasi . ': ' . $conn->error);
}
echo "Tabel $tabelRelasi siap.\n";
}
if (!is_dir(__DIR__ . '/uploads/avatars')) {
mkdir(__DIR__ . '/uploads/avatars', 0755, true);
echo "Folder uploads/avatars dibuat.\n";
}

// 3d. Tabel notifications: pesan sistem (ban, unban, foto dihapus, follow, verified)
$sqlNotif = 'CREATE TABLE IF NOT EXISTS notifications (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
tipe ENUM(\'banned\',\'unbanned\',\'photo_deleted\',\'follow\',\'verified\',\'comment\',\'like\') NOT NULL DEFAULT \'photo_deleted\',
pesan TEXT NOT NULL,
is_read TINYINT(1) NOT NULL DEFAULT 0,
created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
if (!$conn->query($sqlNotif)) {
die('Gagal membuat tabel notifications: ' . $conn->error);
}
echo "Tabel notifications siap.\n";

// 4. Buat tabel albums (dibuat sebelum photos karena direferensikan)
$sqlAlbums = 'CREATE TABLE IF NOT EXISTS albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nama VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB';

if (!$conn->query($sqlAlbums)) {
    die('Gagal membuat tabel albums: ' . $conn->error);
}
echo "Tabel albums dibuat.\n";

// 5. Buat tabel photos
$sqlPhotos = 'CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    album_id INT NULL DEFAULT NULL,
    judul VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    filename VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE SET NULL
) ENGINE=InnoDB';

if (!$conn->query($sqlPhotos)) {
    die('Gagal membuat tabel photos: ' . $conn->error);
}
echo "Tabel photos dibuat.\n";

// 5b. Migrasi: tambah kolom album_id jika DB lama belum punya
$cekAlbum = $conn->query("SHOW COLUMNS FROM photos LIKE 'album_id'");
if ($cekAlbum && $cekAlbum->num_rows === 0) {
    $conn->query('ALTER TABLE photos ADD COLUMN album_id INT NULL DEFAULT NULL AFTER user_id');
    $conn->query('ALTER TABLE photos ADD CONSTRAINT fk_photos_album FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE SET NULL');
    echo "Kolom album_id ditambahkan (migrasi).\n";
}

// 4b. Buat tabel likes (satu user = satu like per foto)
$sqlLikes = 'CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_like (photo_id, user_id),
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB';

if (!$conn->query($sqlLikes)) {
    die('Gagal membuat tabel likes: ' . $conn->error);
}
echo "Tabel likes dibuat.\n";

// 4c. Buat tabel comments
$sqlComments = 'CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    isi VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB';

if (!$conn->query($sqlComments)) {
    die('Gagal membuat tabel comments: ' . $conn->error);
}
echo "Tabel comments dibuat.\n";

// 5. Insert akun default jika belum ada
$cek = $conn->query('SELECT COUNT(*) AS total FROM users');
$row = $cek->fetch_assoc();

if ((int)$row['total'] === 0) {
    $hashAdmin = password_hash('admin123', PASSWORD_DEFAULT);
    $hashUser  = password_hash('user123', PASSWORD_DEFAULT);

    $stmt = $conn->prepare('INSERT INTO users (username, password, nama_lengkap, level) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $u, $p, $n, $l);

    $u = 'admin'; $p = $hashAdmin; $n = 'Administrator'; $l = 'Admin';
    $stmt->execute();

    $u = 'user'; $p = $hashUser; $n = 'User Biasa'; $l = 'User';
    $stmt->execute();

    echo "Akun default dibuat:\n";
    echo "  - Admin: admin / admin123\n";
    echo "  - User : user / user123\n";
} else {
    echo "Akun sudah ada, lewati pembuatan akun default.\n";
}

echo "\nInstalasi selesai! Silakan login di <a href='login.php'>login.php</a>\n";
echo '<strong>PENTING: Hapus file install.php sekarang juga!</strong>';
