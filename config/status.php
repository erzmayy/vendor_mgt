<?php
declare(strict_types=1);

/**
 * Label, warna lencana, dan posisi pada indikator progres untuk setiap status pengajuan.
 * Urutan langkah progres (1..6): Draf, Diajukan, Pemeriksaan, Verifikasi, Klasifikasi, Selesai.
 * Aturan perpindahan status ada di App\Services\WorkflowService.
 */
return [
    'langkah' => ['Draf', 'Diajukan', 'Pemeriksaan', 'Verifikasi', 'Klasifikasi', 'Selesai'],
    'status' => [
        'draf'                  => ['label' => 'Draf',                   'warna' => 'abu',    'langkah' => 1],
        'diajukan'              => ['label' => 'Diajukan',               'warna' => 'biru',   'langkah' => 2],
        'sedang_diperiksa'      => ['label' => 'Sedang diperiksa',       'warna' => 'biru',   'langkah' => 3],
        'perlu_perbaikan'       => ['label' => 'Perlu perbaikan',        'warna' => 'kuning', 'langkah' => 3],
        'dokumen_terverifikasi' => ['label' => 'Dokumen terverifikasi',  'warna' => 'biru',   'langkah' => 4],
        'terjadwal'             => ['label' => 'Verifikasi terjadwal',   'warna' => 'biru',   'langkah' => 4],
        'terverifikasi'         => ['label' => 'Telah diverifikasi',     'warna' => 'biru',   'langkah' => 4],
        'ada_rekomendasi'       => ['label' => 'Menunggu penetapan',     'warna' => 'biru',   'langkah' => 5],
        'terklasifikasi'        => ['label' => 'Telah diklasifikasi',    'warna' => 'hijau',  'langkah' => 5],
        'tdr_tercatat'          => ['label' => 'Proses TDR dicatat',     'warna' => 'hijau',  'langkah' => 5],
        'selesai'               => ['label' => 'Selesai',                'warna' => 'hijau',  'langkah' => 6],
        'diarsipkan'            => ['label' => 'Diarsipkan',             'warna' => 'abu',    'langkah' => 6],
    ],
];
