<?php
/** @var string $q @var array $baris @var int $total @var int $halaman @var int $jumlahHalaman @var array $status */
?>
<h1>Arsip</h1>
<p class="lembut">Pengajuan yang sudah diselesaikan atau diarsipkan.</p>

<form class="kartu filter-bar" method="get" action="index.php">
  <input type="hidden" name="hal" value="staf/arsip">
  <div>
    <label for="q">Cari</label>
    <input type="text" id="q" name="q" maxlength="100" value="<?= h($q) ?>" placeholder="Nama perusahaan, nomor, atau NIB">
  </div>
  <div style="flex:0 0 auto"><button class="tombol" type="submit">Cari</button></div>
</form>

<div class="kartu">
  <p class="lembut"><?= (int) $total ?> pengajuan.</p>
  <?php if ($baris === []): ?>
    <p>Belum ada pengajuan yang selesai.</p>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Nomor</th><th>Vendor</th><th>Sumber</th><th>Status</th><th>Diajukan</th></tr></thead>
    <tbody>
    <?php foreach ($baris as $r): ?>
      <tr>
        <td><a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $r['id_pengajuan'] ?>"><?= h($r['nomor_pengajuan']) ?></a></td>
        <td><?= h($r['nama_perusahaan']) ?></td>
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
        <?php else: ?><a href="<?= h(url('staf/arsip')) ?>&amp;halaman=<?= $i ?>&amp;q=<?= h(rawurlencode($q)) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
