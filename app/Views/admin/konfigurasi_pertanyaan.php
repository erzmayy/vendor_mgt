<?php
/** @var string $judulJenis @var array $baris */
use App\Core\Csrf;

$cek = static fn ($v) => (int) $v === 1 ? 'checked' : '';
$aksi = h(url('admin/konfigurasi/simpan'));
$opsiTeks = static function (array $opsi): string {
    return implode("\n", array_map(static fn ($o) => $o['teks_opsi'] . ' | ' . $o['poin'], $opsi));
};
?>
<h1><?= h($judulJenis) ?></h1>
<p class="lembut">Pertanyaan ini ditampilkan kepada vendor pada langkah "Pertanyaan penilaian". Setiap opsi punya poin 0-100 dan setiap pertanyaan punya bobot relatif.
  Seluruh isian awal adalah <strong>contoh sementara</strong>. Pertanyaan tidak dihapus, hanya dinonaktifkan. Opsi pada pertanyaan yang sudah dijawab tidak dapat diubah.</p>

<details class="kartu tambah-baru">
  <summary>Tambah pertanyaan</summary>
  <form method="post" action="<?= $aksi ?>" autocomplete="off">
    <?= Csrf::field() ?>
    <input type="hidden" name="jenis" value="pertanyaan">
    <div class="baris-form">
      <div><label>Kode</label><input type="text" name="kode_pertanyaan" maxlength="40" required></div>
      <div><label>Bobot (1-100)</label><input type="text" name="bobot" value="1" inputmode="numeric" required></div>
      <div><label>Urutan</label><input type="text" name="urutan" value="0" inputmode="numeric"></div>
    </div>
    <label>Teks pertanyaan</label><input type="text" name="teks_pertanyaan" maxlength="255" required>
    <label>Opsi jawaban (satu per baris: <code>teks jawaban | poin</code>)</label>
    <textarea name="opsi" rows="4" placeholder="Kurang dari 1 tahun | 20&#10;1 sampai 3 tahun | 50" required></textarea>
    <label><input type="checkbox" name="wajib" value="1" checked style="width:auto"> Wajib dijawab</label>
    <label><input type="checkbox" name="aktif" value="1" checked style="width:auto"> Aktif</label>
    <label><input type="checkbox" name="contoh_sementara" value="1" checked style="width:auto"> Masih contoh sementara</label>
    <p><button class="tombol" type="submit">Tambah</button></p>
  </form>
</details>

<div class="kartu">
  <h2>Daftar pertanyaan</h2>
  <?php foreach ($baris as $r): ?>
    <form class="kotak-aksi" method="post" action="<?= $aksi ?>" autocomplete="off">
      <?= Csrf::field() ?>
      <input type="hidden" name="jenis" value="pertanyaan">
      <input type="hidden" name="id_pertanyaan" value="<?= (int) $r['id_pertanyaan'] ?>">
      <div class="baris-form">
        <div><label>Kode</label><input type="text" value="<?= h($r['kode_pertanyaan']) ?>" disabled></div>
        <div><label>Bobot (1-100)</label><input type="text" name="bobot" value="<?= (int) $r['bobot'] ?>" required></div>
        <div><label>Urutan</label><input type="text" name="urutan" value="<?= (int) $r['urutan'] ?>"></div>
      </div>
      <label>Teks pertanyaan</label>
      <input type="text" name="teks_pertanyaan" maxlength="255" value="<?= h($r['teks_pertanyaan']) ?>" required>
      <label>Opsi jawaban <span class="lembut">(teks | poin)</span></label>
      <?php if ($r['ada_jawaban']): ?>
        <div class="lembut">Sudah ada jawaban vendor, opsi tidak dapat diubah:</div>
        <ul class="daftar-hak">
          <?php foreach ($r['opsi'] as $o): ?><li><?= h($o['teks_opsi']) ?> &mdash; <?= (int) $o['poin'] ?> poin</li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <textarea name="opsi" rows="<?= max(3, count($r['opsi'])) ?>" required><?= h($opsiTeks($r['opsi'])) ?></textarea>
      <?php endif; ?>
      <label><input type="checkbox" name="wajib" value="1" <?= $cek($r['wajib']) ?> style="width:auto"> Wajib dijawab</label>
      <label><input type="checkbox" name="aktif" value="1" <?= $cek($r['aktif']) ?> style="width:auto"> Aktif</label>
      <label><input type="checkbox" name="contoh_sementara" value="1" <?= $cek($r['contoh_sementara']) ?> style="width:auto"> Masih contoh sementara</label>
      <p><button class="tombol tombol-kecil" type="submit">Simpan perubahan</button></p>
    </form>
  <?php endforeach; ?>
</div>
