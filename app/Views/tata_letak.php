<?php
/** @var string $konten @var string $judul @var ?array $pengguna */
use App\Core\Auth;
use App\Core\Csrf;
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f2742">
<title><?= h($judul) ?> | Vendor Management PPU</title>
<link rel="stylesheet" href="assets/css/app.css">
<script src="assets/js/app.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/partials/ikon.php'; ?>
<a class="lewati" href="#konten">Lewati ke konten utama</a>
<div class="banner-prototype">
  <strong>PROTOTYPE LOKAL</strong> &mdash; bukan bagian dari website PPU atau SIAP PPU, belum terintegrasi.
  Persyaratan, klasifikasi, dan TDR adalah <strong>simulasi</strong>.
</div>
<header class="bilah-atas">
  <div class="isi">
    <a class="merek" href="<?= h(url('beranda')) ?>">
      <img src="assets/img/logo-header.png" alt="" class="logo">
      <span class="nama-merek"><strong>Vendor Management</strong><small>PT Persona Prima Utama</small></span>
    </a>
    <button type="button" class="tombol tombol-sekunder tombol-ikon pemicu-menu" aria-expanded="false" aria-label="Buka menu">
      <?= ikon('menu') ?>
    </button>
    <nav class="navigasi" aria-label="Navigasi utama">
      <?php if ($pengguna !== null): ?>
        <a href="<?= h(url($pengguna['tipe'] === 'vendor' ? 'vendor/dashboard' : 'staf/dashboard')) ?>">Dashboard</a>
        <span class="pengguna"><?= h($pengguna['nama']) ?> &middot; <?= h(implode(', ', array_map([Auth::class, 'labelPeran'], $pengguna['peran']))) ?></span>
        <form class="sebaris" method="post" action="<?= h(url('keluar')) ?>">
          <?= Csrf::field() ?>
          <button class="tombol tombol-sekunder tombol-kecil" type="submit"><?= ikon('logout') ?> Keluar</button>
        </form>
      <?php else: ?>
        <a href="<?= h(url('beranda')) ?>">Beranda</a>
        <a href="<?= h(url('masuk-staf')) ?>">Petugas</a>
        <a href="<?= h(url('masuk-vendor')) ?>">Masuk</a>
        <a class="tombol tombol-kecil" href="<?= h(url('daftar-vendor')) ?>">Daftar Vendor</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="konten" class="wadah">
  <?php require __DIR__ . '/partials/flash.php'; ?>
  <?= $konten ?>
</main>
<footer class="kaki">
  Prototype lokal Vendor Management &middot; PT Persona Prima Utama &middot;
  <a href="https://ptppu.co.id/" rel="noopener noreferrer" target="_blank">ptppu.co.id</a>
</footer>
</body>
</html>
