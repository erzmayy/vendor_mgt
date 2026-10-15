<?php
/** @var string $judulJenis @var array $bobot */
use App\Core\Csrf;
?>
<h1><?= h($judulJenis) ?></h1>
<p class="lembut">Bobot menentukan seberapa besar tiap komponen memengaruhi skor total klasifikasi. Jumlah ketiganya harus 100.
  Nilai awal adalah <strong>contoh sementara</strong>, bukan ketentuan resmi PPU. Komponen berbobot 0 atau yang belum memiliki skor tidak dipakai, dan bobot sisanya dihitung ulang secara proporsional.</p>

<div class="kartu form-lebar">
  <form method="post" action="<?= h(url('admin/konfigurasi/simpan')) ?>" autocomplete="off">
    <?= Csrf::field() ?>
    <input type="hidden" name="jenis" value="bobot">
    <label for="b1">Kelengkapan berkas</label>
    <input type="text" id="b1" name="bobot_berkas" inputmode="numeric" maxlength="3" value="<?= (int) $bobot['bobot_berkas'] ?>" required>
    <label for="b2">Pertanyaan penilaian (jawaban vendor)</label>
    <input type="text" id="b2" name="bobot_pertanyaan" inputmode="numeric" maxlength="3" value="<?= (int) $bobot['bobot_pertanyaan'] ?>" required>
    <label for="b3">Hasil verifikasi petugas</label>
    <input type="text" id="b3" name="bobot_verifikasi" inputmode="numeric" maxlength="3" value="<?= (int) $bobot['bobot_verifikasi'] ?>" required>
    <p><button class="tombol" type="submit">Simpan bobot</button></p>
  </form>
</div>
<div class="kartu">
  <h2>Cara perhitungan</h2>
  <ul class="daftar-hak">
    <li><strong>Kelengkapan berkas:</strong> persentase dokumen wajib berstatus Sesuai (porsi 85) ditambah dokumen opsional Sesuai (porsi 15). Bila tidak ada dokumen opsional, seluruhnya dari dokumen wajib.</li>
    <li><strong>Pertanyaan penilaian:</strong> rata-rata poin jawaban tertimbang bobot pertanyaan. Jawaban yang ditandai verifikator tidak sesuai kenyataan dihitung 0.</li>
    <li><strong>Hasil verifikasi:</strong> rata-rata tiga nilai yang dicatat petugas.</li>
    <li><strong>Skor total</strong> dicocokkan dengan rentang pada <em>Aturan Klasifikasi</em> untuk menentukan level rekomendasi.</li>
  </ul>
</div>
