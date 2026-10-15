<?php
declare(strict_types=1);

/**
 * Menu sidebar modul Vendor Management untuk petugas, mengikuti pola menu SIAP (butir teks tingkat atas).
 * Seluruh submodul digabung pada SATU halaman per modul (tab dan filter di dalam halaman):
 *   - Vendor Management : pengajuan, jadwal, penetapan, arsip, dan rekanan per level
 *   - Pengaturan Vendor : peran, pertanyaan, bobot, aturan, persyaratan, field
 *
 * Butir langsung : ['label', 'hal', 'hak', 'cocok', 'lencana_gabung'?]
 * Kelompok (bila diperlukan nanti) : ['label', 'item' => [butir...]] dengan panel submodul di samping.
 *
 * 'hak'            = permission yang diperlukan agar menu tampil (null = semua petugas).
 *                    Penjagaan sebenarnya tetap di backend (Router).
 * 'cocok'          = awalan nilai ?hal= yang menandai menu aktif.
 * 'jenis'          = nilai ?jenis= tambahan pada halaman konfigurasi.
 * 'lencana'        = kunci angka merah dari App\Services\LencanaMenuService (submodul).
 * 'lencana_gabung' = beberapa kunci yang dijumlahkan menjadi angka merah butir ini.
 */
return [
    ['label' => 'Vendor Management', 'hal' => 'staf/vendor', 'hak' => 'pengajuan.lihat',
        'cocok' => ['staf/dashboard', 'staf/vendor', 'staf/pengajuan', 'staf/jadwal', 'staf/penetapan', 'staf/arsip', 'staf/rekanan', 'staf/manual'],
        'lencana_gabung' => ['periksa', 'jadwal', 'penetapan']],

    ['label' => 'Pengaturan Vendor', 'hal' => 'admin/pengaturan', 'hak' => 'konfigurasi.kelola',
        'cocok' => ['admin/pengaturan', 'admin/peran', 'admin/konfigurasi']],
];
