# DESIGN.md - NoxGallery
Arah desain disepakati pemilik produk (siswa) bersama referensinya; file ini alasan tertulis sesuai aturan antislop (R-37, R-31, R-10, R-04).

## Identitas
- Nama aplikasi: **NoxGallery** (dari logo resmi pemilik: wordmark "NOXGALLERY" + matahari terbit ungu). Semua titik UI memakai nama ini.
- Bahasa UI: **Indonesia** konsisten (produk ujian UKK Indonesia).
- Karakter: galeri foto sosial-malam, tenang, elegan. DIAL: ENERGY 2 / RHYTHM 2 / MOTION 2.

## Tema: Glass Lavender Night (glassmorphism)
- **Kenapa glass (R-10):** Permintaan eksplisit pemilik ("ganti tema glass") + logo NoxGallery bernuansa glass ungu; kartu transparan di atas foto membuat foto terasa konten utama. Ini arah pemilik, bukan default AI.
- **Dosis:** blur 14px + saturate 1.15, permukaan putih transparan 4-10%. Elemen data (kartu foto) memakai permukaan paling tipis agar foto tidak redup; navbar/auth memakai panel lebih pekat karena teks di atasnya.
- **Latar:** foto ladang lavender malam (assets/bg.jpg, © Benjamin Preyre CC BY-NC-ND 2.0, kredit di README) + overlay gelap gradasi. Teks tidak pernah duduk langsung di foto tanpa overlay.

## Warna (token di style.css :root)
- Base malam: #0b0620 → #3a2364 (gradasi foto)
- Teks: #f2eefb / #c9c1e0 / #8f86ad (ketiganya diukur lolos kontras AA 4.87-16:1 dengan contrast-check.py)
- Fungsi: merah #f87171 (hapus), hijau #4ade80 (sukses), kuning #facc15 (peringatan) - hanya untuk status, bukan dekorasi
- Netral: putih penuh hanya untuk CTA utama + ikon logo. Maksimal 1 aksen per layar.

## Tipografi
- **Inter** (bukan karena default AI: referensi login pemilik memakai geometric sans serupa, dan ui-ux-pro-max merekomendasikan pasangan Space Grotesk/Inter untuk produk sosial gelap. Inter dipilih untuk body karena readability tabular; judul pakai weight 600-700 + letter-spacing ketat.)
- Ukuran dasar 15px, label 12.5-13.5px, judul halaman 25px.

## Ikon (R-04)
- Set stroke-based gaya Lucide, ditulis manual di includes/icons.php.
- **Kenapa set ini:** semua ikon dipilih karena relevan konten (folder=album, heart=like, send=kirim komentar, spark=brand), stroke 2px konsisten satu keluarga; bukan dipakai karena "khas AI".
- Ikon brand (WhatsApp/Telegram/Instagram) pakai versi fill resmi simple-icons agar dikenali.

## Layout & Radius
- Radius bertingkat: kartu besar 18-24px, kontrol 9-10px, chip/pill 999px (pill khusus tombol CTA dan badge, bukan semua elemen).
- Rail aksi ala media sosial vertikal di desktop, wrap rata tengah di touch.
- Tap target touch: min 44px via @media (pointer: coarse) - tampilan mouse tidak diubah.

## Motion
- Tujuan gerakan = orientasi, bukan hiasan: page-in fade naik 380ms (datang dari atas), page-leaving fade 170ms (pergi terasa), kartu stagger 55ms (membaca urutan baru→lama), like heart scale .active.
- Semua patuh prefers-reduced-motion.

## Banned dan Pesan (fitur admin-notifikasi)
- Ban wajib disertai alasan (kolom users.alasan_ban + tabel notifications): alasan tampil di banner login, kartu Pesan, dan baris daftar pengguna.
- Menu Pesan + badge jumlah unread di navbar supaya keputusan admin tidak luput; seluruh kartu memakai token glass yang sama (--surface, --border, --radius), bukan sistem visual kedua.
- Guard ban di config.php (satu query ter-index per halaman): sesi aktif milik akun yang dibanned langsung ditendang ke login dengan banner alasan persisten.
- Dialog alasan memakai <dialog> native: Esc dan klik backdrop menutup (R-26); kontras terukur AA (merah #f87171 di atas #171029 = 6.64:1).
- Proteksi: admin tidak bisa mem-banned diri sendiri atau sesama admin (anti lockout), server-side authoritative.

## Filter pencarian (index)
- Chip urutan + dropdown album + chip Foto Saya: semua pakai token kaca yang sama
  (surface, border, pill 999px, aksen ungu hanya untuk aktif) supaya satu keluarga dengan search-bar.
- Semua chip adalah link dengan URL yang saling menjaga parameter (search, sort, album, mine)
  sehingga hasil bisa dibagikan dan tombol kembali browser tidak kehilangan filter.
- Like dari tampilan terfilter kembali ke URL filter yang sama (whitelist di like.php, anti open-redirect).
- Sort populer/diskusi dihitung di query (subquery count), bukan N+1 per kartu: urutan akurat dan cepat.
- Tap minimal 44px via blok pointer:coarse; chip aktif terukur kontras 10.40:1 (AA).
- Dropdown album custom (bukan select bawaan): popup select dikontrol OS/browser dan bentrok
  dengan tema, jadi diganti tombol chip + panel kaca berisi link (listbox, Esc/klik-luar/panah keyboard).

## Menu Pengaturan
- Satu halaman tiga kartu (Profil, Keamanan, Akun saya): memakai komponen yang sudah ada
  (dash-card, field/input, pw-wrap + eye-toggle global, user-row, glass-dialog) tanpa gaya baru.
- Ikon sliders (tiga garis + titik geser) untuk tombol gear: metafora baku kontrol penyesuaian,
  konsisten dengan ikon stroke set lainnya (alasan relevansi R-04).
- Hapus akun: dialog konfirmasi password + admin dikecualikan (anti lockout, server-side);
  file fisik ikut dihapus karena CASCADE DB tidak menyentuh filesystem.

## Foto profil
- Alasan: avatar inisial terbaca sebagai placeholder generik; foto asli memberi identitas akun.
- Implementasi memakai pola upload yang sama seperti tambah.php (ekstensi + 5 MB + getimagesize),
  nama file avatar_{id}_{waktu}.ext, file lama dihapus saat ganti, file ikut terhapus saat hapus akun.
- Tampil lewat satu helper userAvatar() (foto atau fallback inisial) di 7 titik: chip navbar,
  daftar pengguna, komentar detail, komentar dashboard, info pengunggah, pratinjau pengaturan, form komentar.
- Ikon sliders untuk tombol gear: metafora baku kontrol penyesuaian, sekeluarga stroke dengan ikon lain (R-04).

## PIN keamanan ala DANA
- Alasan: password jarang diganti dan panjang; PIN 6 digit memberi konfirmasi cepat
  untuk aksi destruktif (hapus akun, hapus foto, banned) tanpa mengorbankan keamanan.
- Widget 6 kotak (satu digit per kotak, keyboard angka via inputmode) meniru pola dompet
  digital yang sudah dikenal pengguna; nilai gabungan di hidden field, server validasi
  regex 6 digit + bcrypt (JS hanya membantu, bukan penjaga).
- Hanya aksi destruktif yang dikunci PIN; memulihkan (unban) tetap bebas agar alur
  darurat tidak terkunci. Lupa PIN tidak mengunci akun (login tetap pakai password).

## Perbaikan pill info akun
- Pill level kini netral abu (sama seperti pill Admin/Anda): hijau hanya untuk status
  butuh perhatian (Banned, badge unread), bukan untuk label info.
- `.pill` diberi width:fit-content karena grid/flex me-blockify inline-flex sehingga
  pill bisa melar selebar kolom (kasus bar hijau di info akun).

## Sesi: opt-in ingat-saya + idle-timeout 30 menit
- Alasan: login tanpa password hanya boleh terjadi atas persetujuan eksplisit (checkbox
  tidak dicentang default); sesi yang ditinggal tetap harus mati sendiri.
- Idle dicatat di kolom users.last_active setiap request (sliding) dan perbandingan
  kadaluwarsa dihitung di SQL (DATE_SUB) agar kebal beda zona waktu php.ini antar mesin.
  Ditemukan saat verifikasi: php.ini mesin ini Berlin vs database WIB sehingga hitungan
  PHP selalu negatif; diperbaiki + zona PHP dikunci Asia/Jakarta (sekaligus memperbaiki
  tampilan tanggal bergabung dan waktu relatif di seluruh aplikasi).
- Kadaluwarsa = keluar total: sesi dibuang, token ingat-saya dicabut di DB + cookie,
  lalu toast alasan di login. Kolom terakhir-aktif tampil di Kelola Pengguna.

## Halaman profil ala TikTok
- Alasan: tiap akun butuh etalase (bio + statistik + koleksi tab) tanpa meniru angka
  follower (tak ada sistem follow, angka palsu dilarang R-17).
- Statistik hanya yang real: postingan, suka diterima, album. Tab Disukai/Disimpan
  khusus pemilik (semi-privat); profil banned tertutup untuk non-admin.
- Tombol simpan (bookmark) di kartu + rail; posting-ulang di share-sheet (rumah alaminya
  ala TikTok) agar action-bar kartu tidak sesak. Ikon bookmark: metafora baku simpanan (R-04).
- Grid thumbnail seragam 3 kolom (object-fit cover) + empty state per tab (R-27).
  Di layar kecil tab menjadi ikon saja seperti TikTok (aria-label tetap ada).
- Relasi saved/reposts berkunci ganda + CASCADE dua arah: ikut hilang saat akun/foto dihapus.

## Follow + centang biru
- Alasan: profil butuh bukti sosial (pengikut) dan kepercayaan (verifikasi admin),
  keduanya pola baku aplikasi sosial yang diminta pemilik.
- Statistik jujur tanpa follower palsu: Postingan, Pengikut, Mengikuti, Suka.
  Tab Disukai/Disimpan tetap semi-privat; daftar pengikut memakai baris user yang ada.
- Centang biru = lingkaran biru + cek putih (satu-satunya aksen biru; ungu = aksi,
  merah = bahaya, hijau = perhatian). Kontras putih di atas #0284c7 terukur 4.10:1.
- Badge adalah pemberian admin (verify_action, bukan klaim sendiri; anti self-verify),
  tampil di semua nama. PIN mengunci aksi destruktif; memulihkan (unban) bebas.
- Relasi follows berkunci ganda + CASCADE: ikut hilang saat akun dihapus.
- Filter tahap 2 (waktu, pengunggah, disukai, disimpan): hanya dimensi berdata real
  (tanggal, relasi like/save) yang ditambah; bar dipecah 3 grup berlabel agar tidak sesak.
  Chip waktu bersifat toggle, dropdown kedua memakai komponen fselect + JS class-based yang sama.

## Gelombang 5 fitur (notif interaksi, hapus komen, semat, tagar, views+paginasi)
- Notifikasi komentar/suka melengkapi Pesan (kecuali aksi sendiri); hapus komentar boleh
  pemilik/admin, tanpa notifikasi (standar umum; foto yang dihapus tetap memberi tahu).
- Semat profil: toggle milik pemilik, maks 3, badge di grid; ikon pin gaya stroke
  set lainnya, kebenaran path dibuktikan via getBBox (R-04).
- Tagar diekstrak saat tampil (tanpa tabel sinkron yang bisa basi), batas kata presisi
  (#senja cocok, #senjax tidak), baris populer hanya bila tag ada.
- Views +1 tiap buka detail; sort Dilihat; paginasi 12/halaman dengan total jujur di pill
  dan tombol muat-lebih (sisa N); ganti filter selalu kembali ke halaman 1, like/simpan
  kembali ke halaman yang sama (whitelist anti open-redirect).

## Toast diselaraskan
- Alasan: toast lama abu netral + ikon sukses putih-hitam, terasa tempelan OS di atas
  tema ungu kaca. Kini: latar ungu solid + blur, radius kartu, ikon tint per tipe
  (sama seperti kartu Pesan), ikon rata atas agar teks 2 baris tetap rapi.
- Celah 18px antar kartu bertumpuk (main > .dash-card + .dash-card); grid dan kolom
  sisi tak tersentuh karena sudah punya gap sendiri.

## Bottom navigation ala TikTok (HP <=640px)
- Alasan: navigasi utama pindah ke jempol (bawah): Galeri, Album, tombol +,
  Pesan (badge ikut), Profil (avatar, bukan ikon generik); top bar sisa brand + alat.
  Slot + label 10px muat 320px (terukur 62px/slot).
- Tombol + aksen ungu menonjol sebagai aksi utama; indikator garis-atas = aktif.
  Alat admin (Dashboard, Pengguna) jadi ikon only-mobile di top bar agar tak hilang.
- Safe-area poni (env) + body padding + toast diangkat di atas nav; toast tetap terlihat.
- Desktop tidak berubah (bottom nav display:none, dibuktikan regresi 1280px).

## Penataan mobile gelombang terakhir
- Latar bintang dipaksa position:fixed (dipaku, tidak bergeser) + kartu/nav dipadatkan
  (alpha .85-.93) supaya tidak terlihat buram tembus; desktop tetap.
- Chip filter tidak lagi menumpuk: tiap grup satu baris bisa digeser (scroll-x). Di hosting,
  quirks mode membuat stretch kolom gagal, jadi lebar kunci eksplisit width:100%.
- Aksi kartu diringkas ala TikTok: like, komentar, share, dan menu "..." (unduh/simpan/edit/hapus)
  berisi panel kaca; di desktop panel melebur jadi deretan tombol seperti semula (display:contents).
