<?php
declare(strict_types=1);

/**
 * Uji pencarian rekanan per kategori dan level klasifikasi.
 *   php -S 127.0.0.1:8099 -t public
 *   php tests/uji_rekanan.php http://127.0.0.1:8099
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
                foreach (glob(BASE_PATH . '/storage/uploads/' . (int) $id . '/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir(BASE_PATH . '/storage/uploads/' . (int) $id);
            }
        }
        $pdo->exec("DELETE FROM tbl_vendor WHERE id_vendor IN ({$iv})");
    }
    $akun = $pdo->query("SELECT id_akun_vendor FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchAll(PDO::FETCH_COLUMN);
    if ($akun) {
        $ia = implode(',', array_map('intval', $akun));
        $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE tipe_pelaku='vendor' AND id_pelaku IN ({$ia})");
        $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi IN ('registrasi_vendor','profil_vendor_dibuat')
                      AND (JSON_EXTRACT(detail,'$.id_akun_vendor') IN ({$ia}) OR id_objek IN ({$ia}))");
        $pdo->exec("DELETE FROM tbl_akun_vendor WHERE id_akun_vendor IN ({$ia})");
    }
}

function berkas(string $isi, string $nama): CURLFile
{
    $dir = sys_get_temp_dir() . '/vmp_uji_rek';
    @mkdir($dir);
    file_put_contents($dir . '/' . $nama, $isi);
    return new CURLFile($dir . '/' . $nama, 'application/octet-stream', $nama);
}

$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

/**
 * Alur penuh sampai klasifikasi ditetapkan (sesuai rekomendasi).
 * @return int id_pengajuan
 */
function sampaiKlasifikasi(string $dasar, PDO $pdo, array $petugas, string $email, string $nama, string $nib,
                           string $bidang, string $modeJawab, array $skor, string $pdf, bool $tetapkan = true): int
{
    [$ver, $plan, $auth] = $petugas;
    $v = new Klien($dasar);
    $v->kirim('daftar-vendor/proses', [
        'nama_perusahaan' => $nama, 'nama_kontak' => 'Kontak', 'email' => $email,
        'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
    ], 'daftar-vendor');
    $v->masukVendor($email, SANDI_VENDOR);
    $v->kirim('vendor/formulir/simpan', [
        'langkah' => '1', 'nama_perusahaan' => $nama, 'bentuk_badan_usaha' => 'PT', 'nib' => $nib,
        'bidang_usaha' => $bidang, 'alamat' => 'Jl. Rekanan Uji No. 8, Depok',
        'nama_penanggung_jawab' => 'PJ Rekanan Uji', 'email_perusahaan' => 'k-' . $email, 'no_telepon' => '021-' . random_int(100000, 999999),
    ], 'vendor/formulir');
    jawabSemuaPertanyaan($v, $pdo, $modeJawab);
    $syarat = $pdo->query('SELECT id_syarat, kode_syarat FROM tbl_syarat_dokumen WHERE aktif=1 AND wajib=1')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($syarat as $idS => $kode) {
        $v->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $idS, 'berkas' => berkas($pdf, "{$kode}.pdf")], 'vendor/formulir', '&langkah=4');
    }
    $v->kirim('vendor/kirim', [], 'vendor/dashboard');
    $id = (int) $pdo->query(
        "SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor vv ON vv.id_vendor=p.id_vendor
          JOIN tbl_akun_vendor a ON a.id_akun_vendor=vv.id_akun_vendor WHERE a.email=" . $pdo->quote($email)
    )->fetchColumn();

    $ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    foreach ($pdo->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$id}")->fetchAll(PDO::FETCH_COLUMN) as $d) {
        $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $id, 'id_dokumen' => (string) $d, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
    }
    $ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    $plan->kirim('staf/jadwal/simpan', [
        'id_pengajuan' => (string) $id, 'tanggal_verifikasi' => date('Y-m-d\TH:i', strtotime('+2 days 09:00')),
        'metode' => 'daring', 'lokasi' => '', 'id_verifikator' => '902', 'catatan' => '',
    ], 'staf/dashboard');
    $ver->kirim('staf/hasil/simpan', [
        'id_pengajuan' => (string) $id, 'skor_contoh_1' => (string) $skor[0], 'skor_contoh_2' => (string) $skor[1],
        'skor_contoh_3' => (string) $skor[2], 'kesimpulan' => 'memenuhi', 'temuan' => 'Pemeriksaan uji rekanan.',
    ], 'staf/dashboard');
    if ($tetapkan) {
        $label = $pdo->query("SELECT label_klasifikasi FROM tbl_rekomendasi_klasifikasi WHERE id_pengajuan={$id}")->fetchColumn();
        $auth->kirim('staf/penetapan/simpan', ['id_pengajuan' => (string) $id, 'label_klasifikasi' => (string) $label, 'alasan' => ''], 'staf/dashboard');
    }
    return $id;
}

bersihkan($pdo);
$ver = new Klien($dasar);  $ver->masukStaf('demo_ver1', SANDI);
$plan = new Klien($dasar); $plan->masukStaf('demo_plan', SANDI);
$auth = new Klien($dasar); $auth->masukStaf('demo_auth', SANDI);
$adm = new Klien($dasar);  $adm->masukStaf('demo_admin', SANDI);
$vend = new Klien($dasar);
$petugas = [$ver, $plan, $auth];

echo "== 1. Persiapan vendor berklasifikasi ==\n";
$SERAGAM = 'Seragam / desain';
$PELATIHAN = 'Pelatihan dan sertifikasi';
// A: seragam, skor tinggi -> Level A ; B: seragam, skor rendah -> Level C ; C: pelatihan, tinggi -> Level A
// D: pelatihan, belum ditetapkan -> tidak tampil ; E: nama berawalan "=" untuk uji ekspor
$idA = sampaiKlasifikasi($dasar, $pdo, $petugas, 'rek-a@uji.test', 'PT Uji Rekanan Alfa', 'UJIR-0001', $SERAGAM, 'tertinggi', [95, 95, 95], $PDF);
$idB = sampaiKlasifikasi($dasar, $pdo, $petugas, 'rek-b@uji.test', 'PT Uji Rekanan Beta', 'UJIR-0002', $SERAGAM, 'terendah', [20, 20, 20], $PDF);
$idC = sampaiKlasifikasi($dasar, $pdo, $petugas, 'rek-c@uji.test', 'PT Uji Rekanan Gamma', 'UJIR-0003', $PELATIHAN, 'tertinggi', [95, 95, 95], $PDF);
$idD = sampaiKlasifikasi($dasar, $pdo, $petugas, 'rek-d@uji.test', 'PT Uji Rekanan Delta', 'UJIR-0004', $PELATIHAN, 'tertinggi', [95, 95, 95], $PDF, false);
$idE = sampaiKlasifikasi($dasar, $pdo, $petugas, 'rek-e@uji.test', '=HYPERLINK("http://x") Uji Rekanan Epsilon', 'UJIR-0005', 'Lainnya', 'tertinggi', [95, 95, 95], $PDF);
$label = static fn (int $id) => $pdo->query("SELECT label_klasifikasi FROM tbl_penetapan_klasifikasi WHERE id_pengajuan={$id} ORDER BY id_penetapan DESC LIMIT 1")->fetchColumn();
cek('A berlevel A, B berlevel C, C berlevel A', $label($idA) === 'Level A (simulasi)' && $label($idB) === 'Level C (simulasi)' && $label($idC) === 'Level A (simulasi)',
    $label($idA) . ' / ' . $label($idB) . ' / ' . $label($idC));
cek('D belum ditetapkan', $label($idD) === false);

echo "== 2. Akses ==\n";
$r = fn (Klien $k, string $qs = '') => $k->minta('staf/rekanan', 'GET', [], $qs);
cek('tanpa login diarahkan ke login', $r($vend)['kode'] === 302);
$vl = new Klien($dasar);
$vl->kirim('daftar-vendor/proses', ['nama_perusahaan' => 'PT Uji Rekanan Vendor', 'nama_kontak' => 'Kontak Uji', 'email' => 'rek-v@uji.test', 'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR], 'daftar-vendor');
$vl->masukVendor('rek-v@uji.test', SANDI_VENDOR);
cek('vendor tidak boleh melihat pencarian rekanan (403)', $r($vl)['kode'] === 403);
cek('vendor tidak boleh mengekspor (403)', $vl->minta('staf/rekanan/ekspor')['kode'] === 403);
foreach (['perencanaan' => $plan, 'verifikator' => $ver, 'berwenang' => $auth, 'admin' => $adm] as $nama => $k) {
    cek("{$nama} dapat membuka pencarian rekanan", $r($k)['kode'] === 200);
}

echo "== 3. Pencarian per kategori dan level ==\n";
$semua = $r($ver)['isi'];
cek('vendor berklasifikasi tampil', str_contains($semua, 'PT Uji Rekanan Alfa') && str_contains($semua, 'PT Uji Rekanan Beta') && str_contains($semua, 'PT Uji Rekanan Gamma'));
cek('vendor yang belum ditetapkan tidak tampil', !str_contains($semua, 'PT Uji Rekanan Delta'));
$s = $r($ver, '&bidang=' . rawurlencode($SERAGAM))['isi'];
cek('filter kategori seragam: Alfa dan Beta, tanpa Gamma', str_contains($s, 'Rekanan Alfa') && str_contains($s, 'Rekanan Beta') && !str_contains($s, 'Rekanan Gamma'));
$s = $r($ver, '&bidang=' . rawurlencode($SERAGAM) . '&level=' . rawurlencode('Level A (simulasi)'))['isi'];
cek('kategori seragam + Level A: hanya Alfa', str_contains($s, 'Rekanan Alfa') && !str_contains($s, 'Rekanan Beta') && !str_contains($s, 'Rekanan Gamma'));
$s = $r($ver, '&bidang=' . rawurlencode($SERAGAM) . '&level=' . rawurlencode('Level C (simulasi)'))['isi'];
cek('kategori seragam + Level C: hanya Beta', str_contains($s, 'Rekanan Beta') && !str_contains($s, 'Rekanan Alfa'));
$s = $r($ver, '&level=' . rawurlencode('Level A (simulasi)'))['isi'];
cek('Level A di semua kategori: Alfa dan Gamma', str_contains($s, 'Rekanan Alfa') && str_contains($s, 'Rekanan Gamma') && !str_contains($s, 'Rekanan Beta'));
$s = $r($ver, '&q=Gamma')['isi'];
cek('pencarian nama: hanya Gamma', str_contains($s, 'Rekanan Gamma') && !str_contains($s, 'Rekanan Alfa'));
cek('ringkasan matriks memuat kategori dan jumlah', str_contains($semua, 'Ringkasan kategori dan level') && str_contains($semua, 'SIMULASI'));
$s = $r($ver, '&bidang=Kategori+Karangan&level=Level+Ngawur')['isi'];
cek('nilai filter tidak dikenal diabaikan (tanpa galat)', str_contains($s, 'PT Uji Rekanan Alfa'));
$s = $r($ver, '&q=' . rawurlencode("' OR 1=1 --"))['isi'];
cek('input mirip SQL injection aman', !str_contains($s, 'SQLSTATE') && !str_contains($s, 'Fatal'));
cek('karakter % tidak menjadi wildcard', !str_contains($r($ver, '&q=%25')['isi'], 'PT Uji Rekanan Alfa'));
$ifE = $r($ver, '&bidang=Lainnya')['isi'];
cek('nama berisi rumus ditampilkan sebagai teks yang di-escape', str_contains($ifE, '=HYPERLINK(&quot;http://x&quot;)'));

echo "== 4. Koreksi klasifikasi memengaruhi level terkini ==\n";
$auth->kirim('staf/penetapan/simpan', ['id_pengajuan' => (string) $idA, 'label_klasifikasi' => 'Level B (simulasi)', 'alasan' => 'Koreksi uji untuk memeriksa level terkini.'], 'staf/dashboard');
$s = $r($ver, '&bidang=' . rawurlencode($SERAGAM) . '&level=' . rawurlencode('Level B (simulasi)'))['isi'];
cek('setelah koreksi, Alfa muncul pada Level B', str_contains($s, 'Rekanan Alfa'));
$s = $r($ver, '&bidang=' . rawurlencode($SERAGAM) . '&level=' . rawurlencode('Level A (simulasi)'))['isi'];
cek('setelah koreksi, Alfa tidak lagi pada Level A', !str_contains($s, 'Rekanan Alfa'));
$n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_vendor v JOIN tbl_pengajuan_vendor p ON p.id_vendor=v.id_vendor WHERE v.nama_perusahaan='PT Uji Rekanan Alfa'")->fetchColumn();
cek('satu vendor tampil satu kali (tidak ganda karena riwayat penetapan)', substr_count($r($ver, '&q=Alfa')['isi'], 'PT Uji Rekanan Alfa') === 1 && $n === 1);

echo "== 5. Ekspor CSV ==\n";
$e = $ver->minta('staf/rekanan/ekspor', 'GET', [], '&bidang=' . rawurlencode($SERAGAM));
cek('ekspor CSV (200, text/csv, attachment)', $e['kode'] === 200 && stripos($e['header'], 'text/csv') !== false && stripos($e['header'], 'attachment') !== false);
cek('CSV memuat judul kolom dan data terfilter', str_contains($e['isi'], 'Nama perusahaan') && str_contains($e['isi'], 'PT Uji Rekanan Alfa') && !str_contains($e['isi'], 'Rekanan Gamma'));
$eAll = $ver->minta('staf/rekanan/ekspor', 'GET', [], '&bidang=Lainnya');
cek("sel berawalan '=' diberi tanda kutip (anti formula injection)", str_contains($eAll['isi'], "'=HYPERLINK"));
cek('ekspor tercatat di audit log', (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE aksi='rekanan_diekspor'")->fetchColumn() > 0);

echo "== 6. Menu ==\n";
$dash = $plan->minta('staf/dashboard')['isi'];
cek('menu "Vendor Management" (halaman gabungan) tampil untuk perencanaan', str_contains($dash, 'staf/vendor'));

bersihkan($pdo);
$sisa = (int) $pdo->query("SELECT COUNT(*) FROM tbl_vendor WHERE nama_perusahaan LIKE 'PT Uji Rekanan%' OR nib LIKE 'UJIR-%'")->fetchColumn();
cek('data uji dibersihkan', $sisa === 0);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
