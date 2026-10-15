<?php
/**
 * @var int $langkah @var array $vendor @var array $pengajuan @var array $tambahan @var array $fields
 * @var array $pilihan @var array $syarat @var array $dokumen @var string[] $kekurangan
 * @var bool $bisaEdit @var array $isian @var array $galat @var array $pertanyaan @var array $jawaban
 */
use App\Core\Csrf;

$nama = [1 => 'Profil perusahaan', 2 => 'Informasi tambahan', 3 => 'Pertanyaan penilaian', 4 => 'Dokumen', 5 => 'Tinjau dan kirim'];
$nilai = static function (string $k) use ($isian, $vendor): string {
    if (array_key_exists($k, $isian)) {
        return (string) $isian[$k];
    }
    $v = (string) ($vendor[$k] ?? '');
    return $v === '(belum diisi)' ? '' : $v;
};
$err = static fn (string $k): string => isset($galat[$k]) ? '<div class="galat-field">' . h($galat[$k]) . '</div>' : '';
$dis = $bisaEdit ? '' : 'disabled';
?>
<div class="kartu">
  <h1>Formulir Pendaftaran Vendor</h1>
  <p class="lembut">Seluruh isian dan persyaratan di bawah adalah <strong>contoh sementara</strong> sampai ketentuan resmi PPU diberikan.</p>
  <ol class="langkah-form">
    <?php foreach ($nama as $n => $label): ?>
      <li class="<?= $n === $langkah ? 'aktif' : '' ?>"><a href="<?= h(url('vendor/formulir')) ?>&amp;langkah=<?= $n ?>"><span><?= $n ?></span><?= h($label) ?></a></li>
    <?php endforeach; ?>
  </ol>
  <?php if (!$bisaEdit): ?>
    <div class="peringatan">Pengajuan sudah dikirim, sehingga formulir hanya dapat dilihat.</div>
  <?php endif; ?>
</div>

<?php if ($langkah === 1): ?>
<div class="kartu">
  <h2>1. Profil perusahaan</h2>
  <form method="post" action="<?= h(url('vendor/formulir/simpan')) ?>" autocomplete="off">
    <?= Csrf::field() ?>
    <input type="hidden" name="langkah" value="1">
    <fieldset <?= $dis ?>>
      <label for="nama_perusahaan">Nama perusahaan</label>
      <input type="text" id="nama_perusahaan" name="nama_perusahaan" maxlength="150" value="<?= h($nilai('nama_perusahaan')) ?>" required>
      <?= $err('nama_perusahaan') ?>

      <label for="bentuk_badan_usaha">Bentuk badan usaha</label>
      <select id="bentuk_badan_usaha" name="bentuk_badan_usaha" required>
        <option value="">Pilih</option>
        <?php foreach ($pilihan['bentuk_badan_usaha'] as $o): ?>
          <option value="<?= h($o) ?>" <?= $nilai('bentuk_badan_usaha') === $o ? 'selected' : '' ?>><?= h($o) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $err('bentuk_badan_usaha') ?>

      <label for="nib">NIB / identitas perusahaan <span class="lembut">(jika dipersyaratkan)</span></label>
      <input type="text" id="nib" name="nib" maxlength="30" value="<?= h($nilai('nib')) ?>">
      <?= $err('nib') ?>

      <label for="bidang_usaha">Bidang usaha <span class="lembut">(contoh sementara)</span></label>
      <select id="bidang_usaha" name="bidang_usaha" required>
        <option value="">Pilih</option>
        <?php foreach ($pilihan['bidang_usaha'] as $o): ?>
          <option value="<?= h($o) ?>" <?= $nilai('bidang_usaha') === $o ? 'selected' : '' ?>><?= h($o) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $err('bidang_usaha') ?>

      <label for="alamat">Alamat perusahaan</label>
      <textarea id="alamat" name="alamat" rows="3" maxlength="500" required><?= h($nilai('alamat')) ?></textarea>
      <?= $err('alamat') ?>

      <label for="nama_penanggung_jawab">Nama penanggung jawab</label>
      <input type="text" id="nama_penanggung_jawab" name="nama_penanggung_jawab" maxlength="100" value="<?= h($nilai('nama_penanggung_jawab')) ?>" required>
      <?= $err('nama_penanggung_jawab') ?>

      <label for="email_perusahaan">Email perusahaan</label>
      <input type="email" id="email_perusahaan" name="email_perusahaan" maxlength="100" value="<?= h($nilai('email_perusahaan')) ?>" required>
      <?= $err('email_perusahaan') ?>

      <label for="no_telepon">Nomor telepon</label>
      <input type="text" id="no_telepon" name="no_telepon" maxlength="25" value="<?= h($nilai('no_telepon')) ?>" required>
      <?= $err('no_telepon') ?>
    </fieldset>
    <?php if ($bisaEdit): ?>
      <p>
        <button class="tombol tombol-sekunder" type="submit" name="simpan_draf" value="1">Simpan draf</button>
        <button class="tombol" type="submit" name="simpan_lanjut" value="1">Simpan dan lanjut</button>
      </p>
    <?php endif; ?>
  </form>
</div>

<?php elseif ($langkah === 2): ?>
<div class="kartu">
  <h2>2. Informasi tambahan <span class="lencana lencana-simulasi">contoh sementara</span></h2>
  <?php if ($fields === []): ?>
    <p class="lembut">Tidak ada informasi tambahan yang dikonfigurasi.</p>
  <?php endif; ?>
  <form method="post" action="<?= h(url('vendor/formulir/simpan')) ?>" autocomplete="off">
    <?= Csrf::field() ?>
    <input type="hidden" name="langkah" value="2">
    <fieldset <?= $dis ?>>
    <?php foreach ($fields as $f):
        $k = $f['kode_field'];
        $v = array_key_exists($k, $isian) ? (string) $isian[$k] : (string) ($tambahan[$k] ?? ''); ?>
      <label for="f_<?= h($k) ?>"><?= h($f['label']) ?><?= (int) $f['wajib'] === 1 ? ' <span class="wajib">*</span>' : '' ?></label>
      <?php if ($f['tipe_input'] === 'area_teks'): ?>
        <textarea id="f_<?= h($k) ?>" name="<?= h($k) ?>" rows="3" maxlength="1000"><?= h($v) ?></textarea>
      <?php elseif ($f['tipe_input'] === 'pilihan'): ?>
        <select id="f_<?= h($k) ?>" name="<?= h($k) ?>">
          <option value="">Pilih</option>
          <?php foreach (array_filter(array_map('trim', explode("\n", (string) $f['opsi_pilihan']))) as $o): ?>
            <option value="<?= h($o) ?>" <?= $v === $o ? 'selected' : '' ?>><?= h($o) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input type="<?= $f['tipe_input'] === 'tanggal' ? 'date' : 'text' ?>" id="f_<?= h($k) ?>" name="<?= h($k) ?>"
               <?= $f['tipe_input'] === 'angka' ? 'inputmode="numeric"' : '' ?> maxlength="200" value="<?= h($v) ?>">
      <?php endif; ?>
      <?= $err($k) ?>
    <?php endforeach; ?>
    </fieldset>
    <?php if ($bisaEdit): ?>
      <p>
        <button class="tombol tombol-sekunder" type="submit" name="simpan_draf" value="1">Simpan draf</button>
        <button class="tombol" type="submit" name="simpan_lanjut" value="1">Simpan dan lanjut</button>
      </p>
    <?php endif; ?>
  </form>
</div>

<?php elseif ($langkah === 3): ?>
<div class="kartu">
  <h2>3. Pertanyaan penilaian <span class="lencana lencana-simulasi">contoh sementara</span></h2>
  <p class="lembut">Jawaban Anda dipakai sistem untuk menghitung rekomendasi klasifikasi (simulasi). Jawablah sesuai kenyataan; petugas dapat memeriksa kebenarannya saat verifikasi.</p>
  <?php if ($pertanyaan === []): ?>
    <p class="lembut">Tidak ada pertanyaan penilaian yang dikonfigurasi.</p>
  <?php endif; ?>
  <form method="post" action="<?= h(url('vendor/formulir/simpan')) ?>" autocomplete="off">
    <?= Csrf::field() ?>
    <input type="hidden" name="langkah" value="3">
    <fieldset <?= $dis ?>>
    <?php foreach ($pertanyaan as $q):
        $kunci = 'q_' . $q['id_pertanyaan'];
        $terpilih = (int) ($isian[$kunci] ?? ($jawaban[(int) $q['id_pertanyaan']]['id_opsi'] ?? 0)); ?>
      <div class="baris-dokumen" style="display:block">
        <strong><?= h($q['teks_pertanyaan']) ?></strong>
        <?= (int) $q['wajib'] === 1 ? '<span class="wajib">wajib</span>' : '' ?>
        <?php foreach ($q['opsi'] as $o): ?>
          <label style="font-weight:400;margin:6px 0">
            <input type="radio" name="<?= h($kunci) ?>" value="<?= (int) $o['id_opsi'] ?>" style="width:auto"
                   <?= $terpilih === (int) $o['id_opsi'] ? 'checked' : '' ?>> <?= h($o['teks_opsi']) ?>
          </label>
        <?php endforeach; ?>
        <?= $err($kunci) ?>
      </div>
    <?php endforeach; ?>
    </fieldset>
    <?php if ($bisaEdit): ?>
      <p>
        <button class="tombol tombol-sekunder" type="submit" name="simpan_draf" value="1">Simpan draf</button>
        <button class="tombol" type="submit" name="simpan_lanjut" value="1">Simpan dan lanjut</button>
      </p>
    <?php endif; ?>
  </form>
</div>

<?php elseif ($langkah === 4): ?>
<div class="kartu">
  <h2>4. Dokumen persyaratan <span class="lencana lencana-simulasi">contoh sementara</span></h2>
  <p class="lembut">Format PDF, JPG, atau PNG. Berkas diperiksa isinya, bukan hanya namanya.</p>
  <?php foreach ($syarat as $s):
      $d = $dokumen[(int) $s['id_syarat']] ?? null; ?>
    <div class="baris-dokumen">
      <div>
        <strong><?= h($s['nama_dokumen']) ?></strong>
        <?= (int) $s['wajib'] === 1 ? '<span class="wajib">wajib</span>' : '<span class="lembut">opsional</span>' ?>
        <div class="lembut">Maks. <?= (int) $s['ukuran_maks_kb'] ?> KB &middot; <?= h(strtoupper((string) $s['format_diizinkan'])) ?></div>
        <?php if ($d): ?>
          <div><a href="<?= h(url('dokumen/unduh')) ?>&amp;id=<?= (int) $d['id_dokumen'] ?>" target="_blank" rel="noopener"><?= h($d['nama_file_asli']) ?></a>
            <span class="lembut">(<?= ukuranBerkas((int) $d['ukuran_byte']) ?>)</span></div>
          <?php if ($d['status_pemeriksaan'] === 'tidak_sesuai'): ?>
            <div class="galat-field">Perlu diganti<?= $d['catatan_pemeriksa'] ? ': ' . h($d['catatan_pemeriksa']) : '' ?></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="lembut">Belum diunggah</div>
        <?php endif; ?>
      </div>
      <?php if ($bisaEdit): ?>
      <div class="aksi-dokumen">
        <form method="post" action="<?= h(url('vendor/dokumen/unggah')) ?>" enctype="multipart/form-data">
          <?= Csrf::field() ?>
          <input type="hidden" name="id_syarat" value="<?= (int) $s['id_syarat'] ?>">
          <input type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png" required aria-label="Berkas <?= h($s['nama_dokumen']) ?>">
          <button class="tombol tombol-kecil" type="submit"><?= $d ? 'Ganti' : 'Unggah' ?></button>
        </form>
        <?php if ($d): ?>
          <form method="post" action="<?= h(url('vendor/dokumen/hapus')) ?>" data-konfirmasi="Hapus dokumen ini?">
            <?= Csrf::field() ?>
            <input type="hidden" name="id_dokumen" value="<?= (int) $d['id_dokumen'] ?>">
            <button class="tombol tombol-kecil tombol-sekunder" type="submit">Hapus</button>
          </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <p><a class="tombol" href="<?= h(url('vendor/formulir')) ?>&amp;langkah=5">Lanjut ke tinjau</a></p>
</div>

<?php else: ?>
<div class="kartu">
  <h2>5. Tinjau dan kirim</h2>
  <dl class="ringkasan">
    <dt>Nama perusahaan</dt><dd><?= h($vendor['nama_perusahaan']) ?></dd>
    <dt>Bentuk badan usaha</dt><dd><?= h($vendor['bentuk_badan_usaha'] ?? '-') ?></dd>
    <dt>NIB / identitas</dt><dd><?= h($vendor['nib'] ?? '-') ?></dd>
    <dt>Bidang usaha</dt><dd><?= h($vendor['bidang_usaha'] ?? '-') ?></dd>
    <dt>Alamat</dt><dd><?= nl2br(h($vendor['alamat'] ?? '-')) ?></dd>
    <dt>Penanggung jawab</dt><dd><?= h($vendor['nama_penanggung_jawab'] ?? '-') ?></dd>
    <dt>Email perusahaan</dt><dd><?= h($vendor['email_perusahaan'] ?? '-') ?></dd>
    <dt>Telepon</dt><dd><?= h($vendor['no_telepon'] ?? '-') ?></dd>
    <?php foreach ($fields as $f): ?>
      <dt><?= h($f['label']) ?></dt><dd><?= h((string) ($tambahan[$f['kode_field']] ?? '-')) ?></dd>
    <?php endforeach; ?>
    <dt>Pertanyaan penilaian</dt>
    <dd><?= count($jawaban) ?> dari <?= count($pertanyaan) ?> dijawab</dd>
    <dt>Dokumen</dt>
    <dd><?= count($dokumen) ?> dari <?= count($syarat) ?> berkas terunggah</dd>
  </dl>
  <?php if ($kekurangan !== []): ?>
    <div class="peringatan"><strong>Belum lengkap:</strong>
      <ul class="daftar-hak"><?php foreach ($kekurangan as $k): ?><li><?= h($k) ?></li><?php endforeach; ?></ul>
    </div>
  <?php elseif ($bisaEdit): ?>
    <form method="post" action="<?= h(url('vendor/kirim')) ?>" data-konfirmasi="Kirim pengajuan sekarang? Data tidak dapat diubah setelah dikirim.">
      <?= Csrf::field() ?>
      <button class="tombol" type="submit">Kirim pengajuan</button>
    </form>
  <?php endif; ?>
  <p><a href="<?= h(url('vendor/dashboard')) ?>">Kembali ke dashboard</a></p>
</div>
<?php endif; ?>
