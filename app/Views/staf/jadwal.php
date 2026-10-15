<?php
/** @var array $jadwal @var array $menunggu @var array $nama @var array $status @var bool $bisaKelola */
?>
<h1>Jadwal Verifikasi</h1>

<div class="kartu">
  <h2>Menunggu dijadwalkan</h2>
  <?php if ($menunggu === []): ?>
    <p class="lembut">Tidak ada pengajuan yang menunggu jadwal.</p>
  <?php else: ?>
    <div class="tabel-bungkus"><table class="tabel">
      <thead><tr><th>Vendor</th><th>Nomor</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($menunggu as $r): ?>
        <tr>
          <td><?= h($r['nama_perusahaan']) ?></td>
          <td><?= h($r['nomor_pengajuan']) ?></td>
          <td><a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $r['id_pengajuan'] ?>"><?= $bisaKelola ? 'Tentukan jadwal' : 'Lihat' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="kartu">
  <h2>Jadwal aktif</h2>
  <?php if ($jadwal === []): ?>
    <p class="lembut">Belum ada jadwal aktif.</p>
  <?php else: ?>
    <div class="tabel-bungkus"><table class="tabel">
      <thead><tr><th>Waktu</th><th>Vendor</th><th>Metode</th><th>Lokasi</th><th>Petugas</th></tr></thead>
      <tbody>
      <?php foreach ($jadwal as $j): ?>
        <tr>
          <td><?= tgl($j['tanggal_verifikasi']) ?></td>
          <td><a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $j['id_pengajuan'] ?>"><?= h($j['nama_perusahaan']) ?></a></td>
          <td><?= h(str_replace('_', ' ', $j['metode'])) ?></td>
          <td><?= h($j['lokasi'] ?? '-') ?></td>
          <td><?= h($nama[(int) $j['id_verifikator']]['nama'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
