<?php
/** @var string $jenis @var string $judulJenis @var array $baris @var array $isian */
use App\Core\Csrf;

$cek = static fn ($v) => (int) $v === 1 ? 'checked' : '';
$aksi = h(url('admin/konfigurasi/simpan'));
?>
<h1><?= h($judulJenis) ?></h1>
<p class="lembut">Seluruh isian awal adalah <strong>contoh sementara</strong>. Hilangkan centang "contoh sementara" hanya setelah ketentuan resmi PPU dikonfirmasi. Item tidak dihapus, hanya dinonaktifkan.</p>

<?php if ($jenis === 'aturan'): ?>
  <div class="peringatan">Skor total = rata-rata tiga nilai hasil verifikasi. Rentang aturan aktif tidak boleh saling bertabrakan.</div>
<?php endif; ?>

<details class="kartu tambah-baru">
  <summary>Tambah baru</summary>
  <form method="post" action="<?= $aksi ?>" autocomplete="off">
    <?= Csrf::field() ?>
    <input type="hidden" name="jenis" value="<?= h($jenis) ?>">
    <?php if ($jenis === 'aturan'): ?>
      <div class="baris-form">
        <div><label>Label klasifikasi</label><input type="text" name="label_klasifikasi" maxlength="60" required></div>
        <div><label>Skor minimum</label><input type="text" name="skor_min" inputmode="decimal" required></div>
        <div><label>Skor maksimum</label><input type="text" name="skor_maks" inputmode="decimal" required></div>
        <div><label>Urutan</label><input type="text" name="urutan" value="0" inputmode="numeric"></div>
      </div>
    <?php elseif ($jenis === 'syarat'): ?>
      <div class="baris-form">
        <div><label>Kode</label><input type="text" name="kode_syarat" maxlength="40" required></div>
        <div><label>Nama dokumen</label><input type="text" name="nama_dokumen" maxlength="120" required></div>
        <div><label>Format (koma)</label><input type="text" name="format_diizinkan" value="pdf,jpg,png" required></div>
        <div><label>Ukuran maks (KB)</label><input type="text" name="ukuran_maks_kb" value="2048" inputmode="numeric" required></div>
        <div><label>Urutan</label><input type="text" name="urutan" value="0" inputmode="numeric"></div>
      </div>
      <label>Keterangan</label><input type="text" name="keterangan" maxlength="255">
      <label><input type="checkbox" name="wajib" value="1" checked style="width:auto"> Wajib</label>
    <?php else: ?>
      <div class="baris-form">
        <div><label>Kode</label><input type="text" name="kode_field" maxlength="40" required></div>
        <div><label>Label</label><input type="text" name="label" maxlength="100" required></div>
        <div><label>Tipe</label>
          <select name="tipe_input">
            <?php foreach (['teks', 'angka', 'tanggal', 'pilihan', 'area_teks'] as $t): ?><option><?= h($t) ?></option><?php endforeach; ?>
          </select></div>
        <div><label>Urutan</label><input type="text" name="urutan" value="0" inputmode="numeric"></div>
      </div>
      <label>Opsi pilihan (satu per baris, untuk tipe pilihan)</label><textarea name="opsi_pilihan" rows="2"></textarea>
      <label><input type="checkbox" name="wajib" value="1" style="width:auto"> Wajib diisi</label>
    <?php endif; ?>
    <label><input type="checkbox" name="aktif" value="1" checked style="width:auto"> Aktif</label>
    <label><input type="checkbox" name="contoh_sementara" value="1" checked style="width:auto"> Masih contoh sementara</label>
    <p><button class="tombol" type="submit">Tambah</button></p>
  </form>
</details>

<div class="kartu">
  <h2>Daftar</h2>
  <?php foreach ($baris as $r): ?>
    <form class="kotak-aksi" method="post" action="<?= $aksi ?>" autocomplete="off">
      <?= Csrf::field() ?>
      <input type="hidden" name="jenis" value="<?= h($jenis) ?>">
      <?php if ($jenis === 'aturan'): ?>
        <input type="hidden" name="id_aturan" value="<?= (int) $r['id_aturan'] ?>">
        <div class="baris-form">
          <div><label>Label</label><input type="text" name="label_klasifikasi" maxlength="60" value="<?= h($r['label_klasifikasi']) ?>" required></div>
          <div><label>Skor min</label><input type="text" name="skor_min" value="<?= h((string) $r['skor_min']) ?>" required></div>
          <div><label>Skor maks</label><input type="text" name="skor_maks" value="<?= h((string) $r['skor_maks']) ?>" required></div>
          <div><label>Urutan</label><input type="text" name="urutan" value="<?= (int) $r['urutan'] ?>"></div>
        </div>
      <?php elseif ($jenis === 'syarat'): ?>
        <input type="hidden" name="id_syarat" value="<?= (int) $r['id_syarat'] ?>">
        <div class="baris-form">
          <div><label>Kode</label><input type="text" value="<?= h($r['kode_syarat']) ?>" disabled></div>
          <div><label>Nama dokumen</label><input type="text" name="nama_dokumen" maxlength="120" value="<?= h($r['nama_dokumen']) ?>" required></div>
          <div><label>Format</label><input type="text" name="format_diizinkan" value="<?= h($r['format_diizinkan']) ?>" required></div>
          <div><label>Ukuran maks (KB)</label><input type="text" name="ukuran_maks_kb" value="<?= (int) $r['ukuran_maks_kb'] ?>" required></div>
          <div><label>Urutan</label><input type="text" name="urutan" value="<?= (int) $r['urutan'] ?>"></div>
        </div>
        <label>Keterangan</label><input type="text" name="keterangan" maxlength="255" value="<?= h((string) $r['keterangan']) ?>">
        <label><input type="checkbox" name="wajib" value="1" <?= $cek($r['wajib']) ?> style="width:auto"> Wajib</label>
      <?php else: ?>
        <input type="hidden" name="id_field" value="<?= (int) $r['id_field'] ?>">
        <div class="baris-form">
          <div><label>Kode</label><input type="text" value="<?= h($r['kode_field']) ?>" disabled></div>
          <div><label>Label</label><input type="text" name="label" maxlength="100" value="<?= h($r['label']) ?>" required></div>
          <div><label>Tipe</label>
            <select name="tipe_input">
              <?php foreach (['teks', 'angka', 'tanggal', 'pilihan', 'area_teks'] as $t): ?>
                <option <?= $r['tipe_input'] === $t ? 'selected' : '' ?>><?= h($t) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label>Urutan</label><input type="text" name="urutan" value="<?= (int) $r['urutan'] ?>"></div>
        </div>
        <label>Opsi pilihan</label><textarea name="opsi_pilihan" rows="2"><?= h((string) $r['opsi_pilihan']) ?></textarea>
        <label><input type="checkbox" name="wajib" value="1" <?= $cek($r['wajib']) ?> style="width:auto"> Wajib diisi</label>
      <?php endif; ?>
      <label><input type="checkbox" name="aktif" value="1" <?= $cek($r['aktif']) ?> style="width:auto"> Aktif</label>
      <label><input type="checkbox" name="contoh_sementara" value="1" <?= $cek($r['contoh_sementara']) ?> style="width:auto"> Masih contoh sementara</label>
      <p><button class="tombol tombol-kecil" type="submit">Simpan perubahan</button></p>
    </form>
  <?php endforeach; ?>
</div>
