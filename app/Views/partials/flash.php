<?php
use App\Core\Flash;

foreach (Flash::ambil() as $pesan):
    $ok = $pesan['jenis'] === 'sukses'; ?>
  <div class="<?= $ok ? 'sukses' : 'galat' ?>" role="<?= $ok ? 'status' : 'alert' ?>">
    <?= ikon($ok ? 'check-circle' : 'alert') ?>
    <span><?= h($pesan['pesan']) ?></span>
    <button type="button" class="tutup" aria-label="Tutup pesan"><?= ikon('x') ?></button>
  </div>
<?php endforeach; ?>
