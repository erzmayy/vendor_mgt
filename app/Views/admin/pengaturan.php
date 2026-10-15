<?php
/** @var array<int,array{kode:string,judul:string,html:string}> $bagian @var string $aktif */
?>
<h1>Pengaturan Vendor</h1>
<p class="lembut">Seluruh pengaturan modul pada satu halaman. Klik judul bagian untuk membuka atau menutupnya.
  Semua isian awal adalah <strong>contoh sementara</strong> sampai ketentuan resmi PPU dikonfirmasi.</p>

<nav class="loncat-bagian" aria-label="Loncat ke bagian">
  <?php foreach ($bagian as $b): ?>
    <a href="#bagian-<?= h($b['kode']) ?>"><?= h(preg_replace('/\s*\(simulasi\)/', '', $b['judul'])) ?></a>
  <?php endforeach; ?>
</nav>

<?php foreach ($bagian as $b): ?>
  <details class="bagian" id="bagian-<?= h($b['kode']) ?>" <?= $b['kode'] === $aktif ? 'open' : '' ?>>
    <summary><?= h($b['judul']) ?></summary>
    <div class="bagian-isi"><?= $b['html'] /* HTML hasil render view yang sudah di-escape */ ?></div>
  </details>
<?php endforeach; ?>
