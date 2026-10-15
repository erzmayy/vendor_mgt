<?php
/** @var string $jenis @var string $aksi @var ?string $galat */
use App\Core\Csrf;

$staf = $jenis === 'staf';
?>
<div class="auth">
  <aside class="auth-panel" aria-hidden="false">
    <h2><?= $staf ? 'Modul petugas Vendor Management' : 'Portal Vendor PT Persona Prima Utama' ?></h2>
    <p style="color:#bcd0f7"><?= $staf
        ? 'Kelola pengajuan vendor: periksa dokumen, jadwalkan verifikasi, dan tetapkan klasifikasi.'
        : 'Satu akun untuk mengisi data, mengunggah dokumen, dan memantau status pengajuan.' ?></p>
    <ul>
      <?php if ($staf): ?>
        <li><?= ikon('clipboard-check') ?><span>Pemeriksaan data dan dokumen terpadu</span></li>
        <li><?= ikon('calendar') ?><span>Penjadwalan verifikasi dan pencatatan hasil</span></li>
        <li><?= ikon('shield') ?><span>Hak akses sesuai peran, seluruh perubahan tercatat</span></li>
      <?php else: ?>
        <li><?= ikon('upload') ?><span>Unggah dokumen persyaratan secara aman</span></li>
        <li><?= ikon('clock') ?><span>Pantau status dan jadwal verifikasi</span></li>
        <li><?= ikon('shield') ?><span>Data hanya dapat dilihat oleh pihak berwenang</span></li>
      <?php endif; ?>
    </ul>
  </aside>
  <div class="auth-isi">
    <nav class="pil-peran" aria-label="Jenis akun">
      <a href="<?= h(url('masuk-vendor')) ?>" class="<?= $staf ? '' : 'aktif' ?>">Vendor</a>
      <a href="<?= h(url('masuk-staf')) ?>" class="<?= $staf ? 'aktif' : '' ?>">Petugas</a>
    </nav>
    <h1><?= $staf ? 'Masuk Petugas' : 'Masuk Vendor' ?></h1>
    <p class="lembut"><?= $staf
        ? 'Untuk Divisi Perencanaan, Petugas Verifikasi, Petugas Berwenang, dan Administrator.'
        : 'Gunakan email dan kata sandi akun vendor Anda.' ?></p>
    <?php if ($galat): ?>
      <div class="galat" role="alert"><?= ikon('alert') ?><span><?= h($galat) ?></span></div>
    <?php endif; ?>
    <form method="post" action="<?= h(url($aksi)) ?>" autocomplete="off">
      <?= Csrf::field() ?>
      <?php if ($staf): ?>
        <label for="userid">Userid</label>
        <input type="text" id="userid" name="userid" maxlength="50" required autofocus autocomplete="username">
      <?php else: ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="100" required autofocus autocomplete="email">
      <?php endif; ?>
      <label for="kata_sandi">Kata sandi</label>
      <input type="password" id="kata_sandi" name="kata_sandi" maxlength="100" required autocomplete="current-password">
      <p style="margin-top:18px"><button class="tombol tombol-lebar" type="submit">Masuk</button></p>
    </form>
    <?php if (!$staf): ?>
      <p class="lembut">Belum punya akun? <a href="<?= h(url('daftar-vendor')) ?>">Daftar sebagai vendor</a></p>
    <?php endif; ?>
  </div>
</div>
