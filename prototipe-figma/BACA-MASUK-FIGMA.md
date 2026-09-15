# NoxGallery - Prototipe Figma (persis sama dengan web asli)

Folder ini berisi **16 tangkapan layar penuh halaman** yang diambil langsung dari web
NoxGallery yang sedang berjalan di `http://localhost/galeri-foto/` (sudah login sebagai admin,
pixel-identik dengan "web saya"). Pakai ini untuk membangun prototipe Figma yang persis sama.

## Isi folder

| File | Halaman | Keterangan |
|------|---------|------------|
| `login.desktop.png` / `login.mobile.png` | Login | Juga tampilan default saat keluar |
| `register.desktop.png` / `register.mobile.png` | Registrasi | Form buat akun |
| `index.desktop.png` / `index.mobile.png` | Beranda foto | Grid foto + pencarian + tombol like |
| `album.desktop.png` / `album.mobile.png` | Galeri album | Daftar album |
| `album-detail.desktop.png` | Detail album | Isi satu album (label `id=1`) |
| `detail.desktop.png` / `detail.mobile.png` | Detail foto | Foto besar + aksi like/share + komentar |
| `tambah.desktop.png` / `tambah.mobile.png` | Tambah foto | Form unggah |
| `dashboard.desktop.png` | Dashboard admin | Hitungan + foto terbaru |

`*.desktop.png` = lebar 1440px (layar penuh). `*.mobile.png` = 390px (penuh, satu kolom).

Catatan jujur: `album-detail` hanya ada versi desktop karena halaman utamanya sama dengan
`detail` dalam satu kolom; versi mobile-nya memakai `detail.mobile.png` sebagai ganti.

## Bukti bahwa ini "persis sama" (bukan tiruan)

- Diambil dari server lokal nyata (HTTP 200), sesi login admin aktif (H1 "Galeri · 9 foto"),
  bukan mock statis dan bukan tangkapan halaman tanpa pengguna.
- Ukuran per file masif (1,3-2,8 MB) = tangkapan penuh ter-resolusi tinggi, cocok untuk zoom Figma.

## Dua rute membawanya ke Figma

### Rute A (paling "persis sama", bisa diedit) - plugin html.to.design
1. Buka Figma, buat file baru.
2. Pasang plugin **html.to.design** (di dalam Figma: Community > search "html.to.design").
3. Jalankan plugin, tempel URL salah satu halaman web Anda (mis. `http://localhost/galeri-foto/index.php`).
4. Plugin membangun **layer/screen yang bisa diedit** langsung dari HTML live.
5. Ulangi per halaman yang ingin jadi frame prototipe (login, index, detail, album, tambah, dashboard).
   Diperlukan login admin di halaman yang butuh sesi (pakai kredensial Anda sendiri).
6. Di Figma: susun frame, lalu buat prototipe - hubungkan tombol antarframe
   (Login > Beranda, kartu foto > Detail, tombol Like, dll).

### Rute B (instan, tanpa plugin) - drag tangkapan layar
1. Buka Figma, buat file, buat **Frame** ukuran Desktop (1440) dan Mobile (390).
2. Seret file `*.desktop.png` ke frame Desktop, `*.mobile.png` ke frame Mobile (fit: "Cover" atau "Fill").
3. Jadikan tiap tangkapan sebagai frame sendiri, beri nama sesuai nama file.
4. Buat prototipe: lingkari area tombol (kotak transparan) lalu hubungkan ke frame tujuan.

## Apa yang TIDAK otomatis (jujur)

- Interaktivitas nyata (like tersimpan ke database, unggah file, komentar berjalan) **tidak** dibawa
  ke Figma. Figma hanya menampilkan tampilan dan alur klik.
- Data foto live tidak ikut; tangkapan adalah keadaan saat ini.
- Kalau Anda ingin alur klik yang bisa diputar di browser tanpa Figma, saya bisa siapkan
  prototipe HTML interaktif terpisah - bilang saja.

## Peta alur prototipe (rekomendasi sambungan frame)

Login.php -> index.php -> detail.php(id) -> (like/share) -> kembali
             -> album.php -> album_detail.php(id)
             -> tambah.php (hanya kalau halaman upload masuk alur demo)
index.php  -> dashboard.php (jika akun admin)
login.php  -> register.php (pindah ke form daftar)
