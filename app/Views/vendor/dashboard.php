<?php
/**
 * @var array $vendor @var array $pengajuan @var array $status @var array $riwayat
 * @var ?array $jadwal @var array $syarat @var array $dokumen @var string[] $kekurangan @var bool $bisaEdit
 */
use App\Core\Csrf;

$st = $status['status'][$pengajuan['status_pengajuan']] ?? ['label' => $pengajuan['status_pengajuan'], 'warna' => 'abu', 'langkah' => 1];
$langkahAktif = $st['langkah'];
$nomor = str_starts_with((string) $pengajuan['nomor_pengajuan'], 'DRF-') ? 'Belum diberi nomor (draf)' : $pengajuan['nomor_pengajuan'];
?>
<div class="kartu">
  <div class="baris-judul">
    <div>
      <h1><?= h($vendor['nama_perusahaan']) ?></h1>
      <div class="lembut">No. pengajuan: <strong><?= h($nomor) ?></strong> &middot; Sumber: <?= $pengajuan['sumber_pengajuan'] === 'proses_manual' ? 'Proses manual' : 'Portal Vendor Management' ?></div>
    </div>
    <span class="status status-<?= h($st['warna']) ?>"><?= h($st['label']) ?></span>
  </div>

  <ol class="progres" aria-label="Progres pengajuan">
    <?php foreach ($status['langkah'] as $i => $nama): $n = $i + 1; ?>
      <li class="<?= $n < $langkahAktif ? 'selesai' : ($n === $langkahAktif ? 'aktif' : '') ?>"><span><?= $n ?></span><?= h($nama) ?></li>
    <?php endforeach; ?>
  </ol>
  <div class="ringkas-kartu">
    <div><small>Bidang usaha</small><strong><?= h($vendor['bidang_usaha'] ?? '-') ?></strong></div>
    <div><small>Diajukan</small><strong><?= $pengajuan['tanggal_diajukan'] ? h(date('d-m-Y H:i', strtotime($pengajuan['tanggal_diajukan']))) : 'Belum dikirim' ?></strong></div>
    <div><small>Kelengkapan</small><strong><?= $kekurangan === [] ? 'Lengkap' : count($kekurangan) . ' item belum lengkap' ?></strong></div>
    <div><small>Dikembalikan</small><strong><?= (int) $pengajuan['jumlah_pengembalian'] ?> kali</strong></div>
  </div>

  <?php if ($pengajuan['status_pengajuan'] === 'perlu_perbaikan'): ?>
    <div class="peringatan" role="alert">
      <strong>Pengajuan dikembalikan untuk diperbaiki.</strong>
      <div><?= nl2br(h($pengajuan['catatan_perbaikan'] ?: 'Silakan periksa data dan dokumen Anda, lalu kirim ulang.')) ?></div>
    </div>
  <?php endif; ?>

  <p>
    <?php if ($bisaEdit): ?>
      <a class="tombol" href="<?= h(url('vendor/formulir')) ?>"><?= $pengajuan['status_pengajuan'] === 'draf' ? 'Lengkapi formulir' : 'Perbaiki pengajuan' ?></a>
    <?php else: ?>
      <span class="lembut">Pengajuan sedang diproses. Data tidak dapat diubah kecuali dikembalikan oleh petugas.</span>
    <?php endif; ?>
  </p>
</div>

<div class="grid-dua">
  <div class="kartu">
    <h2><?= ikon('calendar') ?> Jadwal verifikasi</h2>
    <?php if ($jadwal): ?>
      <p><strong><?= h(date('d-m-Y H:i', strtotime($jadwal['tanggal_verifikasi']))) ?></strong><br>
        <?= h(str_replace('_', ' ', $jadwal['metode'])) ?><?= $jadwal['lokasi'] ? ' &middot; ' . h($jadwal['lokasi']) : '' ?></p>
      <?php if ($jadwal['catatan']): ?><p class="lembut"><?= h($jadwal['catatan']) ?></p><?php endif; ?>
    <?php else: ?>
      <div class="kartu-kosong"><?= ikon('calendar') ?><p>Belum ada jadwal.<br><span class="lembut">Jadwal akan tampil setelah dokumen Anda diverifikasi.</span></p></div>
    <?php endif; ?>
  </div>

  <div class="kartu">
    <h2><?= ikon('clipboard-check') ?> Kelengkapan pengajuan</h2>
    <?php if ($kekurangan === []): ?>
      <p class="sukses-teks">Seluruh isian dan dokumen wajib sudah lengkap.</p>
      <?php if ($bisaEdit): ?>
        <form method="post" action="<?= h(url('vendor/kirim')) ?>" data-konfirmasi="Kirim pengajuan sekarang? Data tidak dapat diubah setelah dikirim.">
          <?= Csrf::field() ?>
          <button class="tombol" type="submit">Kirim pengajuan</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <ul class="daftar-hak">
        <?php foreach ($kekurangan as $k): ?><li><?= h($k) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="kartu">
  <h2>Dokumen persyaratan <span class="lencana lencana-simulasi">contoh sementara</span></h2>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Dokumen</th><th>Wajib</th><th>Berkas</th><th>Pemeriksaan</th></tr></thead>
      <tbody>
      <?php foreach ($syarat as $s):
          $d = $dokumen[(int) $s['id_syarat']] ?? null; ?>
        <tr>
          <td><?= h($s['nama_dokumen']) ?></td>
          <td><?= (int) $s['wajib'] === 1 ? 'Ya' : 'Tidak' ?></td>
          <td><?= $d ? '<a href="' . h(url('dokumen/unduh')) . '&amp;id=' . (int) $d['id_dokumen'] . '" target="_blank" rel="noopener">' . h($d['nama_file_asli']) . '</a>' : '<span class="lembut">Belum diunggah</span>' ?></td>
          <td><?php
              if (!$d) { echo '&mdash;'; }
              elseif ($d['status_pemeriksaan'] === 'sesuai') { echo '<span class="status status-hijau">Sesuai</span>'; }
              elseif ($d['status_pemeriksaan'] === 'tidak_sesuai') { echo '<span class="status status-kuning">Perlu diganti</span>' . ($d['catatan_pemeriksa'] ? '<div class="lembut">' . h($d['catatan_pemeriksa']) . '</div>' : ''); }
              else { echo '<span class="status status-abu">Belum diperiksa</span>'; }
          ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="kartu">
  <h2><?= ikon('clock') ?> Riwayat aktivitas</h2>
  <?php if ($riwayat === []): ?>
    <p class="lembut">Belum ada aktivitas.</p>
  <?php else: ?>
    <ul class="linimasa">
      <?php foreach ($riwayat as $r):
          $label = $status['status'][$r['status_tujuan']]['label'] ?? $r['status_tujuan']; ?>
        <li>
          <strong><?= h($label) ?></strong>
          <span class="lembut"><?= h(date('d-m-Y H:i', strtotime($r['waktu']))) ?></span>
          <?php if ($r['catatan']): ?><div class="lembut"><?= h($r['catatan']) ?></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
