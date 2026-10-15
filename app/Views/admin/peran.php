<?php
/** @var string $q @var array $hasilCari @var array $peran @var array $nama @var string[] $pilihan @var array $label */
use App\Core\Csrf;
?>
<h1>Peran Pengguna</h1>
<p class="lembut">Pemetaan manual: pegawai mana berperan apa di Vendor Management. Data pegawai dibaca dari <code>tbl_user</code>; tabel itu tidak diubah.</p>

<div class="kartu">
  <h2>Tetapkan peran</h2>
  <form class="filter-bar" method="get" action="index.php">
    <input type="hidden" name="hal" value="admin/pengaturan">
    <input type="hidden" name="bagian" value="peran">
    <div>
      <label for="q">Cari pegawai (nama atau userid)</label>
      <input type="text" id="q" name="q" maxlength="50" value="<?= h($q) ?>">
    </div>
    <div style="flex:0 0 auto"><button class="tombol" type="submit">Cari</button></div>
  </form>
  <?php if ($q !== ''): ?>
    <?php if ($hasilCari === []): ?>
      <p class="lembut">Tidak ada pegawai yang cocok.</p>
    <?php else: ?>
      <div class="tabel-bungkus"><table class="tabel">
        <thead><tr><th>Pegawai</th><th>Dept / Divisi</th><th>Tetapkan</th></tr></thead>
        <tbody>
        <?php foreach ($hasilCari as $u): ?>
          <tr>
            <td><?= h($u['nama']) ?><div class="lembut"><?= h($u['userid']) ?></div></td>
            <td><?= h($u['dept'] ?? '-') ?> / <?= h($u['divisi'] ?? '-') ?></td>
            <td>
              <form method="post" action="<?= h(url('admin/peran/simpan')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="id_user" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="aktif" value="1">
                <select name="peran" aria-label="Peran untuk <?= h($u['nama']) ?>">
                  <?php foreach ($pilihan as $p): ?><option value="<?= h($p) ?>"><?= h($label[$p]) ?></option><?php endforeach; ?>
                </select>
                <button class="tombol tombol-kecil" type="submit">Aktifkan</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="kartu">
  <h2>Peran yang sudah ditetapkan</h2>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Pegawai</th><th>Peran</th><th>Status</th><th>Ditetapkan</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($peran as $r):
        $u = $nama[(int) $r['id_user']] ?? null; ?>
      <tr>
        <td><?= h($u['nama'] ?? '(tidak ditemukan)') ?><div class="lembut"><?= h($r['userid']) ?></div></td>
        <td><?= h($label[$r['peran']] ?? $r['peran']) ?></td>
        <td><?= (int) $r['aktif'] === 1 ? '<span class="status status-hijau">Aktif</span>' : '<span class="status status-abu">Nonaktif</span>' ?></td>
        <td><?= tgl($r['ditetapkan_pada']) ?></td>
        <td>
          <form method="post" action="<?= h(url('admin/peran/simpan')) ?>" data-konfirmasi="Ubah status peran ini?">
            <?= Csrf::field() ?>
            <input type="hidden" name="id_user" value="<?= (int) $r['id_user'] ?>">
            <input type="hidden" name="peran" value="<?= h($r['peran']) ?>">
            <input type="hidden" name="aktif" value="<?= (int) $r['aktif'] === 1 ? '0' : '1' ?>">
            <button class="tombol tombol-kecil tombol-sekunder" type="submit"><?= (int) $r['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
