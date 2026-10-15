<?php
/** @var array $pemeriksaan @var array $tabel @var ?string $galat @var string $sumberUser */
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Uji Koneksi - Prototype Vendor Management</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:0;background:#f4f6f9;color:#1f2933}
  main{max-width:760px;margin:0 auto;padding:24px 16px}
  .banner{background:#fff3cd;border:1px solid #ffe69c;padding:12px 16px;border-radius:8px;font-size:14px}
  h1{font-size:22px;margin:20px 0 8px}
  ul{list-style:none;padding:0}
  li{background:#fff;margin:6px 0;padding:10px 14px;border-radius:8px;border:1px solid #e1e5ea}
  .ok{color:#137333;font-weight:600}.gagal{color:#b3261e;font-weight:600}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden}
  th,td{text-align:left;padding:8px 12px;border-bottom:1px solid #e1e5ea;font-size:14px}
  pre{background:#fff;padding:12px;border-radius:8px;overflow:auto}
</style>
</head>
<body>
<main>
  <div class="banner"><strong>PROTOTYPE LOKAL.</strong> Bukan bagian dari website PPU maupun SIAP PPU, dan belum terintegrasi dengan keduanya.</div>
  <h1>Uji Koneksi dan Lingkungan</h1>
  <ul>
    <?php foreach ($pemeriksaan as [$label, $baik]): ?>
      <li><span class="<?= $baik ? 'ok' : 'gagal' ?>"><?= $baik ? 'OK' : 'GAGAL' ?></span> &mdash; <?= h($label) ?></li>
    <?php endforeach; ?>
    <li>Sumber akun staf (SUMBER_USER): <strong><?= h($sumberUser) ?></strong></li>
  </ul>
  <?php if ($galat !== null): ?>
    <h1>Pesan kesalahan</h1><pre><?= h($galat) ?></pre>
  <?php endif; ?>
  <?php if ($tabel): ?>
    <h1>Tabel di database prototype</h1>
    <table>
      <tr><th>Tabel</th><th>Jumlah baris</th></tr>
      <?php foreach ($tabel as [$nama, $jumlah]): ?>
        <tr><td><?= h($nama) ?></td><td><?= $jumlah ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
