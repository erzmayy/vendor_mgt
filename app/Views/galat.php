<?php /** @var int $kode @var string $pesan */ ?>
<div class="kartu halaman-galat">
  <div class="angka-galat"><?= (int) $kode ?></div>
  <h1><?= (int) $kode === 403 ? 'Akses ditolak' : ((int) $kode === 404 ? 'Halaman tidak ditemukan' : 'Terjadi kendala') ?></h1>
  <p class="lembut"><?= h($pesan) ?></p>
  <p><a class="tombol tombol-sekunder" href="<?= h(url('beranda')) ?>"><?= ikon('home') ?> Kembali ke beranda</a></p>
</div>
