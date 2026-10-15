<?php
/**
 * Halaman Vendor Management: tab Dashboard + tab daftar pengajuan/rekanan.
 * @var string $segmen @var array $segmenDaftar @var array $jumlahSegmen @var array $status @var bool $bisaManual
 * Mode daftar: @var array $filter @var array $baris @var int $total @var int $halaman @var int $jumlahHalaman
 *              @var string[] $level @var array $matriks @var array $pilihan
 * Mode dashboard: @var array $kpi @var int $total @var array $terbaru @var array $jadwal @var string[] $peran
 */
$modeDashboard = $segmen === 'dashboard';
$filter = $filter ?? ['q' => '', 'bidang' => '', 'level' => '', 'sumber' => '', 'status' => '', 'segmen' => $segmen];

$tautan = static function (array $ubah) use ($filter): string {
    $p = array_filter(array_merge($filter, ['halaman' => ''], $ubah), static fn ($v) => $v !== '' && $v !== null);
    return h(url('staf/vendor')) . ($p ? '&amp;' . http_build_query($p, '', '&amp;') : '');
};
$ekspor = array_filter(['bidang' => $filter['bidang'], 'level' => $filter['level'], 'q' => $filter['q']], static fn ($v) => $v !== '');
$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
?>
<h1>Vendor Management</h1>

<div class="baris-judul bar-atas-halaman">
  <p class="lembut">Dashboard, pengajuan, dan rekanan pada satu halaman. Level klasifikasi adalah hasil <strong>simulasi</strong>, bukan ketentuan resmi PPU.</p>
  <div class="aksi-halaman">
    <?php if ($bisaManual): ?>
      <a class="tombol tombol-sekunder tombol-kecil" href="<?= h(url('staf/manual')) ?>"><?= ikon('file-plus') ?> Catat Pengajuan Manual</a>
    <?php endif; ?>
    <?php if (!$modeDashboard): ?>
      <a class="tombol tombol-sekunder tombol-kecil" href="<?= h(url('staf/rekanan/ekspor')) . ($ekspor ? '&amp;' . http_build_query($ekspor, '', '&amp;') : '') ?>"><?= ikon('archive') ?> Ekspor CSV</a>
    <?php endif; ?>
  </div>
</div>

<nav class="segmen" aria-label="Bagian Vendor Management">
  <a class="<?= $modeDashboard ? 'aktif' : '' ?>" href="<?= h(url('staf/vendor')) ?>&amp;segmen=dashboard" <?= $modeDashboard ? 'aria-current="page"' : '' ?>>
    <?= ikon('home') ?> Dashboard
  </a>
  <?php foreach ($segmenDaftar as $kode => [$label]): ?>
    <a class="<?= $segmen === $kode ? 'aktif' : '' ?>" href="<?= h(url('staf/vendor')) ?>&amp;segmen=<?= h($kode) ?>"
       <?= $segmen === $kode ? 'aria-current="page"' : '' ?>>
      <?= h($label) ?> <span class="hitung"><?= (int) $jumlahSegmen[$kode] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($modeDashboard): ?>
  <?php require __DIR__ . '/ringkasan.php'; ?>
<?php else: ?>

<form class="kartu filter-bar" method="get" action="index.php">
  <input type="hidden" name="hal" value="staf/vendor">
  <input type="hidden" name="segmen" value="<?= h($filter['segmen']) ?>">
  <div>
    <label for="q">Cari</label>
    <input type="text" id="q" name="q" maxlength="100" value="<?= h($filter['q']) ?>" placeholder="Nama, nomor, atau NIB">
  </div>
  <div>
    <label for="bidang">Kategori</label>
    <select id="bidang" name="bidang">
      <option value="">Semua kategori</option>
      <?php foreach ($pilihan['bidang_usaha'] as $b): ?>
        <option value="<?= h($b) ?>" <?= $filter['bidang'] === $b ? 'selected' : '' ?>><?= h($b) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="level">Level</label>
    <select id="level" name="level">
      <option value="">Semua level</option>
      <?php foreach ($level as $l): ?>
        <option value="<?= h($l) ?>" <?= $filter['level'] === $l ? 'selected' : '' ?>><?= h($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="sumber">Sumber</label>
    <select id="sumber" name="sumber">
      <option value="">Semua sumber</option>
      <option value="portal_vendor_management" <?= $filter['sumber'] === 'portal_vendor_management' ? 'selected' : '' ?>>Portal</option>
      <option value="proses_manual" <?= $filter['sumber'] === 'proses_manual' ? 'selected' : '' ?>>Proses manual</option>
    </select>
  </div>
  <div style="flex:0 0 auto">
    <button class="tombol" type="submit"><?= ikon('search') ?> Terapkan</button>
    <a class="tombol tombol-sekunder" href="<?= h(url('staf/vendor')) ?>&amp;segmen=<?= h($filter['segmen']) ?>">Reset</a>
  </div>
</form>

<div class="kartu">
  <p class="lembut"><?= (int) $total ?> pengajuan ditemukan.</p>
  <?php if ($baris === []): ?>
    <div class="kartu-kosong"><?= ikon('inbox') ?><p>Tidak ada pengajuan yang sesuai dengan tahap atau filter yang dipilih.</p></div>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Vendor</th><th>Level</th><th>Status</th><th>Waktu</th></tr></thead>
    <tbody>
    <?php foreach ($baris as $r):
        $ts = $r['jadwal'] ? strtotime((string) $r['jadwal']) : null; ?>
      <tr>
        <td>
          <a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $r['id_pengajuan'] ?>"><?= h($r['nama_perusahaan']) ?></a>
          <div class="lembut"><?= h($r['nomor_pengajuan']) ?><?= $r['sumber_pengajuan'] === 'proses_manual' ? ' &middot; manual' : '' ?></div>
          <div class="lembut"><?= h($r['bidang_usaha'] ?? '-') ?></div>
        </td>
        <td><?= $r['level'] ? '<span class="status status-hijau">' . h($r['level']) . '</span>' : '<span class="lembut">-</span>' ?></td>
        <td><?= lencanaStatus($r['status_pengajuan'], $status) ?></td>
        <td class="sel-waktu">
          <div>Diajukan <?= tgl($r['tanggal_diajukan'], 'd-m-Y') ?></div>
          <?php if ($ts): ?><div class="lembut">Verifikasi <?= h(date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('H:i', $ts)) ?></div><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($jumlahHalaman > 1): ?>
    <nav class="paginasi" aria-label="Halaman">
      <?php for ($i = 1; $i <= $jumlahHalaman; $i++): ?>
        <?php if ($i === $halaman): ?><span class="sekarang"><?= $i ?></span>
        <?php else: ?><a href="<?= $tautan(['halaman' => (string) $i]) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>

<details class="bagian">
  <summary>Ringkasan rekanan per kategori dan level <span class="lencana lencana-simulasi">SIMULASI</span></summary>
  <div class="bagian-isi">
    <?php if ($matriks === []): ?>
      <div class="kartu-kosong"><?= ikon('inbox') ?><p>Belum ada vendor dengan klasifikasi yang ditetapkan.</p></div>
    <?php else: ?>
      <p class="lembut">Klik angka untuk menyaring tabel di atas pada kategori dan level tersebut.</p>
      <div class="tabel-bungkus"><table class="tabel">
        <thead><tr><th>Kategori</th><?php foreach ($level as $l): ?><th><?= h($l) ?></th><?php endforeach; ?><th>Jumlah</th></tr></thead>
        <tbody>
        <?php foreach ($matriks as $bidang => $perLevel): ?>
          <tr>
            <td><a href="<?= $tautan(['bidang' => $bidang, 'level' => '', 'segmen' => 'semua']) ?>"><?= h($bidang) ?></a></td>
            <?php foreach ($level as $l): $n = $perLevel[$l] ?? 0; ?>
              <td><?= $n > 0 ? '<a href="' . $tautan(['bidang' => $bidang, 'level' => $l, 'segmen' => 'semua']) . '">' . $n . '</a>' : '<span class="lembut">0</span>' ?></td>
            <?php endforeach; ?>
            <td><strong><?= (int) array_sum($perLevel) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</details>

<?php endif; ?>
