<?php
/** @var array $baris @var array $status */
$ket = [
    'ada_rekomendasi' => 'Menunggu penetapan klasifikasi',
    'terklasifikasi'  => 'Menunggu catatan TDR',
    'tdr_tercatat'    => 'Menunggu diselesaikan',
];
?>
<h1>Penetapan Klasifikasi <span class="lencana lencana-simulasi">SIMULASI</span></h1>
<p class="lembut">Antrean pengajuan untuk Petugas Berwenang. Klasifikasi dan TDR pada prototype ini adalah simulasi, bukan ketentuan resmi PPU.</p>

<div class="kartu">
  <?php if ($baris === []): ?>
    <p class="lembut">Tidak ada pengajuan dalam antrean.</p>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Vendor</th><th>Nomor</th><th>Status</th><th>Langkah berikutnya</th></tr></thead>
    <tbody>
    <?php foreach ($baris as $r): ?>
      <tr>
        <td><a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $r['id_pengajuan'] ?>"><?= h($r['nama_perusahaan']) ?></a></td>
        <td><?= h($r['nomor_pengajuan']) ?></td>
        <td><?= lencanaStatus($r['status_pengajuan'], $status) ?></td>
        <td><?= h($ket[$r['status_pengajuan']] ?? '-') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
