# Follow-up Audit #001 - Perbaikan Disetujui "SEMUA"
Tanggal: 2026-09-12 · Semua 9 temuan diperbaiki dan diverifikasi ulang dengan Playwright (desktop 1280 + mobile 375 touch) dan MySQL.

| No | Temuan | Perbaikan | Bukti verifikasi |
|----|--------|-----------|------------------|
| 1 | R-03 overflow detail mobile (121px) | `.action-rail` mobile kini `flex-wrap: wrap` + center | Re-audit: overflow detail = 0, offenders = 0 |
| 2 | R-03 tombol kartu terpotong | `.photo-actions { flex-wrap: wrap; row-gap: 6px }` | Re-audit: tak ada `.action-btn` di luar viewport |
| 3 | R-02 em dash di UI | title jadi `X · NoxGallery`; opsi jadi "Tanpa Album"; caption share " - NoxGallery"; komentar kode ikut dibersihkan | Sweep `[char]0x2014` di SEMUA php/css/sql md ter-deploy: **0 hit**; browser: "Login · NoxGallery", opsi tanpa em dash |
| 4 | R-03 tap target <44px | `@media (pointer: coarse)`: btn-icon 44×44, action-btn min 44, nav-link/rail/pw-toggle/toast-close/search-clear dibesarkan (desktop tetap ramping) | Re-audit context touch 375px: `.btn-icon` **44×44**, `.action-btn` **50×44** |
| 5 | R-10 glass tanpa alasan tertulis | Alasan + dosis ditulis di `DESIGN.md` (arah pemilik, bukan default) | DESIGN.md §Tema |
| 6 | R-04 ikon Lucide tanpa alasan | Alasan relevansi per-ikon ditulis di `DESIGN.md` | DESIGN.md §Ikon |
| 7 | C-1 bahasa campur | Login/register disatukan ke Indonesia ("Selamat datang kembali!", "Masuk", "atau", "Ingat saya", "Daftar", dsb.) | Re-audit: tidak ada string Inggris tersisa di auth |
| 8 | R-20 brand terbelah | UI disatukan **NoxGallery**: `<title>`, navbar wordmark, caption share, installer heading | Re-audit: title = "Login · NoxGallery" |
| 9 | R-37 arah tidak tertulis | `DESIGN.md` dibuat (identitas, warna terukur, tipografi beralasan, dosis glass, motion) | File ada di root project |

## Catatan penting
- Saat perbaikan ditemukan **beberapa hasil polish pass sebelumnya kembali ke versi lama** (`.action-btn` desktop, blok `:active`, `aria-pressed`, `aria-label` logout/search-clear) - kemungkinan folder project tertimpa dari zip backup (terlihat ada `galeri-foto-mysql.zip` / `galeri-foto-php.zip` di Downloads tanggal 10/09). Semuanya **diterapkan ulang** dan kini terverifikasi ada.
- Elemen "ghost 415px" di hasil scan = anchor judul foto yang di-ellipsis (`text-overflow`) - perilaku benar, bukan overflow.
- `like_toggles:false` di harness = race pembacaan WebKit; diverifikasi manual via MySQL: like 1 -> unlike 0 -> like 1, dan atribut `aria-pressed` ikut status.

## Gate status (Hard Gate)
- R-02 PASS (0 em dash di kode/UI ter-deploy)
- R-03 PASS (overflow 0 di semua halaman mobile; tap 44px di touch)
- R-25 PASS (semua pasangan warna terukur; tidak ada perubahan warna)
- R-32/26/35 PASS (0 console error; click-through penuh; fokus terlihat)
- R-17/18/24/28/33/34/36/38 PASS (tidak berubah dari audit awal)
- Liveliness & Purpose-Gate: alasan tertulis di DESIGN.md (R-31 terpenuhi)

Laporan akhir: project LOLOS Delivery Gate antislop.
