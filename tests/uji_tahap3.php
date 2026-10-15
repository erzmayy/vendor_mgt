<?php
declare(strict_types=1);

/**
 * Uji otomatis Tahap 3: autentikasi, CSRF, dan hak akses di backend.
 * Cara pakai (server harus berjalan):
 *   php -S 127.0.0.1:8099 -t public
 *   php tests/uji_tahap3.php http://127.0.0.1:8099
 * Menyentuh database prototype lokal (menambah baris log).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$dasar = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
const SANDI = 'DemoLokal#2026';

$lulus = 0;
$gagal = 0;

function cek(string $nama, bool $kondisi, string $info = ''): void
{
    global $lulus, $gagal;
    if ($kondisi) {
        $lulus++;
        echo "  [LULUS] {$nama}\n";
    } else {
        $gagal++;
        echo "  [GAGAL] {$nama}" . ($info !== '' ? "  -> {$info}" : '') . "\n";
    }
}

require __DIR__ . "/Klien.php";

echo "== 1. Halaman publik dan akses tanpa login ==\n";
$k = new Klien($dasar);
foreach (['beranda', 'masuk-staf', 'masuk-vendor'] as $h) {
    cek("{$h} dapat dibuka tanpa login", $k->minta($h)['kode'] === 200);
}
$r = $k->minta('staf/dashboard');
cek('staf/dashboard tanpa login dialihkan ke masuk-staf', $r['kode'] === 302 && str_contains($r['lokasi'], 'masuk-staf'), $r['lokasi']);
$r = $k->minta('vendor/dashboard');
cek('vendor/dashboard tanpa login dialihkan ke masuk-vendor', $r['kode'] === 302 && str_contains($r['lokasi'], 'masuk-vendor'), $r['lokasi']);
cek('halaman tidak dikenal -> 404', $k->minta('tidak-ada')['kode'] === 404);
$r = $k->minta('<script>alert(1)</script>');
cek('input rute tidak dipantulkan mentah (XSS)', !str_contains($r['isi'], '<script>alert(1)</script>'));

echo "== 2. CSRF dan metode ==\n";
$r = $k->minta('masuk-staf/proses', 'POST', ['userid' => 'demo_admin', 'kata_sandi' => SANDI]);
cek('POST login tanpa token CSRF ditolak (400)', $r['kode'] === 400, (string) $r['kode']);
$r = $k->minta('masuk-staf/proses', 'POST', ['_csrf' => 'salah', 'userid' => 'demo_admin', 'kata_sandi' => SANDI]);
cek('POST login dengan token CSRF salah ditolak (400)', $r['kode'] === 400);
cek('logout lewat GET ditolak (405)', $k->minta('keluar')['kode'] === 405);

echo "== 3. Login staf: gagal ==\n";
$r = $k->masukStaf('demo_admin', 'salah-sandi');
cek('sandi salah dialihkan kembali ke form', $r['kode'] === 302 && str_contains($r['lokasi'], 'masuk-staf'));
cek('setelah sandi salah tetap belum login', $k->minta('staf/dashboard')['kode'] === 302);
$r = $k->masukStaf('demo_tanpa_peran', SANDI);
cek('akun tanpa peran tidak dapat masuk', $r['kode'] === 302 && str_contains($r['lokasi'], 'masuk-staf'));
cek('akun tanpa peran tetap belum login', $k->minta('staf/dashboard')['kode'] === 302);
$r = $k->masukStaf('tidak_ada_' . uniqid(), SANDI);
cek('akun tidak ada ditolak', $r['kode'] === 302 && str_contains($r['lokasi'], 'masuk-staf'));

echo "== 4. Matriks hak akses staf (ditegakkan di backend) ==\n";
$harapan = [
    //  userid        => [staf/pengajuan, staf/penetapan, admin/peran]
    'demo_plan'  => [200, 403, 403],
    'demo_ver1'  => [200, 403, 403],
    'demo_ver2'  => [200, 403, 403],
    'demo_auth'  => [200, 200, 403],
    'demo_admin' => [200, 403, 200],
];
foreach ($harapan as $userid => [$a, $b, $c]) {
    $s = new Klien($dasar);
    $sebelum = $s->csrf('masuk-staf') !== '' ? $s->idSesi() : '';
    $r = $s->masukStaf($userid, SANDI);
    cek("{$userid} dapat login", $r['kode'] === 302 && str_contains($r['lokasi'], 'staf/dashboard'), $r['lokasi']);
    cek("{$userid}: ID session berganti setelah login", $s->idSesi() !== '' && $s->idSesi() !== $sebelum);
    cek("{$userid}: staf/dashboard 200", $s->minta('staf/dashboard')['kode'] === 200);
    cek("{$userid}: staf/pengajuan {$a}", $s->minta('staf/pengajuan')['kode'] === $a);
    cek("{$userid}: staf/penetapan {$b}", $s->minta('staf/penetapan')['kode'] === $b);
    cek("{$userid}: admin/peran {$c}", $s->minta('admin/peran')['kode'] === $c);
    cek("{$userid}: halaman vendor ditolak (403)", $s->minta('vendor/dashboard')['kode'] === 403);
    cek("{$userid}: vendor/formulir ditolak (403)", $s->minta('vendor/formulir')['kode'] === 403);
}

echo "== 5. Login vendor ==\n";
$v = new Klien($dasar);
$r = $v->masukVendor('vendor.demo@contoh.test', 'salah');
cek('vendor sandi salah ditolak', $r['kode'] === 302 && str_contains($r['lokasi'], 'masuk-vendor'));
$r = $v->masukVendor('vendor.demo@contoh.test', SANDI);
cek('vendor dapat login', $r['kode'] === 302 && str_contains($r['lokasi'], 'vendor/dashboard'), $r['lokasi']);
cek('vendor/dashboard 200', $v->minta('vendor/dashboard')['kode'] === 200);
cek('vendor/formulir 200', $v->minta('vendor/formulir')['kode'] === 200);
cek('vendor tidak boleh staf/dashboard (403)', $v->minta('staf/dashboard')['kode'] === 403);
cek('vendor tidak boleh staf/pengajuan (403)', $v->minta('staf/pengajuan')['kode'] === 403);
cek('vendor tidak boleh admin/peran (403)', $v->minta('admin/peran')['kode'] === 403);

echo "== 6. Logout ==\n";
$t = $v->csrf('vendor/dashboard');
$r = $v->minta('keluar', 'POST', ['_csrf' => $t]);
cek('logout POST + CSRF berhasil (302)', $r['kode'] === 302, (string) $r['kode']);
cek('setelah logout dashboard tidak bisa dibuka', $v->minta('vendor/dashboard')['kode'] === 302);

echo "== 7. Pembatasan percobaan login ==\n";
$uji = 'uji_kunci_' . uniqid();
$pesan = '';
$s = new Klien($dasar);
for ($i = 0; $i < 6; $i++) {
    $s->masukStaf($uji, 'salah');
    $form = $s->minta('masuk-staf');
    if (preg_match('/<div class="galat"[^>]*>(.*?)<\/div>/s', $form['isi'], $m)) {
        $pesan = html_entity_decode($m[1]);
    }
}
cek('setelah 5 kali gagal akun dikunci sementara', str_contains($pesan, 'Terlalu banyak'), $pesan);

echo "== 8. Audit log ==\n";
$pdo = App\Core\Database::prototype();
foreach (['login_berhasil', 'login_gagal', 'akses_ditolak', 'csrf_ditolak', 'logout'] as $aksi) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE aksi = '{$aksi}'")->fetchColumn();
    cek("audit log memuat aksi {$aksi}", $n > 0);
}
$n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE detail LIKE '%DemoLokal%'")->fetchColumn();
cek('audit log tidak menyimpan kata sandi', $n === 0);
$n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_user WHERE userid='demo_admin' AND lastlogin IS NOT NULL")->fetchColumn();
cek('lastlogin staf tercatat', $n === 1);

// Bersihkan jejak gagal-login hasil uji agar akun demo tidak terkunci pada pengujian berikutnya.
$pdo->exec(
    "DELETE FROM tbl_log_aktivitas WHERE aksi = 'login_gagal'
       AND (JSON_UNQUOTE(JSON_EXTRACT(detail, '$.identitas')) LIKE 'staf:demo\\_%'
         OR JSON_UNQUOTE(JSON_EXTRACT(detail, '$.identitas')) LIKE 'staf:uji\\_kunci\\_%'
         OR JSON_UNQUOTE(JSON_EXTRACT(detail, '$.identitas')) LIKE 'staf:tidak\\_ada\\_%'
         OR JSON_UNQUOTE(JSON_EXTRACT(detail, '$.identitas')) = 'vendor:vendor.demo@contoh.test')"
);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
