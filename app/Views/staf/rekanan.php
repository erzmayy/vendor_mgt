<?php
/**
 * @var array $filter @var array $hasil @var array $matriks @var string[] $level @var array $pilihan @var array $status
 */
$tautan = static function (array $ubah) use ($filter): string {
    $p = array_filter(array_merge($filter, $ubah), static fn ($v) => $v !== '' && $v !== null);
    return h(url('staf/rekanan')) . ($p ? '&amp;' . http_build_query($p, '', '&amp;') : '');
};
$tautanEkspor = static function () use ($filter): string {
    $p = array_filter($filter, static fn ($v) => $v !== '');
    return h(url('staf/rekanan/ekspor')) . ($p ? '&amp;' . http_build_query($p, '', '&amp;') : '');
};
?>
<h1>Cari Rekanan per Kategori dan Level</h1>
<p class="lembut">Vendor yang klasifikasinya sudah ditetapkan Petugas Berwenang.
  Level adalah hasil <strong>simulasi</strong> berdasarkan aturan contoh, bukan ketentuan resmi PPU.</p>

<form class="kartu filter-bar" method="get" action="index.php">
  <input type="hidden" name="hal" value="staf/rekanan">
  <div>
    <label for="bidang">Kategori (bidang usaha)</label>
    <select id="bidang" name="bidang">
      <option value="">Semua kategori</option>
      <?php foreach ($pilihan['bidang_usaha'] as $b): ?>
        <option value="<?= h($b) ?>" <?= $filter['bidang'] === $b ? 'selected' : '' ?>><?= h($b) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="level">Level klasifikasi</label>
    <select id="level" name="level">
      <option value="">Semua level</option>
      <?php foreach ($level as $l): ?>
        <option value="<?= h($l) ?>" <?= $filter['level'] === $l ? 'selected' : '' ?>><?= h($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="q">Nama atau nomor</label>
    <input type="text" id="q" name="q" maxlength="100" value="<?= h($filter['q']) ?>" placeholder="Opsional">
  </div>
  <div style="flex:0 0 auto">
    <button class="tombol" type="submit"><?= ikon('search') ?> Tampilkan</button>
    <a class="tombol tombol-sekunder" href="<?= h(url('staf/rekanan')) ?>">Reset</a>
  </div>
</form>

<div class="kartu">
  <h2><?= ikon('list') ?> Ringkasan kategori dan level <span class="lencana lencana-simulasi">SIMULASI</span></h2>
  <?php if ($matriks === []): ?>
    <div class="kartu-kosong"><?= ikon('inbox') ?><p>Belum ada vendor dengan klasifikasi yang ditetapkan.</p></div>
  <?php else: ?>
    <p class="lembut">Klik angka untuk menampilkan vendor pada kategori dan level tersebut.</p>
    <div class="tabel-bungkus"><table class="tabel tanpa-kartu">
      <thead><tr><th>Kategori</th><?php foreach ($level as $l): ?><th><?= h($l) ?></th><?php endforeach; ?><th>Jumlah</th></tr></thead>
      <tbody>
      <?php foreach ($matriks as $bidang => $perLevel): ?>
        <tr>
          <td><a href="<?= $tautan(['bidang' => $bidang, 'level' => '']) ?>"><?= h($bidang) ?></a></td>
          <?php foreach ($level as $l): $n = $perLevel[$l] ?? 0; ?>
            <td><?= $n > 0 ? '<a href="' . $tautan(['bidang' => $bidang, 'level' => $l]) . '"><strong>' . $n . '</strong></a>' : '<span class="lembut">0</span>' ?></td>
          <?php endforeach; ?>
          <td><strong><?= (int) array_sum($perLevel) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="kartu">
  <div class="baris-judul">
    <h2><?= ikon('users') ?> Hasil pencarian <span class="lencana"><?= count($hasil) ?> vendor</span></h2>
    <?php if ($hasil): ?>
      <a class="tombol tombol-sekunder tombol-kecil" href="<?= $tautanEkspor() ?>"><?= ikon('archive') ?> Ekspor CSV</a>
    <?php endif; ?>
  </div>
  <?php if ($hasil === []): ?>
    <div class="kartu-kosong"><?= ikon('inbox') ?><p>Tidak ada vendor yang sesuai dengan pilihan kategori dan level.</p></div>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Vendor</th><th>Kategori</th><th>Level</th><th>Status pengajuan</th><th>Ditetapkan</th><th>Kontak</th></tr></thead>
    <tbody>
    <?php foreach ($hasil as $r): ?>
      <tr>
        <td><a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $r['id_pengajuan'] ?>"><?= h($r['nama_perusahaan']) ?></a>
          <div class="lembut"><?= h($r['nomor_pengajuan']) ?></div></td>
        <td><?= h($r['bidang_usaha'] ?? '-') ?></td>
        <td><span class="status status-hijau"><?= h($r['label_klasifikasi']) ?></span></td>
        <td><?= lencanaStatus($r['status_pengajuan'], $status) ?></td>
        <td><?= tgl($r['ditetapkan_pada'], 'd-m-Y') ?></td>
        <td><?= h($r['email_perusahaan'] ?? '-') ?><div class="lembut"><?= h($r['no_telepon'] ?? '') ?></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
