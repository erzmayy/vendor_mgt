<?php
/** @var array $isian @var array $galat */
use App\Core\Csrf;

$f = static fn (string $k): string => h($isian[$k] ?? '');
$e = static fn (string $k): string => isset($galat[$k]) ? '<div class="galat-field">' . h($galat[$k]) . '</div>' : '';
?>
<div class="auth">
  <aside class="auth-panel">
    <h2>Daftar sebagai vendor</h2>
    <p style="color:#bcd0f7">Buat akun untuk mengajukan pendaftaran sebagai calon rekanan PT Persona Prima Utama.</p>
    <ul>
      <li><?= ikon('user-plus') ?><span>Buat akun dengan email perusahaan</span></li>
      <li><?= ikon('file') ?><span>Isi formulir bertahap dan simpan sebagai draf</span></li>
      <li><?= ikon('upload') ?><span>Unggah dokumen persyaratan (PDF, JPG, PNG)</span></li>
      <li><?= ikon('clock') ?><span>Pantau status pengajuan hingga selesai</span></li>
    </ul>
    <p style="color:#9db8ee;font-size:.82rem;margin-top:20px">Isian dan persyaratan pada prototype ini bersifat contoh sementara.</p>
  </aside>
  <div class="auth-isi">
    <h1>Buat akun vendor</h1>
    <p class="lembut">Sudah punya akun? <a href="<?= h(url('masuk-vendor')) ?>">Masuk</a></p>
    <?php if (isset($galat['umum'])): ?>
      <div class="galat" role="alert"><?= ikon('alert') ?><span><?= h($galat['umum']) ?></span></div>
    <?php endif; ?>
    <form method="post" action="<?= h(url('daftar-vendor/proses')) ?>" autocomplete="off">
      <?= Csrf::field() ?>
      <label for="nama_perusahaan">Nama perusahaan</label>
      <input type="text" id="nama_perusahaan" name="nama_perusahaan" maxlength="150" value="<?= $f('nama_perusahaan') ?>" required autofocus>
      <?= $e('nama_perusahaan') ?>

      <label for="nama_kontak">Nama kontak</label>
      <input type="text" id="nama_kontak" name="nama_kontak" maxlength="100" value="<?= $f('nama_kontak') ?>" required autocomplete="name">
      <?= $e('nama_kontak') ?>

      <label for="email">Email (untuk masuk)</label>
      <input type="email" id="email" name="email" maxlength="100" value="<?= $f('email') ?>" required autocomplete="email">
      <?= $e('email') ?>

      <div class="baris-form">
        <div>
          <label for="kata_sandi">Kata sandi</label>
          <input type="password" id="kata_sandi" name="kata_sandi" maxlength="72" required autocomplete="new-password" aria-describedby="bantu-sandi">
          <?= $e('kata_sandi') ?>
        </div>
        <div>
          <label for="konfirmasi_sandi">Ulangi kata sandi</label>
          <input type="password" id="konfirmasi_sandi" name="konfirmasi_sandi" maxlength="72" required autocomplete="new-password">
          <?= $e('konfirmasi_sandi') ?>
        </div>
      </div>
      <div class="lembut" id="bantu-sandi">Minimal 10 karakter, memuat huruf dan angka.</div>

      <p style="margin-top:18px"><button class="tombol tombol-lebar" type="submit">Buat akun</button></p>
    </form>
  </div>
</div>
