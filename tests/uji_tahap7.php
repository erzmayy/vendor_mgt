<?php
declare(strict_types=1);

/**
 * Tahap 7: pengujian menyeluruh lintas fitur.
 *  1. matriks akses (ditulis tangan dari ketentuan, bukan dari konfigurasi)
 *  2. sapuan CSRF dan metode HTTP
 *  3. XSS tersimpan di seluruh alur dan halaman
 *  4. SQL injection dan input aneh
 *  5. header keamanan, cookie, dan berkas tidak dapat diakses langsung
 *  6. permintaan bersamaan (race condition)
 *  7. penanganan galat tak terduga (tanpa membocorkan rincian)
 *
 *   php -S 127.0.0.1:8099 -t public
 *   php tests/uji_tahap7.php http://127.0.0.1:8099
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Klien.php';
require __DIR__ . '/Bantu.php';

use App\Core\Database;

$dasar = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
const SANDI = 'DemoLokal#2026';
const SANDI_VENDOR = 'SandiUji12345';

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

$pdo = Database::prototype();
$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
$XSS = '"><script>alert(7)</script><img src=x onerror=alert(8)>';

function bersihkan(PDO $pdo): void
{
    $vendor = $pdo->query(
        "SELECT v.id_vendor FROM tbl_vendor v JOIN tbl_akun_vendor a ON a.id_akun_vendor = v.id_akun_vendor
          WHERE a.email LIKE '%@uji.test'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($vendor) {
        $iv = implode(',', array_map('intval', $vendor));
        $peng = $pdo->query("SELECT id_pengajuan FROM tbl_pengajuan_vendor WHERE id_vendor IN ({$iv})")->fetchAll(PDO::FETCH_COLUMN);
        if ($peng) {
            $ip = implode(',', array_map('intval', $peng));
            foreach (['tbl_jawaban_vendor', 'tbl_catatan_tdr', 'tbl_penetapan_klasifikasi', 'tbl_rekomendasi_klasifikasi',
                      'tbl_hasil_verifikasi', 'tbl_jadwal_verifikasi', 'tbl_dokumen_vendor', 'tbl_riwayat_status', 'tbl_log_aktivitas'] as $t) {
                $pdo->exec("DELETE FROM {$t} WHERE id_pengajuan IN ({$ip})");
            }
            $pdo->exec("DELETE FROM tbl_pengajuan_vendor WHERE id_pengajuan IN ({$ip})");
            foreach ($peng as $id) {
                $dir = BASE_PATH . '/storage/uploads/' . (int) $id;
                foreach (glob($dir . '/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
            }
        }
        $pdo->exec("DELETE FROM tbl_vendor WHERE id_vendor IN ({$iv})");
    }
    // pengajuan manual uji
    $man = $pdo->query("SELECT id_vendor FROM tbl_vendor WHERE nib LIKE 'UJI7-%' OR nama_perusahaan LIKE 'PT Uji Tujuh%'")->fetchAll(PDO::FETCH_COLUMN);
    if ($man) {
        $im = implode(',', array_map('intval', $man));
        $peng = $pdo->query("SELECT id_pengajuan FROM tbl_pengajuan_vendor WHERE id_vendor IN ({$im})")->fetchAll(PDO::FETCH_COLUMN);
        if ($peng) {
            $ip = implode(',', array_map('intval', $peng));
            foreach (['tbl_riwayat_status', 'tbl_log_aktivitas', 'tbl_dokumen_vendor'] as $t) {
                $pdo->exec("DELETE FROM {$t} WHERE id_pengajuan IN ({$ip})");
            }
            $pdo->exec("DELETE FROM tbl_pengajuan_vendor WHERE id_pengajuan IN ({$ip})");
        }
        $pdo->exec("DELETE FROM tbl_vendor WHERE id_vendor IN ({$im})");
    }
    $akun = $pdo->query("SELECT id_akun_vendor FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchAll(PDO::FETCH_COLUMN);
    if ($akun) {
        $ia = implode(',', array_map('intval', $akun));
        $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE tipe_pelaku='vendor' AND id_pelaku IN ({$ia})");
        $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi IN ('registrasi_vendor','profil_vendor_dibuat')
                      AND (JSON_EXTRACT(detail,'$.id_akun_vendor') IN ({$ia}) OR id_objek IN ({$ia}))");
        $pdo->exec("DELETE FROM tbl_akun_vendor WHERE id_akun_vendor IN ({$ia})");
    }
    // Log akses ditolak / CSRF / login gagal hasil uji sengaja dibiarkan (jejak audit tidak dihapus).
    // Identitas uji pada login_gagal dibersihkan agar pembatasan percobaan tidak mengunci akun demo.
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi='login_gagal'
                  AND (JSON_UNQUOTE(JSON_EXTRACT(detail,'$.identitas')) LIKE 'staf:demo\\_%'
                    OR JSON_UNQUOTE(JSON_EXTRACT(detail,'$.identitas')) LIKE 'vendor:%@uji.test')");
}

function berkas(string $isi, string $nama): CURLFile
{
    $dir = sys_get_temp_dir() . '/vmp_uji7';
    @mkdir($dir);
    file_put_contents($dir . '/' . $nama, $isi);
    return new CURLFile($dir . '/' . $nama, 'application/octet-stream', $nama);
}

/** Vendor mengajukan sampai status diajukan. @return array{0:Klien,1:int} */
function ajukan(string $dasar, PDO $pdo, string $email, string $nama, string $pdf, string $xss = '', string $nib = ''): array
{
    $v = new Klien($dasar);
    $v->kirim('daftar-vendor/proses', [
        'nama_perusahaan' => $nama, 'nama_kontak' => $xss !== '' ? 'Kontak ' . $xss : 'Kontak Uji', 'email' => $email,
        'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
    ], 'daftar-vendor');
    $v->masukVendor($email, SANDI_VENDOR);
    $v->kirim('vendor/formulir/simpan', [
        'langkah' => '1', 'nama_perusahaan' => $nama, 'bentuk_badan_usaha' => 'PT', 'nib' => $nib,
        'bidang_usaha' => 'Pelatihan dan sertifikasi', 'alamat' => 'Jl. Tujuh Uji No. 7 ' . $xss,
        'nama_penanggung_jawab' => 'PJ Uji ' . $xss, 'email_perusahaan' => 'k-' . $email, 'no_telepon' => '021-' . random_int(100000, 999999),
    ], 'vendor/formulir');
    if ($xss !== '') {
        $v->kirim('vendor/formulir/simpan', ['langkah' => '2', 'situs_web' => $xss, 'pengalaman_singkat' => $xss], 'vendor/formulir');
    }
    jawabSemuaPertanyaan($v, $pdo);
    $syarat = $pdo->query('SELECT id_syarat, kode_syarat FROM tbl_syarat_dokumen WHERE aktif=1 AND wajib=1')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($syarat as $idS => $kode) {
        $v->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $idS, 'berkas' => berkas($pdf, "{$kode}.pdf")], 'vendor/formulir', '&langkah=4');
    }
    $v->kirim('vendor/kirim', [], 'vendor/dashboard');
    $id = (int) $pdo->query(
        "SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor vv ON vv.id_vendor=p.id_vendor
          JOIN tbl_akun_vendor a ON a.id_akun_vendor=vv.id_akun_vendor WHERE a.email=" . $pdo->quote($email)
    )->fetchColumn();
    return [$v, $id];
}

/** Permintaan mentah ke URL (tanpa index.php), untuk menguji akses berkas langsung. */
function mentah(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 10]);
    $r = (string) curl_exec($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $h = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['kode' => $kode, 'header' => substr($r, 0, $h), 'isi' => substr($r, $h)];
}

bersihkan($pdo);

$vend = null;
$ver = new Klien($dasar);  $ver->masukStaf('demo_ver1', SANDI);
$plan = new Klien($dasar); $plan->masukStaf('demo_plan', SANDI);
$auth = new Klien($dasar); $auth->masukStaf('demo_auth', SANDI);
$adm = new Klien($dasar);  $adm->masukStaf('demo_admin', SANDI);
$anon = new Klien($dasar);

[$vend, $idP] = ajukan($dasar, $pdo, 'pemilik7@uji.test', 'PT Uji Tujuh Pemilik', $PDF);
[$vendLain] = ajukan($dasar, $pdo, 'lain7@uji.test', 'PT Uji Tujuh Lain', $PDF);
$idDok = (int) $pdo->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$idP} LIMIT 1")->fetchColumn();
$stat = static fn (int $id) => $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$id}")->fetchColumn();
cek('persiapan: dua vendor mengajukan', $stat($idP) === 'diajukan' && $idDok > 0);

echo "== 1. Matriks akses (ditulis tangan dari ketentuan) ==\n";
// urutan aktor: anon, vendor lain, perencanaan, verifikator, berwenang, admin
$aktor = ['anon' => $anon, 'vendor' => $vendLain, 'plan' => $plan, 'ver' => $ver, 'auth' => $auth, 'adm' => $adm];
$q = '&id=' . $idP;
$matriks = [
    //  rute                                  anon vend plan ver  auth adm
    ['staf/dashboard', '',                   [302, 403, 200, 200, 200, 200]],
    ['staf/pengajuan', '',                   [302, 403, 200, 200, 200, 200]],
    ['staf/pengajuan/detail', $q,            [302, 403, 200, 200, 200, 200]],
    ['staf/jadwal', '',                      [302, 403, 200, 200, 200, 200]],
    ['staf/arsip', '',                       [302, 403, 200, 200, 200, 200]],
    ['staf/penetapan', '',                   [302, 403, 403, 403, 200, 403]],
    ['staf/manual', '',                      [302, 403, 403, 200, 200, 200]],
    ['admin/peran', '',                      [302, 403, 403, 403, 403, 200]],
    ['admin/konfigurasi', '&jenis=aturan',   [302, 403, 403, 403, 403, 200]],
    ['admin/konfigurasi', '&jenis=bobot',    [302, 403, 403, 403, 403, 200]],
    ['admin/konfigurasi', '&jenis=pertanyaan', [302, 403, 403, 403, 403, 200]],
    ['vendor/dashboard', '',                 [302, 200, 403, 403, 403, 403]],
    ['vendor/formulir', '&langkah=3',        [302, 200, 403, 403, 403, 403]],
    ['dokumen/unduh', '&id=' . $idDok,       [302, 404, 403, 200, 403, 403]],
    ['uji-koneksi', '',                      [200, 200, 200, 200, 200, 200]],
    ['beranda', '',                          [200, 200, 200, 200, 200, 200]],
    ['daftar-vendor', '',                    [200, 302, 302, 302, 302, 302]],
    ['masuk-staf', '',                       [200, 302, 302, 302, 302, 302]],
    ['masuk-vendor', '',                     [200, 302, 302, 302, 302, 302]],
];
$namaAktor = array_keys($aktor);
foreach ($matriks as [$hal, $qs, $harap]) {
    foreach ($namaAktor as $i => $a) {
        $r = $aktor[$a]->minta($hal, 'GET', [], $qs);
        cek("{$hal}{$qs} sebagai {$a} = {$harap[$i]}", $r['kode'] === $harap[$i], 'dapat ' . $r['kode']);
    }
}
$r = $vend->minta('dokumen/unduh', 'GET', [], '&id=' . $idDok);
cek('pemilik dokumen = 200', $r['kode'] === 200);

echo "== 2. Sapuan CSRF dan metode HTTP ==\n";
$rute = require BASE_PATH . '/config/rute.php';
$aktorLogin = ['verifikator' => $ver, 'admin' => $adm, 'vendor' => $vend, 'anon' => $anon];
$nPost = 0;
foreach ($rute as $hal => $r) {
    if ($r['http'] !== 'POST') {
        continue;
    }
    $nPost++;
    $semua400 = true;
    $tanpaToken = [];
    foreach ($aktorLogin as $nama => $k) {
        $kode = $k->minta($hal, 'POST', ['id_pengajuan' => '1'])['kode'];
        if ($kode !== 400) {
            $semua400 = false;
            $tanpaToken[] = "{$nama}={$kode}";
        }
    }
    cek("POST {$hal} tanpa token CSRF = 400 untuk semua aktor", $semua400, implode(',', $tanpaToken));
    cek("GET {$hal} (rute POST) = 405", $ver->minta($hal)['kode'] === 405);
}
foreach ($rute as $hal => $r) {
    if ($r['http'] === 'GET') {
        $t = $ver->csrf('staf/dashboard');
        $kode = $ver->minta($hal, 'POST', ['_csrf' => $t])['kode'];
        cek("POST ke rute GET {$hal} = 405", $kode === 405, (string) $kode);
    }
}
cek("seluruh rute POST tersapu ({$nPost})", $nPost >= 20);

echo "== 3. XSS tersimpan di seluruh alur ==\n";
[$vx, $idX] = ajukan($dasar, $pdo, 'xss7@uji.test', 'PT Uji Tujuh Xss ' . $XSS, $PDF, $XSS, 'UJI7-X001');
cek('pengajuan berisi payload XSS berhasil dibuat', $idX > 0 && $stat($idX) === 'diajukan', (string) $idX);
$ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idX], 'staf/dashboard');
$dokX = $pdo->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$idX}")->fetchAll(PDO::FETCH_COLUMN);
$ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idX, 'id_dokumen' => (string) $dokX[0], 'hasil' => 'tidak_sesuai', 'catatan' => $XSS], 'staf/dashboard');
$idQ = (int) $pdo->query("SELECT id_pertanyaan FROM tbl_jawaban_vendor WHERE id_pengajuan={$idX} LIMIT 1")->fetchColumn();
$ver->kirim('staf/jawaban/tandai', ['id_pengajuan' => (string) $idX, 'id_pertanyaan' => (string) $idQ, 'tindakan' => 'tandai', 'catatan' => $XSS], 'staf/dashboard');
$ver->kirim('staf/pengajuan/kembalikan', ['id_pengajuan' => (string) $idX, 'catatan' => 'Perbaiki ' . $XSS], 'staf/dashboard');
cek('payload masuk ke catatan perbaikan', (string) $stat($idX) === 'perlu_perbaikan');
$vx->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $pdo->query("SELECT id_syarat FROM tbl_dokumen_vendor WHERE id_dokumen={$dokX[0]}")->fetchColumn(), 'berkas' => berkas($PDF . "x\n", 'ulang.pdf')], 'vendor/formulir', '&langkah=4');
$vx->kirim('vendor/kirim', [], 'vendor/dashboard');
$ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idX], 'staf/dashboard');
foreach ($pdo->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$idX}")->fetchAll(PDO::FETCH_COLUMN) as $d) {
    $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idX, 'id_dokumen' => (string) $d, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
}
$ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $idX], 'staf/dashboard');
$plan->kirim('staf/jadwal/simpan', [
    'id_pengajuan' => (string) $idX, 'tanggal_verifikasi' => date('Y-m-d\TH:i', strtotime('+2 days 10:00')),
    'metode' => 'kunjungan_lapangan', 'lokasi' => $XSS, 'id_verifikator' => '902', 'catatan' => $XSS,
], 'staf/dashboard');
$ver->kirim('staf/hasil/simpan', [
    'id_pengajuan' => (string) $idX, 'skor_contoh_1' => '70', 'skor_contoh_2' => '70', 'skor_contoh_3' => '70',
    'kesimpulan' => 'memenuhi_sebagian', 'temuan' => $XSS,
], 'staf/dashboard');
$auth->kirim('staf/penetapan/simpan', ['id_pengajuan' => (string) $idX, 'label_klasifikasi' => 'Level C (simulasi)', 'alasan' => 'Alasan ' . $XSS], 'staf/dashboard');
$auth->kirim('staf/tdr/simpan', ['id_pengajuan' => (string) $idX, 'tanggal_proses' => date('Y-m-d'), 'keterangan' => 'TDR ' . $XSS], 'staf/dashboard');
$auth->kirim('staf/pengajuan/selesaikan', ['id_pengajuan' => (string) $idX], 'staf/dashboard');
cek('alur penuh dengan payload selesai (status selesai)', $stat($idX) === 'selesai', (string) $stat($idX));

$halaman = [
    ['vendor', $vx, 'vendor/dashboard', ''],
    ['vendor', $vx, 'vendor/formulir', '&langkah=1'],
    ['vendor', $vx, 'vendor/formulir', '&langkah=2'],
    ['vendor', $vx, 'vendor/formulir', '&langkah=3'],
    ['vendor', $vx, 'vendor/formulir', '&langkah=4'],
    ['vendor', $vx, 'vendor/formulir', '&langkah=5'],
    ['verifikator', $ver, 'staf/pengajuan/detail', '&id=' . $idX],
    ['verifikator', $ver, 'staf/pengajuan', '&q=Xss'],
    ['verifikator', $ver, 'staf/arsip', ''],
    ['verifikator', $ver, 'staf/jadwal', ''],
    ['verifikator', $ver, 'staf/dashboard', ''],
    ['berwenang', $auth, 'staf/pengajuan/detail', '&id=' . $idX],
    ['berwenang', $auth, 'staf/penetapan', ''],
    ['perencanaan', $plan, 'staf/pengajuan/detail', '&id=' . $idX],
    ['admin', $adm, 'staf/pengajuan/detail', '&id=' . $idX],
];
$bocor = [];
foreach ($halaman as [$nama, $k, $hal, $qs]) {
    $isi = $k->minta($hal, 'GET', [], $qs)['isi'];
    foreach (['<script>alert(7)', 'onerror=alert(8)>', '"><script'] as $pola) {
        if (str_contains($isi, $pola)) {
            $bocor[] = "{$nama}:{$hal}{$qs}";
            break;
        }
    }
}
cek('payload XSS tidak muncul mentah di ' . count($halaman) . ' halaman', $bocor === [], implode('; ', $bocor));
$isiDetail = $ver->minta('staf/pengajuan/detail', 'GET', [], '&id=' . $idX)['isi'];
cek('payload tampil sebagai teks yang di-escape', str_contains($isiDetail, '&lt;script&gt;alert(7)&lt;/script&gt;'));
$rt = $ver->minta('staf/manual/simpan', 'POST', []); // tanpa csrf, hanya memastikan tidak ada galat
$ver->kirim('staf/manual/simpan', [
    'nama_perusahaan' => 'PT Uji Tujuh Manual ' . $XSS, 'bentuk_badan_usaha' => 'PT', 'nib' => '', 'bidang_usaha' => 'Seragam / desain',
    'alamat' => 'Jl. Manual ' . $XSS, 'nama_penanggung_jawab' => 'PJ ' . $XSS, 'email_perusahaan' => 'salah', 'no_telepon' => '021-555',
], 'staf/manual');
$formManual = $ver->minta('staf/manual')['isi'];
cek('isian manual berisi payload di-escape saat form ditampilkan ulang', !str_contains($formManual, '<script>alert(7)') && str_contains($formManual, '&lt;script&gt;'));

echo "== 4. SQL injection dan input aneh ==\n";
$payload = ["' OR '1'='1", "1; DROP TABLE tbl_user;--", '" OR 1=1 --', "' UNION SELECT 1,2,3,4,5--", "\x00", str_repeat('A', 5000), '%', '_', '../../etc/passwd'];
$jmlUser = (int) $pdo->query('SELECT COUNT(*) FROM tbl_user')->fetchColumn();
$jmlPeng = (int) $pdo->query('SELECT COUNT(*) FROM tbl_pengajuan_vendor')->fetchColumn();
$aneh = 0;
$detailAneh = [];
$jalankan = static function (Klien $k, string $hal, string $qs) use (&$aneh, &$detailAneh): void {
    $r = $k->minta($hal, 'GET', [], $qs);
    $buruk = $r['kode'] >= 500 || preg_match('/SQLSTATE|Fatal error|Warning:|Notice:|Stack trace|PDOException/', $r['isi']);
    if ($buruk) {
        $aneh++;
        $detailAneh[] = "{$hal}{$qs}=>{$r['kode']}";
    }
};
foreach ($payload as $p) {
    $e = rawurlencode($p);
    foreach (['&q=', '&status=', '&sumber=', '&halaman='] as $param) {
        $jalankan($ver, 'staf/pengajuan', $param . $e);
    }
    $jalankan($ver, 'staf/arsip', '&q=' . $e);
    $jalankan($adm, 'admin/peran', '&q=' . $e);
    $jalankan($ver, 'staf/pengajuan/detail', '&id=' . $e);
    $jalankan($ver, 'dokumen/unduh', '&id=' . $e);
    $jalankan($vend, 'vendor/formulir', '&langkah=' . $e);
    $jalankan($adm, 'admin/konfigurasi', '&jenis=' . $e);
}
cek('parameter GET aneh tidak menimbulkan galat atau bocoran (' . (count($payload) * 10) . ' permintaan)', $aneh === 0, implode('; ', array_slice($detailAneh, 0, 5)));
$aneh = 0;
foreach ($payload as $p) {
    $r = $anon->kirim('masuk-staf/proses', ['userid' => $p, 'kata_sandi' => $p], 'masuk-staf');
    $r2 = $anon->kirim('masuk-vendor/proses', ['email' => $p, 'kata_sandi' => $p], 'masuk-vendor');
    $r3 = $anon->kirim('daftar-vendor/proses', ['nama_perusahaan' => $p, 'nama_kontak' => $p, 'email' => $p, 'kata_sandi' => $p, 'konfirmasi_sandi' => $p], 'daftar-vendor');
    foreach ([$r, $r2, $r3] as $x) {
        if ($x['kode'] >= 500 || preg_match('/SQLSTATE|Fatal error|Warning:/', $x['isi'])) {
            $aneh++;
        }
    }
}
cek('input aneh pada login dan registrasi aman', $aneh === 0);
cek('tabel tidak berubah akibat injection (tbl_user, pengajuan)',
    (int) $pdo->query('SELECT COUNT(*) FROM tbl_user')->fetchColumn() === $jmlUser
    && (int) $pdo->query('SELECT COUNT(*) FROM tbl_pengajuan_vendor')->fetchColumn() === $jmlPeng);
$r = $anon->minta('beranda', 'GET', [], '&hal[]=x&q[]=a');
cek('parameter berbentuk array tidak menimbulkan peringatan PHP', $r['kode'] === 200 && !str_contains($r['isi'], 'Warning'));
$r = $ver->kirim('staf/pengajuan/kembalikan', ['id_pengajuan' => ['1'], 'catatan' => ['a']], 'staf/dashboard');
cek('POST dengan nilai array tidak menimbulkan galat 500', $r['kode'] < 500);
$r = $anon->kirim('masuk-staf/proses', ['userid' => 'demo_admin', 'kata_sandi' => str_repeat('A', 300000)], 'masuk-staf');
cek('kata sandi sangat panjang ditangani (bukan 500)', $r['kode'] < 500);
$r = $vend->kirim('vendor/dokumen/unggah', ['id_syarat' => '1', 'berkas' => berkas($PDF, 'a.pdf')], 'vendor/formulir', '&langkah=4');
cek('unggah pada pengajuan terkirim tidak menimbulkan 500', $r['kode'] < 500);

echo "== 5. Header keamanan dan akses berkas langsung ==\n";
$r = $anon->minta('beranda');
foreach (['X-Frame-Options: DENY', 'X-Content-Type-Options: nosniff', 'Referrer-Policy: same-origin', "Content-Security-Policy: default-src 'self'"] as $h) {
    cek("header {$h}", stripos($r['header'], $h) !== false);
}
$login = new Klien($dasar);
$r = $login->minta('masuk-staf');
cek('cookie session HttpOnly dan SameSite', preg_match('/Set-Cookie: vmp_sesi=[^;]+;.*HttpOnly/i', $r['header']) === 1 && stripos($r['header'], 'SameSite=Lax') !== false);
$r = $vend->minta('dokumen/unduh', 'GET', [], '&id=' . $idDok);
cek('unduhan dokumen: nosniff, sandbox, no-store', stripos($r['header'], 'nosniff') !== false && stripos($r['header'], 'sandbox') !== false && stripos($r['header'], 'no-store') !== false);
$namaSimpan = (string) $pdo->query("SELECT nama_file_simpan FROM tbl_dokumen_vendor WHERE id_dokumen={$idDok}")->fetchColumn();
foreach (['/.env', '/app/Core/Env.php', '/config/rute.php', '/database/schema.sql', '/tests/Klien.php',
          "/storage/uploads/{$idP}/{$namaSimpan}", '/storage/logs/aplikasi.log', '/README.md'] as $jalur) {
    cek("akses langsung {$jalur} tidak mungkin (404)", mentah($dasar . $jalur)['kode'] === 404);
}
$t = $ver->csrf('staf/dashboard');
$ver->minta('keluar', 'POST', ['_csrf' => $t]);
cek('setelah logout sesi lama tidak dapat dipakai', $ver->minta('staf/dashboard')['kode'] === 302);
$ver = new Klien($dasar);
$ver->masukStaf('demo_ver1', SANDI);

echo "== 6. Permintaan bersamaan ==\n";
/** Kirim beberapa POST sekaligus (masing-masing dengan sesi sendiri). @return int[] kode HTTP */
function serempak(array $permintaan): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($permintaan as [$klien, $hal, $data, $token]) {
        $ch = curl_init($GLOBALS['dasar'] . '/index.php?hal=' . str_replace('%2F', '/', rawurlencode($hal)));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data + ['_csrf' => $token]),
            CURLOPT_COOKIEFILE => $klien->jar(), CURLOPT_COOKIEJAR => $klien->jar(), CURLOPT_TIMEOUT => 30,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }
    do {
        $s = curl_multi_exec($mh, $aktif);
        if ($aktif) {
            curl_multi_select($mh, 0.2);
        }
    } while ($aktif && $s === CURLM_OK);
    $kode = [];
    foreach ($handles as $ch) {
        $kode[] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);
    return $kode;
}
$riw = static fn (int $id, string $ke) => (int) Database::prototype()->query(
    "SELECT COUNT(*) FROM tbl_riwayat_status WHERE id_pengajuan={$id} AND status_tujuan='{$ke}'"
)->fetchColumn();

$a1 = new Klien($dasar); $a1->masukStaf('demo_ver1', SANDI);
$a2 = new Klien($dasar); $a2->masukStaf('demo_ver2', SANDI);
$a3 = new Klien($dasar); $a3->masukStaf('demo_ver1', SANDI);
$tok = fn (Klien $k) => $k->csrf('staf/dashboard');
serempak([
    [$a1, 'staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idP], $tok($a1)],
    [$a2, 'staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idP], $tok($a2)],
    [$a3, 'staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idP], $tok($a3)],
]);
cek('3 permintaan mulai-periksa bersamaan: hanya satu perpindahan tercatat', $riw($idP, 'sedang_diperiksa') === 1 && $stat($idP) === 'sedang_diperiksa');

[$vr, $idR] = ajukan($dasar, $pdo, 'race7@uji.test', 'PT Uji Tujuh Race', $PDF);
// pengajuan sudah terkirim oleh ajukan(); dorong sampai dokumen_terverifikasi lalu uji penjadwalan serempak
$ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idR], 'staf/dashboard');
foreach ($pdo->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$idR}")->fetchAll(PDO::FETCH_COLUMN) as $d) {
    $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idR, 'id_dokumen' => (string) $d, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
}
$ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $idR], 'staf/dashboard');
$p1 = new Klien($dasar); $p1->masukStaf('demo_plan', SANDI);
$p2 = new Klien($dasar); $p2->masukStaf('demo_plan', SANDI);
$jd = ['id_pengajuan' => (string) $idR, 'tanggal_verifikasi' => date('Y-m-d\TH:i', strtotime('+3 days 11:00')),
       'metode' => 'daring', 'lokasi' => '', 'id_verifikator' => '902', 'catatan' => ''];
serempak([
    [$p1, 'staf/jadwal/simpan', $jd, $p1->csrf('staf/dashboard')],
    [$p2, 'staf/jadwal/simpan', $jd, $p2->csrf('staf/dashboard')],
]);
$aktif = (int) $pdo->query("SELECT COUNT(*) FROM tbl_jadwal_verifikasi WHERE id_pengajuan={$idR} AND status_jadwal='aktif'")->fetchColumn();
cek('2 penjadwalan bersamaan: hanya satu jadwal aktif', $aktif === 1 && $stat($idR) === 'terjadwal', "aktif={$aktif}");

// regresi: variabel $nama pada View pernah bertabrakan dengan nama view sehingga nama petugas tidak tampil
$detailR = $ver->minta('staf/pengajuan/detail', 'GET', [], '&id=' . $idR)['isi'];
cek('nama petugas verifikasi tampil pada jadwal', str_contains($detailR, 'Petugas: Demo Verifikator 1'));
cek('nama pelaku tampil pada riwayat status', substr_count($detailR, 'Demo Verifikator 1') >= 2);

echo "== 7. Galat tak terduga tidak membocorkan rincian ==\n";
$log = BASE_PATH . '/storage/logs/aplikasi.log';
$ukuranLog = is_file($log) ? filesize($log) : 0;
$port = 8097;
$php = PHP_BINARY;
$env = array_merge($_SERVER, ['VMP_DB_NAMA' => 'database_tidak_ada_zzz', 'VMP_APP_DEBUG' => '0']);
$env = array_filter($env, 'is_string');
$proc = proc_open([$php, '-S', "127.0.0.1:{$port}", '-t', BASE_PATH . '/public'], [1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $pipes, BASE_PATH, $env);
usleep(1500000);
$galat = new Klien("http://127.0.0.1:{$port}");
$r = $galat->kirim('masuk-vendor/proses', ['email' => 'x@y.test', 'kata_sandi' => 'abcdefgh1234'], 'masuk-vendor');
cek('galat database menghasilkan halaman 500 umum', $r['kode'] === 500, (string) $r['kode']);
cek('halaman galat tidak memuat rincian teknis',
    !preg_match('/PDOException|SQLSTATE|Stack trace|database_tidak_ada_zzz|C:\\\\|laragon|Database\.php/i', $r['isi']) && str_contains($r['isi'], 'Terjadi kesalahan'));
preg_match('#<strong>([A-F0-9]{8})</strong>#', $r['isi'], $m);
$kode = $m[1] ?? '';
clearstatcache();
$isiLog = is_file($log) ? (string) file_get_contents($log, false, null, $ukuranLog) : '';
cek('kode rujukan tercatat di log beserta rincian', $kode !== '' && str_contains($isiLog, $kode) && str_contains($isiLog, 'PDOException'));
cek('log tidak memuat kata sandi database/akun', !str_contains($isiLog, 'abcdefgh1234'));
if (is_resource($proc)) {
    proc_terminate($proc);
    proc_close($proc);
}

bersihkan($pdo);
$sisa = (int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchColumn()
      + (int) $pdo->query("SELECT COUNT(*) FROM tbl_vendor WHERE nama_perusahaan LIKE 'PT Uji Tujuh%'")->fetchColumn();
cek('data uji dibersihkan', $sisa === 0);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
