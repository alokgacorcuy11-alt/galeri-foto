# NoxGallery - Website Galeri Foto (UKK RPL 2023/2024, Paket 3)

> Brand UI: **NoxGallery** (logo resmi pemilik). Arah desain tertulis di `DESIGN.md`; hasil audit kualitas UI + perbaikannya di `anti-slop/`.

## Deskripsi
Aplikasi **Website Galeri Foto** berbasis **client-server** dibuat untuk memenuhi Uji Kompetensi Keahlian (UKK) Rekayasa Perangkat Lunak tahun pelajaran 2023/2024. Aplikasi memiliki dashboard, sistem autentikasi (login, logout, registrasi) dengan dua level pengguna (`Admin` dan `User`), CRUD data foto, fasilitas **pencarian (search)**, **download gambar**, serta fitur interaksi sosial: **like**, **komentar**, dan **bagikan ke WhatsApp/Telegram/Instagram**.

## Kebutuhan Sistem (Spek Minimal sesuai Soal)
| No | Peralatan | Spesifikasi |
|----|-----------|-------------|
| 1 | Server (PC/Laptop) | Prosesor Dual Core 2,4 GHz, RAM 2 GB, Keyboard, Mouse, Monitor |
| 2 | Client (PC/Laptop) | Prosesor Dual Core 2,4 GHz, RAM 2 GB, Keyboard, Mouse, Monitor |
| 3 | Smartphone | Android / iOS |
| 4 | Koneksi Internet | Minimal 1 Mbps |

## Perangkat Lunak
- **PHP** 7.4+ (disarankan 8.x) dengan ekstensi `mysqli`
- **MySQL** / MariaDB
- **Apache** (XAMPP/Laragon disarankan)
- Text editor: VS Code / Sublime Text
- Browser: Chrome/Firefox

## Struktur Folder
```
galeri-foto/
├── assets/
│   └── style.css         # Design system dark monokrom (custom CSS)
├── includes/
│   ├── config.php        # Konfigurasi database, session, helper functions
│   ├── icons.php         # Library ikon SVG inline (stroke + brand icons)
│   ├── layout.php        # Template bersama: head, navbar, footer, alert
│   └── engagement.php    # Helper like, komentar, share, waktu relatif
├── uploads/              # Penyimpanan file foto (tulisable)
├── DESIGN.md             # Arah desain + alasan tiap keputusan (antislop R-31/R-37)
├── database.sql          # Skema database (referensi)
├── install.php           # Installer otomatis (jalankan sekali, lalu hapus)
├── login.php             # Halaman login
├── register.php          # Halaman registrasi
├── logout.php            # Proses logout
├── dashboard.php         # Dashboard statistik (Admin: global, User: pribadi)
├── album.php             # Daftar album + buat album
├── album_detail.php      # Isi satu album
├── album_edit.php        # Edit album (pemilik saja)
├── album_hapus.php       # Hapus album (pemilik/Admin, foto tidak ikut hapus)
├── index.php             # Halaman utama galeri + search
├── detail.php            # Detail foto + like + komentar + share
├── tambah.php            # Upload/tambah foto
├── edit.php              # Edit foto
├── hapus.php             # Hapus foto (khusus Admin)
├── download.php          # Download gambar
├── like.php              # Toggle like/unlike foto
├── komentar.php          # Tambah komentar foto
├── kebutuhan-teknis.txt  # Daftar kebutuhan teknis & spesifikasi (langkah 1 soal)
└── README.md             # Dokumentasi ini
```

## Cara Instalasi
1. Salin folder `galeri-foto` ke folder web server, misal `C:\xampp\htdocs\galeri-foto`
2. Jalankan Apache & MySQL dari XAMPP Control Panel
3. Buka browser: `http://localhost/galeri-foto/install.php`
4. Installer otomatis membuat database, tabel, dan akun default
5. **Hapus `install.php`** setelah instalasi selesai
6. Login dengan akun default:
   - **Admin**: `admin` / `admin123`
   - **User**: `user` / `user123`

## Hak Akses (Privilege Matrix sesuai Soal)
| Fitur | User | Admin |
|---|---|---|
| Login | ✔ | ✔ |
| Logout | ✔ | ✔ |
| Registrasi | ✔ | ✔ |
| Lihat Data Foto/Galeri | ✔ | ✔ |
| Tambah Foto | ✔ | ✔ |
| Edit Foto | ✔ milik sendiri | ✔ milik sendiri |
| Hapus Foto | ✖ | ✔ |
| Search | ✔ | ✔ |
| Download Gambar | ✔ | ✔ |

## Aturan Kepemilikan Foto
- **Edit**: hanya akun yang mengupload foto tersebut yang melihat tombol Edit dan boleh mengubahnya. Akun lain (termasuk Admin) tidak melihat tombol Edit pada foto bukan miliknya - akses URL langsung juga ditolak server.
- **Hapus**: tetap khusus Admin (sesuai matriks privilege soal).
| Tabel | Kolom | Keterangan |
|---|---|---|
| `users` | id, username, password(hash), nama_lengkap, `level ENUM('Admin','User')`, remember_token, created_at | Data pengguna |
| `photos` | id, user_id(FK), album_id(FK nullable), judul, deskripsi, filename, created_at | Data foto |
| `albums` | id, user_id(FK), nama, deskripsi, created_at | Album foto |
| `likes` | id, photo_id(FK), user_id(FK), created_at, UNIQUE(photo_id,user_id) | 1 user = 1 like per foto |
| `comments` | id, photo_id(FK), user_id(FK), isi(500), created_at | Komentar foto |

## Keamanan yang Diterapkan
- Password di-hash dengan `password_hash()` (bcrypt), verifikasi `password_verify()`
- SQL Injection dicegah dengan **prepared statements** (mysqli) di semua query
- XSS dicegah dengan fungsi `e()` (htmlspecialchars) pada semua output
- Validasi upload: ekstensi whitelist (jpg, jpeg, png, gif, webp) & maks 5 MB
- Nama file di-random (`uniqid`) untuk mencegah path traversal
- Cek level akses dengan `requireAdmin()` untuk fitur hapus
- Like dibatasi 1 per user dengan constraint UNIQUE di database

## Dokumentasi Kode Utama
- `includes/config.php`: koneksi mysqli + helper (`isLoggedIn`, `isAdmin`, `requireLogin`, `requireAdmin`, `e`)
- `login.php`: verifikasi `password_verify()` lalu set session `user_id`, `username`, `nama`, `level` + Remember Me (token acak 30 hari)
- `includes/config.php`: koneksi mysqli + helper (`isLoggedIn`, `isAdmin`, `requireLogin`, `requireAdmin`, `e`) + auto-login dari cookie remember
- `tambah.php`: validasi file (ukuran, ekstensi) → `move_uploaded_file()` → INSERT prepared statement
- `index.php`: search dengan `LIKE ?` pada judul/deskripsi/nama uploader
- `download.php`: header `Content-Disposition: attachment` untuk memaksa download
- `hapus.php`: guard `requireAdmin()` - hanya Admin bisa hapus (sesuai matriks privilege)
- `dashboard.php`: statistik global (Admin) / pribadi (User), foto & komentar terbaru
- `like.php`: toggle like/unlike (INSERT/DELETE prepared statement)
- `komentar.php`: INSERT komentar + redirect kembali ke `detail.php#komentar`
- `includes/layout.php`: template bersama + animasi transisi pindah halaman di semua halaman
- `includes/engagement.php`: helper `countLikes`, `hasLiked`, `getComments`, `timeAgo`, `shareButtons`

## Fitur Album
- **Buat album**: nama + deskripsi, cover otomatis dari foto terbaru di dalamnya
- **Isi album**: pilih album saat upload/edit foto (dropdown album milik sendiri)
- **Badge album**: tampil di kartu galeri & halaman detail, klik untuk buka album
- **Hapus album**: pemilik atau Admin; foto di dalamnya **tidak ikut terhapus** (FK `ON DELETE SET NULL` → jadi "Tanpa Album")
- **Edit album**: hanya pemilik (aturan kepemilikan sama seperti foto)

## Fitur Like, Komentar & Share
- **Like**: toggle suka/batal pada tiap foto, counter tampil di kartu galeri dan detail
- **Komentar**: tulis komentar pada halaman detail, tampil dengan avatar inisial + waktu relatif ("2 jam lalu")
- **Share**: tombol bagikan ke **WhatsApp** (`wa.me`), **Telegram** (`t.me/share`), **Instagram** (caption + link disalin ke clipboard lalu buka Instagram), dan **Salin Link**

## Fitur UI/UX
- Transisi halaman: konten fade-naik saat masuk fitur, fade keluar saat pindah/kembali (JS, tanpa loading page)
- Dashboard: Admin melihat statistik global & semua aktivitas; User melihat statistik pribadi - kedua role teruji
- Tema glassmorphism: kartu kaca (blur + saturasi) di atas foto ladang lavender malam + Milky Way (`assets/bg.jpg`, © Benjamin Preyre CC BY-NC-ND 2.0 - kredit di footer), font Inter
- Login ala referensi: judul gradasi "Welcome back!", input kaca pil, eye-toggle password, Remember Me fungsional, tombol "Coba akun demo"
- Ikon SVG inline stroke-based + brand icons (whatsapp/telegram/instagram)
- Halaman detail ala TikTok: media besar, action rail vertikal, panel komentar
- Responsive: rail vertikal berubah jadi baris horizontal di layar kecil
- Alert feedback, konfirmasi hapus, empty state komunikatif
