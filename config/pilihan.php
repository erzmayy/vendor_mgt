<?php
declare(strict_types=1);

/**
 * Daftar pilihan isian formulir dan konten informasi beranda.
 * CONTOH SEMENTARA, bukan ketentuan resmi PPU: bidang usaha, deskripsi, dan kontak diambil dari
 * halaman publik https://ptppu.co.id/vendor/ (dirangkum dengan kalimat sendiri) dan perlu dikonfirmasi PPU.
 */
return [
    'bentuk_badan_usaha' => ['PT', 'CV', 'UD', 'Koperasi', 'Yayasan', 'Perorangan', 'Lainnya'],

    'bidang_usaha' => [
        'Seragam / desain',
        'Pelatihan dan sertifikasi',
        'Alat tulis kantor / perlengkapan kantor',
        'Pengiriman dokumen dan logistik',
        'Konsultan ISO / manajemen',
        'Konsultan kesehatan',
        'Konsultan keuangan dan pajak',
        'Pemeliharaan dan perbaikan teknik',
        'Bahan kimia dan kebersihan',
        'Pekerjaan sipil dan elektrikal',
        'Pemeliharaan kendaraan',
        'Lainnya',
    ],

    // Penjelasan singkat tiap bidang usaha (kunci harus sama dengan daftar di atas).
    'deskripsi_bidang' => [
        'Seragam / desain'                         => 'Kebutuhan seragam, pakaian kerja, serta jasa desain dan penjahitan.',
        'Pelatihan dan sertifikasi'                => 'Pelatihan, sertifikasi, dan pengembangan kompetensi tenaga kerja.',
        'Alat tulis kantor / perlengkapan kantor'  => 'Pengadaan alat tulis dan perlengkapan kantor.',
        'Pengiriman dokumen dan logistik'          => 'Pengiriman dokumen dan barang, dalam kota maupun antarpulau.',
        'Konsultan ISO / manajemen'                => 'Konsultasi ISO, strategi, operasional, keuangan, pemasaran, dan sumber daya manusia.',
        'Konsultan kesehatan'                      => 'Jasa konsultasi kesehatan, termasuk deteksi dini dan pemantauan pengobatan.',
        'Konsultan keuangan dan pajak'             => 'Konsultasi investasi, utang, perhitungan dan pelaporan pajak, serta perencanaan keuangan.',
        'Pemeliharaan dan perbaikan teknik'        => 'Perbaikan dan pemeliharaan mekanikal, elektrikal, dan sipil, termasuk instalasi dan peningkatan.',
        'Bahan kimia dan kebersihan'               => 'Produk pembersih, pembersih khusus, dan disinfektan.',
        'Pekerjaan sipil dan elektrikal'           => 'Pekerjaan konstruksi bangunan dan sistem kelistrikan gedung.',
        'Pemeliharaan kendaraan'                   => 'Perawatan berkala dan perbaikan kendaraan.',
        'Lainnya'                                  => 'Bidang usaha lain yang belum tercantum.',
    ],

    // Nama ilustrasi tiap bidang usaha (simbol "il-<nama>" pada app/Views/partials/ilustrasi.php).
    'ilustrasi_bidang' => [
        'Seragam / desain'                         => 'seragam',
        'Pelatihan dan sertifikasi'                => 'pelatihan',
        'Alat tulis kantor / perlengkapan kantor'  => 'atk',
        'Pengiriman dokumen dan logistik'          => 'logistik',
        'Konsultan ISO / manajemen'                => 'iso',
        'Konsultan kesehatan'                      => 'kesehatan',
        'Konsultan keuangan dan pajak'             => 'keuangan',
        'Pemeliharaan dan perbaikan teknik'        => 'teknik',
        'Bahan kimia dan kebersihan'               => 'kebersihan',
        'Pekerjaan sipil dan elektrikal'           => 'sipil',
        'Pemeliharaan kendaraan'                   => 'kendaraan',
        'Lainnya'                                  => 'lainnya',
    ],

    // Kontak PPU (halaman publik ptppu.co.id/vendor/). Perlu dikonfirmasi sebelum dipakai di sistem sebenarnya.
    'kontak' => [
        'telepon' => '021-83790597',
        'email'   => 'ppu-pusat@ptppu.co.id',
        'alamat'  => 'Jl. Dr. Sahardjo No. 60 E-G, Jakarta Selatan 12970',
        'situs'   => 'https://ptppu.co.id/',
    ],
];
