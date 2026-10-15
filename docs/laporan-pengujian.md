# Laporan Pengujian Prototype Vendor Management PPU

Dokumen ini merangkum pengujian Tahap 7. Prototype ini **mandiri dan lokal**, bukan bagian dari website PPU
maupun SIAP PPU, dan seluruh aturan klasifikasi, persyaratan, dan TDR adalah **contoh simulasi**.

## Cara menjalankan

```bash
php -S 127.0.0.1:8099 -t public            # terminal 1
php tests/uji_tahap3.php   http://127.0.0.1:8099
php tests/uji_tahap4.php   http://127.0.0.1:8099
php tests/uji_tahap5.php   http://127.0.0.1:8099
php tests/uji_tahap6.php   http://127.0.0.1:8099
php tests/uji_penilaian.php http://127.0.0.1:8099
php tests/uji_tahap7.php   http://127.0.0.1:8099
```

Seluruh uji berjalan lewat HTTP seperti pengguna sebenarnya (cookie, CSRF, upload), lalu memeriksa
database. Data uji memakai email `@uji.test` dan dibersihkan otomatis. Basis data lokal Laragon (MySQL 8.0.30), PHP 8.2.12.

## Hasil

| Suite | Cakupan | Pemeriksaan | Hasil |
|---|---|---|---|
| `uji_tahap3` | Login, session, CSRF, hak akses dasar, audit | 72 | Lulus |
| `uji_tahap4` | Registrasi, profil, formulir, upload aman, kepemilikan data, status | 76 | Lulus |
| `uji_tahap5` | Dashboard, daftar/filter, pemeriksaan, pengembalian, jadwal, peran | 75 | Lulus |
| `uji_tahap6` | Hasil verifikasi, rekomendasi, penetapan, TDR, arsip, manual, konfigurasi | 106 | Lulus |
| `uji_penilaian` | Skor otomatis (berkas + pertanyaan + verifikasi), bobot, pertanyaan | 59 | Lulus |
| `uji_tahap7` | Matriks akses, CSRF/metode, XSS, SQL injection, header, race condition, galat | 217 | Lulus |
| **Total** | | **605** | **Lulus** |

## Yang diuji pada Tahap 7

| Area | Pemeriksaan |
|---|---|
| Matriks akses | 19 halaman x 6 aktor (anonim, vendor, perencanaan, verifikator, berwenang, admin); nilai harapan ditulis tangan dari ketentuan |
| CSRF | Setiap rute POST ditolak (400) tanpa token, untuk 4 jenis aktor |
| Metode HTTP | GET ke rute POST dan POST ke rute GET = 405 |
| XSS tersimpan | Payload pada seluruh isian bebas di satu alur penuh, diperiksa pada 15 halaman |
| SQL injection | 9 payload pada seluruh parameter pencarian, filter, id, login, dan registrasi |
| Input aneh | Parameter array, nilai array pada POST, kata sandi 300 KB, unggahan ke pengajuan terkunci |
| Header dan cookie | X-Frame-Options, nosniff, CSP, Referrer-Policy, cookie HttpOnly + SameSite |
| Akses berkas langsung | `.env`, `app/`, `config/`, `database/`, `tests/`, `storage/` tidak dapat dibuka lewat web (404) |
| Sesi | Sesi lama tidak berlaku setelah logout; ID session berganti saat login |
| Permintaan bersamaan | 3 pemeriksaan serempak dan 2 penjadwalan serempak menghasilkan satu perpindahan |
| Galat tak terduga | Halaman 500 umum + kode rujukan; rincian hanya di log; kata sandi tidak tercatat |

## Temuan dan perbaikan selama pengujian

| # | Temuan | Perbaikan |
|---|---|---|
| 1 | Galat tak tertangani menampilkan pesan PHP lengkap dengan path server | `Galat::pasang()`: halaman umum + kode rujukan, rincian ke `storage/logs` |
| 2 | Parameter berbentuk array (`?hal[]=x`) memicu peringatan PHP | `Masukan::bersihkan()` membuang nilai non-skalar |
| 3 | Unggahan `berkas[]` (array) dapat memicu TypeError | Penolakan eksplisit di `DokumenService` |
| 4 | Nama petugas tidak tampil pada jadwal/riwayat (variabel `$nama` pada `View` bertabrakan dengan nama view) | `View` dibuat ulang dengan parameter terisolasi |
| 5 | Tanggal TDR "hari ini" dianggap masa depan karena jam ikut terbaca | Format `!Y-m-d` |
| 6 | Filter arsip rusak (blok kode ganda) tersembunyi di balik kode HTTP 200 | Diperbaiki; klien uji kini mendeteksi galat PHP pada isi respons |

## Keterbatasan yang diketahui

- **Mode `sesi_siap` dan `siap_lintas`** hanya diuji logikanya terhadap tabel prototype, belum terhadap SIAP asli.
- **Batas waktu sesi tidak aktif (30 menit)** tidak diuji otomatis.
- **Cookie `Secure` dan HTTPS** tidak diuji (lingkungan lokal HTTP).
- **Pemindaian virus pada berkas unggahan** tidak ada. Validasi hanya tipe, isi, ukuran, dan nama.
- **Verifikasi email dan lupa kata sandi** tidak ada pada prototype.
- Pembatasan percobaan hanya untuk login dan pendaftaran; endpoint lain belum dibatasi lajunya.
- Uji beban dan performa belum dilakukan; kueri daftar memakai indeks pada status, sumber, dan vendor.
- Tampilan diperiksa secara visual pada dashboard, detail pengajuan, dan tampilan HP; belum diuji lintas peramban.
