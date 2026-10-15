<?php
/** Ringkasan (dashboard) modul Vendor Management, ditampilkan sebagai tab pertama.
 * @var array $kpi @var int $total @var array $terbaru @var array $jadwal @var array $status @var string[] $peran
 */
$bulan = [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
$inisialUsaha = static fn (string $nama): string => inisial(preg_replace('/^(PT|CV|UD)\.?\s+/i', '', $nama) ?? $nama);
?>
<p class="lembut">Total pengajuan masuk (di luar draf): <strong><?= (int) $total ?></strong>. Angka di bawah dihitung langsung dari database.</p>

<div class="kpi-grid">
  <?php foreach ($kpi as $k):
      $tag = $k['status'] !== null ? 'a' : 'div'; ?>
    <<?= $tag ?> class="kpi" <?= $k['status'] !== null ? 'href="' . h(url('staf/vendor')) . '&amp;segmen=semua&amp;status=' . h($k['status']) . '"' : '' ?>>
      <div class="angka"><?= (int) $k['nilai'] ?></div>
      <div class="nama"><?= h($k['label']) ?></div>
    </<?= $tag ?>>
  <?php endforeach; ?>
</div>

<div class="grid-dua">
  <div class="kartu">
    <h2><?= ikon('list') ?> Pengajuan terbaru</h2>
    <?php if ($terbaru === []): ?>
      <div class="kartu-kosong"><?= ikon('inbox') ?><p>Belum ada pengajuan yang dikirim vendor.</p></div>
    <?php else: ?>
      <ul class="daftar-baris">
        <?php foreach ($terbaru as $r):
            $warna = $status['status'][$r['status_pengajuan']]['warna'] ?? 'abu'; ?>
          <li>
            <span class="inisial inisial-<?= h($warna) ?>" aria-hidden="true"><?= h($inisialUsaha((string) $r['nama_perusahaan'])) ?></span>
            <div class="baris-utama">
              <a class="baris-judul-link" href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $r['id_pengajuan'] ?>"><?= h($r['nama_perusahaan']) ?></a>
              <span class="baris-sub"><?= h($r['nomor_pengajuan']) ?> &middot; <?= tgl($r['tanggal_diajukan'], 'd-m-Y') ?></span>
              <span class="baris-status"><?= lencanaStatus($r['status_pengajuan'], $status) ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="tautan-lihat"><a href="<?= h(url('staf/vendor')) ?>&amp;segmen=semua">Lihat semua pengajuan <?= ikon('arrow-right') ?></a></p>
    <?php endif; ?>
  </div>

  <div class="kartu">
    <h2><?= ikon('calendar') ?> Verifikasi terjadwal</h2>
    <?php if ($jadwal === []): ?>
      <div class="kartu-kosong"><?= ikon('calendar') ?><p>Tidak ada jadwal verifikasi aktif.</p></div>
    <?php else: ?>
      <ul class="daftar-baris">
        <?php foreach ($jadwal as $j):
            $ts = strtotime((string) $j['tanggal_verifikasi']); ?>
          <li>
            <span class="kotak-tanggal" aria-hidden="true"><strong><?= date('d', $ts) ?></strong><small><?= $bulan[(int) date('n', $ts)] ?></small></span>
            <div class="baris-utama">
              <a class="baris-judul-link" href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $j['id_pengajuan'] ?>"><?= h($j['nama_perusahaan']) ?></a>
              <span class="baris-sub"><?= h(date('H:i', $ts)) ?> &middot; <?= h(str_replace('_', ' ', $j['metode'])) ?><?= $j['lokasi'] ? ' &middot; ' . h($j['lokasi']) : '' ?></span>
            </div>
            <span class="sr-saja"><?= tgl($j['tanggal_verifikasi']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<p class="lembut">Peran Anda: <?= h(implode(', ', $peran)) ?></p>
