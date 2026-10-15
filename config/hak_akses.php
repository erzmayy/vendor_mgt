<?php
declare(strict_types=1);

/**
 * Matriks peran dan hak akses (permission) prototype.
 * Dicek di backend oleh Router. Menyembunyikan menu saja tidak cukup.
 * Peran staf dipetakan manual lewat tbl_peran_user; peran "vendor" melekat pada akun vendor.
 */
return [
    'vendor' => [
        'label' => 'Vendor',
        'hak'   => [
            'profil.kelola_sendiri',
            'formulir.kelola_sendiri',
            'dokumen.unggah_sendiri',
            'pengajuan.kirim_sendiri',
            'status.lihat_sendiri',
        ],
    ],
    'perencanaan' => [
        'label' => 'Divisi Perencanaan',
        'hak'   => ['pengajuan.lihat', 'vendor.lihat', 'jadwal.kelola'],
    ],
    'verifikator' => [
        'label' => 'Petugas Verifikasi',
        'hak'   => [
            'pengajuan.lihat', 'vendor.lihat', 'dokumen.periksa', 'pengajuan.kembalikan',
            'hasil.catat', 'rekomendasi.lihat', 'pengajuan_manual.catat',
        ],
    ],
    'berwenang' => [
        'label' => 'Petugas Berwenang',
        'hak'   => [
            'pengajuan.lihat', 'vendor.lihat', 'rekomendasi.lihat', 'klasifikasi.tetapkan',
            'tdr.catat', 'pengajuan.selesaikan', 'pengajuan_manual.catat', 'log.lihat',
        ],
    ],
    'admin' => [
        'label' => 'Administrator',
        'hak'   => [
            'pengajuan.lihat', 'vendor.lihat', 'rekomendasi.lihat', 'pengajuan_manual.catat',
            'log.lihat', 'konfigurasi.kelola', 'peran.kelola', 'akun.kelola',
        ],
    ],
];
