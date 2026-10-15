<?php
/** @var string $konten @var string $judul @var array $pengguna */
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Services\LencanaMenuService;

$menu = require BASE_PATH . '/config/menu_staf.php';
$halSekarang = (string) ($_GET['hal'] ?? '');
$jenisSekarang = (string) ($_GET['jenis'] ?? '');
$sesiSiap = Env::ambil('SUMBER_LOGIN', 'lokal') === 'sesi_siap';
$peranLabel = implode(', ', array_map([Auth::class, 'labelPeran'], $pengguna['peran']));
$angka = (new LencanaMenuService())->hitung();

/** Menentukan apakah butir menu sedang aktif dan alamatnya. @return array{0: bool, 1: string} */
$butir = static function (array $i) use ($halSekarang, $jenisSekarang): array {
    $aktif = false;
    foreach ($i['cocok'] as $c) {
        if ($halSekarang === $c || str_starts_with($halSekarang, $c . '/')) {
            $aktif = true;
        }
    }
    $href = url($i['hal']);
    if (isset($i['jenis'])) {
        $href .= '&jenis=' . $i['jenis'];
        $aktif = $aktif && $jenisSekarang === $i['jenis'];
    }
    return [$aktif, $href];
};
$boleh = static fn (array $i): bool => ($i['hak'] ?? null) === null || Auth::punyaHak($i['hak']);
$lencana = static function (int $n): string {
    return $n > 0
        ? '<span class="lencana-menu" aria-label="' . $n . ' menunggu tindakan">' . ($n > 99 ? '99+' : $n) . '</span>'
        : '';
};
$pengumuman = 'PROTOTYPE LOKAL — simulasi modul Vendor Management di SIAP PPU, belum terintegrasi. '
            . 'Persyaratan, klasifikasi, dan TDR adalah simulasi.';
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#3a8ee6">
<title><?= h($judul) ?> | Vendor Management PPU</title>
<link rel="stylesheet" href="assets/css/app.css">
<script src="assets/js/app.js" defer></script>
</head>
<body class="tubuh-siap">
<?php require __DIR__ . '/partials/ikon.php'; ?>
<a class="lewati" href="#konten">Lewati ke konten utama</a>

<header class="siap-header">
  <button type="button" class="siap-hamburger pemicu-sidebar" aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu">
    <?= ikon('menu') ?>
  </button>
  <a class="siap-merek" href="<?= h(url('staf/dashboard')) ?>">
    <span class="siap-logo"><img src="assets/img/logo-header.png" alt=""></span>
    <span class="siap-jejak">Home / <?= h($judul) ?></span>
  </a>
  <div class="siap-ticker" role="note" aria-label="Pemberitahuan prototype">
    <span><?= h($pengumuman) ?></span>
  </div>
  <span class="siap-pil-prototype" aria-hidden="true">PROTOTYPE</span>
  <div class="siap-pengguna">
    <button type="button" class="siap-pengguna-tombol" aria-haspopup="true" aria-expanded="false" aria-controls="menu-pengguna">
      <strong><?= h($pengguna['nama']) ?></strong>
      <span class="siap-avatar" aria-hidden="true"><?= h(inisial($pengguna['nama'])) ?></span>
    </button>
    <div class="siap-menu-pengguna" id="menu-pengguna" hidden>
      <div class="siap-menu-pengguna-info">
        <strong><?= h($pengguna['nama']) ?></strong>
        <small><?= h($peranLabel) ?></small>
      </div>
      <?php if ($sesiSiap): ?>
        <p class="siap-menu-pengguna-catatan">Keluar dilakukan melalui SIAP.</p>
      <?php else: ?>
        <form method="post" action="<?= h(url('keluar')) ?>">
          <?= Csrf::field() ?>
          <button class="siap-keluar" type="submit"><?= ikon('logout') ?> Keluar</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="badan-siap">
  <aside class="sidebar" id="sidebar" aria-label="Menu modul">
    <ul class="menu-siap">
      <?php foreach ($menu as $m):
          if (isset($m['item'])) {
              $anak = array_values(array_filter($m['item'], $boleh));
              if (!$anak) { continue; }
              $grupAktif = false;
              $jumlahModul = 0;
              foreach ($anak as $a) {
                  if ($butir($a)[0]) { $grupAktif = true; }
                  $jumlahModul += (int) ($angka[$a['lencana'] ?? ''] ?? 0);
              }
              $idGrup = 'submenu-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($m['label'])); ?>
        <li class="menu-grup <?= $grupAktif ? 'aktif-grup' : '' ?>">
          <button type="button" class="menu-tautan menu-pemicu" aria-expanded="false" aria-controls="<?= h($idGrup) ?>" aria-haspopup="true">
            <span class="menu-label"><?= h($m['label']) ?></span>
            <?= $lencana($jumlahModul) ?>
            <svg class="ikon caret" aria-hidden="true" focusable="false"><use href="#i-chevron-down"/></svg>
          </button>
          <ul class="submenu-siap" id="<?= h($idGrup) ?>">
            <?php foreach ($anak as $a):
                [$aktif, $href] = $butir($a); ?>
              <li>
                <a class="<?= $aktif ? 'aktif' : '' ?>" href="<?= h($href) ?>" <?= $aktif ? 'aria-current="page"' : '' ?>>
                  <span><?= h($a['label']) ?></span>
                  <?= $lencana((int) ($angka[$a['lencana'] ?? ''] ?? 0)) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </li>
      <?php } else {
              if (!$boleh($m)) { continue; }
              [$aktif, $href] = $butir($m); ?>
        <li>
          <a class="menu-tautan <?= $aktif ? 'aktif' : '' ?>" href="<?= h($href) ?>" <?= $aktif ? 'aria-current="page"' : '' ?>>
            <span class="menu-label"><?= h($m['label']) ?></span>
            <?= $lencana(array_sum(array_map(static fn ($k) => (int) ($angka[$k] ?? 0), $m['lencana_gabung'] ?? []))) ?>
          </a>
        </li>
      <?php } endforeach; ?>
    </ul>
  </aside>
  <div class="tirai" aria-hidden="true"></div>

  <main id="konten" class="area-siap">
    <section class="panel" aria-labelledby="judul-panel">
      <h1 class="panel-judul" id="judul-panel"><?= ikon('menu') ?> <?= h($judul) ?></h1>
      <div class="panel-isi">
        <?php require __DIR__ . '/partials/flash.php'; ?>
        <?= $konten ?>
      </div>
    </section>
  </main>
</div>
</body>
</html>
