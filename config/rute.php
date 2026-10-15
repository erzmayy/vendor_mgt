<?php
declare(strict_types=1);

use App\Controllers\AdminKonfigurasiController;
use App\Controllers\AdminPengaturanController;
use App\Controllers\AdminPeranController;
use App\Controllers\AuthController;
use App\Controllers\DokumenController;
use App\Controllers\HalamanController;
use App\Controllers\RegistrasiController;
use App\Controllers\StafKlasifikasiController;
use App\Controllers\StafPengajuanController;
use App\Controllers\StafRekananController;
use App\Controllers\StafVendorController;
use App\Controllers\UjiKoneksiController;
use App\Controllers\VendorPortalController;

/**
 * Tabel rute. Setiap rute menyatakan:
 *   kelas, metode      : controller yang dipanggil
 *   http               : GET atau POST (POST otomatis diperiksa CSRF)
 *   akses              : publik | login | staf | vendor
 *   hak (opsional)     : permission yang wajib dimiliki (lihat config/hak_akses.php)
 * Aturan ini ditegakkan di backend oleh App\Core\Router.
 */
return [
    'beranda'        => ['kelas' => HalamanController::class, 'metode' => 'beranda', 'http' => 'GET', 'akses' => 'publik'],
    'uji-koneksi'    => ['kelas' => UjiKoneksiController::class, 'metode' => 'tampil', 'http' => 'GET', 'akses' => 'publik'],

    'masuk-staf'     => ['kelas' => AuthController::class, 'metode' => 'formStaf', 'http' => 'GET', 'akses' => 'publik'],
    'masuk-staf/proses' => ['kelas' => AuthController::class, 'metode' => 'prosesStaf', 'http' => 'POST', 'akses' => 'publik'],
    'masuk-vendor'   => ['kelas' => AuthController::class, 'metode' => 'formVendor', 'http' => 'GET', 'akses' => 'publik'],
    'masuk-vendor/proses' => ['kelas' => AuthController::class, 'metode' => 'prosesVendor', 'http' => 'POST', 'akses' => 'publik'],
    'keluar'         => ['kelas' => AuthController::class, 'metode' => 'keluar', 'http' => 'POST', 'akses' => 'login'],

    // Portal vendor (hanya data milik akun yang sedang login)
    'daftar-vendor'  => ['kelas' => RegistrasiController::class, 'metode' => 'form', 'http' => 'GET', 'akses' => 'publik'],
    'daftar-vendor/proses' => ['kelas' => RegistrasiController::class, 'metode' => 'proses', 'http' => 'POST', 'akses' => 'publik'],
    'vendor/dashboard' => ['kelas' => VendorPortalController::class, 'metode' => 'dashboard', 'http' => 'GET', 'akses' => 'vendor', 'hak' => 'status.lihat_sendiri'],
    'vendor/formulir' => ['kelas' => VendorPortalController::class, 'metode' => 'formulir', 'http' => 'GET', 'akses' => 'vendor', 'hak' => 'formulir.kelola_sendiri'],
    'vendor/formulir/simpan' => ['kelas' => VendorPortalController::class, 'metode' => 'simpan', 'http' => 'POST', 'akses' => 'vendor', 'hak' => 'formulir.kelola_sendiri'],
    'vendor/dokumen/unggah' => ['kelas' => DokumenController::class, 'metode' => 'unggah', 'http' => 'POST', 'akses' => 'vendor', 'hak' => 'dokumen.unggah_sendiri'],
    'vendor/dokumen/hapus' => ['kelas' => DokumenController::class, 'metode' => 'hapus', 'http' => 'POST', 'akses' => 'vendor', 'hak' => 'dokumen.unggah_sendiri'],
    'vendor/kirim'   => ['kelas' => VendorPortalController::class, 'metode' => 'kirim', 'http' => 'POST', 'akses' => 'vendor', 'hak' => 'pengajuan.kirim_sendiri'],

    // Dokumen: pemeriksaan kepemilikan / hak dilakukan di dalam controller
    'dokumen/unduh'  => ['kelas' => DokumenController::class, 'metode' => 'unduh', 'http' => 'GET', 'akses' => 'login'],

    // Modul petugas
    'staf/dashboard' => ['kelas' => StafVendorController::class, 'metode' => 'dashboard', 'http' => 'GET', 'akses' => 'staf'],
    'staf/pengajuan' => ['kelas' => StafPengajuanController::class, 'metode' => 'daftar', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'pengajuan.lihat'],
    'staf/pengajuan/detail' => ['kelas' => StafPengajuanController::class, 'metode' => 'detail', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'pengajuan.lihat'],
    'staf/pengajuan/mulai-periksa' => ['kelas' => StafPengajuanController::class, 'metode' => 'mulaiPeriksa', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'dokumen.periksa'],
    'staf/dokumen/periksa' => ['kelas' => StafPengajuanController::class, 'metode' => 'periksaDokumen', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'dokumen.periksa'],
    'staf/jawaban/tandai' => ['kelas' => StafPengajuanController::class, 'metode' => 'tandaiJawaban', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'dokumen.periksa'],
    'staf/pengajuan/kembalikan' => ['kelas' => StafPengajuanController::class, 'metode' => 'kembalikan', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'pengajuan.kembalikan'],
    'staf/pengajuan/verifikasi-dokumen' => ['kelas' => StafPengajuanController::class, 'metode' => 'verifikasiDokumen', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'dokumen.periksa'],
    'staf/jadwal'    => ['kelas' => StafPengajuanController::class, 'metode' => 'jadwal', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'pengajuan.lihat'],
    'staf/jadwal/simpan' => ['kelas' => StafPengajuanController::class, 'metode' => 'jadwalSimpan', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'jadwal.kelola'],
    'admin/peran'    => ['kelas' => AdminPeranController::class, 'metode' => 'index', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'peran.kelola'],
    'admin/peran/simpan' => ['kelas' => AdminPeranController::class, 'metode' => 'simpan', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'peran.kelola'],


    // Tahap 6: hasil verifikasi, klasifikasi, TDR, arsip, pencatatan manual
    'staf/hasil/simpan' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'hasil', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'hasil.catat'],
    'staf/penetapan' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'antrean', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'klasifikasi.tetapkan'],
    'staf/penetapan/simpan' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'tetapkan', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'klasifikasi.tetapkan'],
    'staf/tdr/simpan' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'tdr', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'tdr.catat'],
    'staf/pengajuan/selesaikan' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'selesaikan', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'pengajuan.selesaikan'],
    'staf/pengajuan/arsipkan' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'arsipkan', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'pengajuan.selesaikan'],
    'staf/arsip' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'arsip', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'pengajuan.lihat'],
    'staf/manual' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'manualForm', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'pengajuan_manual.catat'],
    'staf/manual/simpan' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'manualSimpan', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'pengajuan_manual.catat'],
    'staf/dokumen/unggah' => ['kelas' => StafKlasifikasiController::class, 'metode' => 'unggahManual', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'pengajuan_manual.catat'],
    'staf/vendor' => ['kelas' => StafVendorController::class, 'metode' => 'index', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'pengajuan.lihat'],
    'staf/rekanan' => ['kelas' => StafRekananController::class, 'metode' => 'cari', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'vendor.lihat'],
    'staf/rekanan/ekspor' => ['kelas' => StafRekananController::class, 'metode' => 'ekspor', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'vendor.lihat'],
    'admin/pengaturan' => ['kelas' => AdminPengaturanController::class, 'metode' => 'index', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'konfigurasi.kelola'],
    'admin/konfigurasi' => ['kelas' => AdminKonfigurasiController::class, 'metode' => 'index', 'http' => 'GET', 'akses' => 'staf', 'hak' => 'konfigurasi.kelola'],
    'admin/konfigurasi/simpan' => ['kelas' => AdminKonfigurasiController::class, 'metode' => 'simpan', 'http' => 'POST', 'akses' => 'staf', 'hak' => 'konfigurasi.kelola'],
];
