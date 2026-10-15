<?php
$pilihan = require BASE_PATH . '/config/pilihan.php';
$kontak = $pilihan['kontak'];
?>
<section class="hero" aria-labelledby="judul-hero">
  <h1 id="judul-hero">Mitra / Vendor PT Persona Prima Utama</h1>
  <p>Saluran informasi dan pendaftaran bagi calon mitra penyedia jasa dan barang PPU. Ajukan pendaftaran, unggah dokumen
     persyaratan, dan pantau proses pemeriksaan serta verifikasi Anda dalam satu tempat.</p>
  <div class="aksi">
    <a class="tombol tombol-terang" href="<?= h(url('daftar-vendor')) ?>"><?= ikon('user-plus') ?> Pendaftaran Mitra/Vendor</a>
    <a class="tombol tombol-garis" href="<?= h(url('masuk-vendor')) ?>">Sudah punya akun? Masuk</a>
  </div>
</section>

<h2>Bagaimana prosesnya</h2>
<div class="langkah-kartu">
  <div class="kartu">
    <span class="nomor"><?= ikon('user-plus') ?></span>
    <h3>1. Buat akun</h3>
    <p class="lembut">Daftar dengan email perusahaan. Akun dipakai untuk mengisi dan memantau pengajuan Anda.</p>
  </div>
  <div class="kartu">
    <span class="nomor"><?= ikon('upload') ?></span>
    <h3>2. Lengkapi data dan dokumen</h3>
    <p class="lembut">Isi profil perusahaan, jawab pertanyaan penilaian, dan unggah dokumen persyaratan. Draf dapat disimpan.</p>
  </div>
  <div class="kartu">
    <span class="nomor"><?= ikon('clipboard-check') ?></span>
    <h3>3. Pemeriksaan dan verifikasi</h3>
    <p class="lembut">Petugas memeriksa berkas, menjadwalkan verifikasi, lalu menetapkan klasifikasi. Statusnya dapat Anda pantau.</p>
  </div>
</div>

<h2>Kategori mitra / vendor <span class="lencana lencana-simulasi">contoh sementara</span></h2>
<p class="lembut">Bidang usaha yang dibutuhkan PPU. Daftar dan penjelasannya dirangkum dari halaman publik Vendor PPU dan masih perlu dikonfirmasi.</p>
<div class="kategori-grid">
  <?php require __DIR__ . '/partials/ilustrasi.php'; ?>
  <?php foreach ($pilihan['bidang_usaha'] as $b): ?>
    <div class="kategori">
      <div class="kategori-gambar"><?= ilustrasi($pilihan['ilustrasi_bidang'][$b] ?? 'lainnya') ?></div>
      <div class="kategori-teks">
        <h3><?= h($b) ?></h3>
        <p class="lembut"><?= h($pilihan['deskripsi_bidang'][$b] ?? '') ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid-dua" style="margin-top:16px">
  <section class="kartu" aria-labelledby="judul-kontak">
    <h2 id="judul-kontak"><?= ikon('help') ?> Butuh bantuan?</h2>
    <dl class="ringkasan">
      <dt>Telepon</dt><dd><?= h($kontak['telepon']) ?></dd>
      <dt>Email</dt><dd><a href="mailto:<?= h($kontak['email']) ?>"><?= h($kontak['email']) ?></a></dd>
      <dt>Alamat</dt><dd><?= h($kontak['alamat']) ?></dd>
      <dt>Situs</dt><dd><a href="<?= h($kontak['situs']) ?>" rel="noopener noreferrer" target="_blank">ptppu.co.id</a></dd>
    </dl>
    <p class="lembut">Kontak diambil dari halaman publik PPU; perlu dikonfirmasi sebelum dipakai pada sistem sebenarnya.</p>
  </section>
  <section class="kartu" aria-labelledby="judul-staf">
    <h2 id="judul-staf"><?= ikon('shield') ?> Untuk petugas PPU</h2>
    <p class="lembut">Petugas Perencanaan, Verifikasi, Berwenang, dan Administrator masuk melalui halaman petugas.
       Pada sistem sebenarnya, petugas masuk melalui SIAP.</p>
    <p><a class="tombol tombol-sekunder" href="<?= h(url('masuk-staf')) ?>">Masuk sebagai Petugas <?= ikon('arrow-right') ?></a></p>
    <p class="lembut">Akun demo untuk pengujian ada di <code>README.md</code>.
       <a href="<?= h(url('uji-koneksi')) ?>">Uji koneksi dan lingkungan</a></p>
  </section>
</div>
