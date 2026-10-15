<?php
/** @var array $isian @var array $galat @var array $duplikat @var array $pilihan */
use App\Core\Csrf;

$v = static fn (string $k): string => h((string) ($isian[$k] ?? ''));
$e = static fn (string $k): string => isset($galat[$k]) ? '<div class="galat-field">' . h($galat[$k]) . '</div>' : '';
?>
<h1>Catat Pengajuan Manual</h1>
<p class="lembut">Untuk pendaftaran vendor yang diterima melalui proses manual (di luar portal). Sumber pengajuan dicatat sebagai
  <strong>Proses Manual</strong>. Contoh sementara: simulasi proses paralel.</p>

<?php if ($duplikat): ?>
<div class="peringatan" role="alert">
  <strong>Kemungkinan data vendor ganda ditemukan:</strong>
  <ul class="daftar-hak">
    <?php foreach ($duplikat as $d): ?>
      <li>
        <?php if ($d['id_pengajuan']): ?>
          <a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $d['id_pengajuan'] ?>" target="_blank" rel="noopener"><?= h($d['vendor']['nama_perusahaan']) ?></a>
        <?php else: ?>
          <?= h($d['vendor']['nama_perusahaan']) ?>
        <?php endif; ?>
        &mdash; <?= h(implode('; ', $d['alasan'])) ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p>Periksa data di atas. Jika tetap ingin mencatat sebagai vendor baru, centang konfirmasi dan isi alasan di bawah, lalu simpan lagi.</p>
</div>
<?php endif; ?>

<div class="kartu form-lebar" style="max-width:720px">
  <form method="post" action="<?= h(url('staf/manual/simpan')) ?>" autocomplete="off">
    <?= Csrf::field() ?>
    <label for="nama_perusahaan">Nama perusahaan</label>
    <input type="text" id="nama_perusahaan" name="nama_perusahaan" maxlength="150" value="<?= $v('nama_perusahaan') ?>" required>
    <?= $e('nama_perusahaan') ?>

    <div class="baris-form">
      <div>
        <label for="bentuk_badan_usaha">Bentuk badan usaha</label>
        <select id="bentuk_badan_usaha" name="bentuk_badan_usaha" required>
          <option value="">Pilih</option>
          <?php foreach ($pilihan['bentuk_badan_usaha'] as $o): ?>
            <option value="<?= h($o) ?>" <?= ($isian['bentuk_badan_usaha'] ?? '') === $o ? 'selected' : '' ?>><?= h($o) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $e('bentuk_badan_usaha') ?>
      </div>
      <div>
        <label for="nib">NIB / identitas (jika ada)</label>
        <input type="text" id="nib" name="nib" maxlength="30" value="<?= $v('nib') ?>">
        <?= $e('nib') ?>
      </div>
    </div>

    <label for="bidang_usaha">Bidang usaha</label>
    <select id="bidang_usaha" name="bidang_usaha" required>
      <option value="">Pilih</option>
      <?php foreach ($pilihan['bidang_usaha'] as $o): ?>
        <option value="<?= h($o) ?>" <?= ($isian['bidang_usaha'] ?? '') === $o ? 'selected' : '' ?>><?= h($o) ?></option>
      <?php endforeach; ?>
    </select>
    <?= $e('bidang_usaha') ?>

    <label for="alamat">Alamat perusahaan</label>
    <textarea id="alamat" name="alamat" rows="3" maxlength="500" required><?= $v('alamat') ?></textarea>
    <?= $e('alamat') ?>

    <label for="nama_penanggung_jawab">Nama penanggung jawab</label>
    <input type="text" id="nama_penanggung_jawab" name="nama_penanggung_jawab" maxlength="100" value="<?= $v('nama_penanggung_jawab') ?>" required>
    <?= $e('nama_penanggung_jawab') ?>

    <div class="baris-form">
      <div>
        <label for="email_perusahaan">Email perusahaan</label>
        <input type="email" id="email_perusahaan" name="email_perusahaan" maxlength="100" value="<?= $v('email_perusahaan') ?>" required>
        <?= $e('email_perusahaan') ?>
      </div>
      <div>
        <label for="no_telepon">Nomor telepon</label>
        <input type="text" id="no_telepon" name="no_telepon" maxlength="25" value="<?= $v('no_telepon') ?>" required>
        <?= $e('no_telepon') ?>
      </div>
    </div>

    <?php if ($duplikat): ?>
      <div class="kotak-aksi">
        <label><input type="checkbox" name="konfirmasi_duplikat" value="1" style="width:auto"> Saya sudah memeriksa dan tetap ingin mencatat sebagai vendor baru</label>
        <label for="alasan_duplikat">Alasan</label>
        <textarea id="alasan_duplikat" name="alasan_duplikat" rows="2" maxlength="300"><?= $v('alasan_duplikat') ?></textarea>
        <?= $e('alasan_duplikat') ?>
      </div>
    <?php endif; ?>

    <p><button class="tombol" type="submit">Simpan pengajuan manual</button></p>
  </form>
</div>
