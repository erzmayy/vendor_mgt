<?php
/** @var array $filter @var array $baris @var int $total @var int $halaman @var int $jumlahHalaman @var array $status */
$qs = static function (array $tambah = []) use ($filter): string {
    $p = array_filter(array_merge($filter, $tambah), static fn ($v) => $v !== '' && $v !== null);
    return $p ? '&amp;' . http_build_query($p, '', '&amp;') : '';
};
?>
<h1>Daftar Pengajuan</h1>
<p class="lembut">Draf vendor yang belum dikirim tidak ditampilkan.</p>

<form class="kartu filter-bar" method="get" action="index.php">
  <input type="hidden" name="hal" value="staf/pengajuan">
  <div>
    <label for="q">Cari</label>
    <input type="text" id="q" name="q" maxlength="100" value="<?= h($filter['q']) ?>" placeholder="Nama perusahaan, nomor, atau NIB">
  </div>
  <div>
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="">Semua status</option>
      <?php foreach ($status['status'] as $kode => $s): if ($kode === 'draf') { continue; } ?>
        <option value="<?= h($kode) ?>" <?= $filter['status'] === $kode ? 'selected' : '' ?>><?= h($s['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="sumber">Sumber</label>
    <select id="sumber" name="sumber">
      <option value="">Semua sumber</option>
      <option value="portal_vendor_management" <?= $filter['sumber'] === 'portal_vendor_management' ? 'selected' : '' ?>>Portal Vendor Management</option>
      <option value="proses_manual" <?= $filter['sumber'] === 'proses_manual' ? 'selected' : '' ?>>Proses Manual</option>
    </select>
  </div>
  <div style="flex:0 0 auto">
    <button class="tombol" type="submit"><?= ikon('search') ?> Terapkan</button>
    <a class="tombol tombol-sekunder" href="<?= h(url('staf/pengajuan')) ?>">Reset</a>
  </div>
</form>

<div class="kartu">
  <p class="lembut"><?= (int) $total ?> pengajuan ditemukan.</p>
  <?php if ($baris === []): ?>
    <div class="kartu-kosong"><?= ikon('inbox') ?><p>Tidak ada pengajuan yang sesuai dengan pencarian atau filter.</p></div>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Nomor</th><th>Vendor</th><th>Bidang usaha</th><th>Sumber</th><th>Status</th><th>Diajukan</th></tr></thead>
    <tbody>
    <?php foreach ($baris as $r): ?>
      <tr>
        <td><a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $r['id_pengajuan'] ?>"><?= h($r['nomor_pengajuan']) ?></a></td>
        <td><?= h($r['nama_perusahaan']) ?><?= $r['nib'] ? '<div class="lembut">NIB: ' . h($r['nib']) . '</div>' : '' ?></td>
        <td><?= h($r['bidang_usaha'] ?? '-') ?></td>
        <td><?= $r['sumber_pengajuan'] === 'proses_manual' ? 'Proses manual' : 'Portal' ?></td>
        <td><?= lencanaStatus($r['status_pengajuan'], $status) ?></td>
        <td><?= tgl($r['tanggal_diajukan']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($jumlahHalaman > 1): ?>
    <nav class="paginasi" aria-label="Halaman">
      <?php for ($i = 1; $i <= $jumlahHalaman; $i++): ?>
        <?php if ($i === $halaman): ?><span class="sekarang"><?= $i ?></span>
        <?php else: ?><a href="<?= h(url('staf/pengajuan')) . $qs(['halaman' => (string) $i]) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
