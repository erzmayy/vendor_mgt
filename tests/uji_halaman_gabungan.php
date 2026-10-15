<?php
declare(strict_types=1);

/**
 * Uji halaman gabungan: Vendor Management (satu tabel + tab tahap + filter) dan Pengaturan Vendor.
 *   php -S 127.0.0.1:8099 -t public
 *   php tests/uji_halaman_gabungan.php http://127.0.0.1:8099
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
    $pdo->exec("UPDATE tbl_pengaturan_penilaian SET nilai='40' WHERE kunci IN ('bobot_berkas','bobot_pertanyaan')");
    $pdo->exec("UPDATE tbl_pengaturan_penilaian SET nilai='20' WHERE kunci = 'bobot_verifikasi'");
}

function berkas(string $isi, string $nama): CURLFile
{
    $dir = sys_get_temp_dir() . '/vmp_uji_gab';
    @mkdir($dir);
    file_put_contents($dir . '/' . $nama, $isi);
    return new CURLFile($dir . '/' . $nama, 'application/octet-stream', $nama);
}

$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

/**
 * Membawa vendor baru sampai tahap tertentu.
 * @param string $sampai diajukan | terjadwal | klasifikasi
 * @return int id_pengajuan
 */
function bawaKe(string $dasar, PDO $pdo, array $petugas, string $sampai, string $email, string $nama, string $nib,
                string $bidang, string $modeJawab, array $skor, string $pdf): int
{
    [$ver, $plan, $auth] = $petugas;
    $v = new Klien($dasar);
    $v->kirim('daftar-vendor/proses', [
        'nama_perusahaan' => $nama, 'nama_kontak' => 'Kontak Uji', 'email' => $email,
        'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
    ], 'daftar-vendor');
    $v->masukVendor($email, SANDI_VENDOR);
    $v->kirim('vendor/formulir/simpan', [
        'langkah' => '1', 'nama_perusahaan' => $nama, 'bentuk_badan_usaha' => 'PT', 'nib' => $nib,
        'bidang_usaha' => $bidang, 'alamat' => 'Jl. Gabungan Uji No. 3, Bekasi',
        'nama_penanggung_jawab' => 'PJ Gabungan Uji', 'email_perusahaan' => 'k-' . $email, 'no_telepon' => '021-' . random_int(100000, 999999),
    ], 'vendor/formulir');
    jawabSemuaPertanyaan($v, $pdo, $modeJawab);
    foreach ($pdo->query('SELECT id_syarat, kode_syarat FROM tbl_syarat_dokumen WHERE aktif=1 AND wajib=1')->fetchAll(PDO::FETCH_KEY_PAIR) as $idS => $kode) {
        $v->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $idS, 'berkas' => berkas($pdf, "{$kode}.pdf")], 'vendor/formulir', '&langkah=4');
    }
    $v->kirim('vendor/kirim', [], 'vendor/dashboard');
    $id = (int) $pdo->query(
        "SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor vv ON vv.id_vendor=p.id_vendor
          JOIN tbl_akun_vendor a ON a.id_akun_vendor=vv.id_akun_vendor WHERE a.email=" . $pdo->quote($email)
    )->fetchColumn();
    if ($sampai === 'diajukan') {
        return $id;
    }

    $ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    foreach ($pdo->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$id}")->fetchAll(PDO::FETCH_COLUMN) as $d) {
        $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $id, 'id_dokumen' => (string) $d, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
    }
    $ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    $plan->kirim('staf/jadwal/simpan', [
        'id_pengajuan' => (string) $id, 'tanggal_verifikasi' => date('Y-m-d\TH:i', strtotime('+4 days 10:30')),
        'metode' => 'daring', 'lokasi' => '', 'id_verifikator' => '902', 'catatan' => '',
    ], 'staf/dashboard');
    if ($sampai === 'terjadwal') {
        return $id;
    }
    $ver->kirim('staf/hasil/simpan', [
        'id_pengajuan' => (string) $id, 'skor_contoh_1' => (string) $skor[0], 'skor_contoh_2' => (string) $skor[1],
        'skor_contoh_3' => (string) $skor[2], 'kesimpulan' => 'memenuhi', 'temuan' => 'Pemeriksaan uji halaman gabungan.',
    ], 'staf/dashboard');
    $label = $pdo->query("SELECT label_klasifikasi FROM tbl_rekomendasi_klasifikasi WHERE id_pengajuan={$id}")->fetchColumn();
    $auth->kirim('staf/penetapan/simpan', ['id_pengajuan' => (string) $id, 'label_klasifikasi' => (string) $label, 'alasan' => ''], 'staf/dashboard');
    return $id;
}

bersihkan($pdo);
$ver = new Klien($dasar);  $ver->masukStaf('demo_ver1', SANDI);
$plan = new Klien($dasar); $plan->masukStaf('demo_plan', SANDI);
$auth = new Klien($dasar); $auth->masukStaf('demo_auth', SANDI);
$adm = new Klien($dasar);  $adm->masukStaf('demo_admin', SANDI);
$anon = new Klien($dasar);
$petugas = [$ver, $plan, $auth];
$SERAGAM = 'Seragam / desain';
$PELATIHAN = 'Pelatihan dan sertifikasi';
$XSS = '<img src=x onerror=alert(9)>';

echo "== 1. Persiapan ==\n";
$idA = bawaKe($dasar, $pdo, $petugas, 'klasifikasi', 'gab-a@uji.test', 'PT Uji Gabung Alfa', 'UJIG-0001', $SERAGAM, 'tertinggi', [95, 95, 95], $PDF);
$idB = bawaKe($dasar, $pdo, $petugas, 'klasifikasi', 'gab-b@uji.test', 'PT Uji Gabung Beta', 'UJIG-0002', $PELATIHAN, 'terendah', [20, 20, 20], $PDF);
$idC = bawaKe($dasar, $pdo, $petugas, 'diajukan', 'gab-c@uji.test', 'PT Uji Gabung Gamma', 'UJIG-0003', $SERAGAM, 'tertinggi', [0, 0, 0], $PDF);
$idD = bawaKe($dasar, $pdo, $petugas, 'terjadwal', 'gab-d@uji.test', 'PT Uji Gabung Delta ' . $XSS, 'UJIG-0004', $PELATIHAN, 'tertinggi', [0, 0, 0], $PDF);
$stat = static fn (int $id) => $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$id}")->fetchColumn();
cek('tahap pengajuan sesuai rencana', $stat($idA) === 'terklasifikasi' && $stat($idB) === 'terklasifikasi' && $stat($idC) === 'diajukan' && $stat($idD) === 'terjadwal',
    $stat($idA) . ',' . $stat($idB) . ',' . $stat($idC) . ',' . $stat($idD));

echo "== 2. Akses halaman Vendor Management ==\n";
// tanpa parameter segmen halaman membuka tab Dashboard; pengujian daftar memakai tab Semua pengajuan
$g = static fn (Klien $k, string $qs = '') => $k->minta('staf/vendor', 'GET', [], str_contains($qs, 'segmen=') ? $qs : '&segmen=semua' . $qs);
cek('tanpa login diarahkan ke login', $g($anon)['kode'] === 302);
$vl = new Klien($dasar);
$vl->kirim('daftar-vendor/proses', ['nama_perusahaan' => 'PT Uji Gabung Vendor', 'nama_kontak' => 'Kontak Uji', 'email' => 'gab-v@uji.test', 'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR], 'daftar-vendor');
$vl->masukVendor('gab-v@uji.test', SANDI_VENDOR);
cek('vendor tidak boleh membuka (403)', $g($vl)['kode'] === 403);
foreach (['perencanaan' => $plan, 'verifikator' => $ver, 'berwenang' => $auth, 'admin' => $adm] as $n => $k) {
    cek("{$n} dapat membuka", $g($k)['kode'] === 200);
}
$dash = $ver->minta('staf/dashboard')['isi'];
cek('sidebar hanya memuat butir gabungan (tanpa submodul terpisah)',
    str_contains($dash, 'staf/vendor') && !str_contains($dash, 'hal=staf/jadwal') && !str_contains($dash, 'hal=staf/arsip') && !str_contains($dash, 'hal=staf/rekanan'));

echo "== 2b. Dashboard sebagai bagian Vendor Management ==\n";
$awal = $ver->minta('staf/vendor');
cek('staf/vendor tanpa parameter membuka tab Dashboard (kartu angka)', $awal['kode'] === 200 && str_contains($awal['isi'], 'class="kpi-grid"'));
cek('sidebar tidak lagi memiliki butir Dashboard terpisah', !str_contains($awal['isi'], '<span class="menu-label">Dashboard</span>'));
cek('tab Dashboard ada pada daftar tab dan berstatus aktif',
    (bool) preg_match('/class="aktif"[^>]*aria-current="page"[^>]*>\s*<svg[^>]*><use href="#i-home"\/><\/svg>\s*Dashboard/s', $awal['isi']));
$lama = $ver->minta('staf/dashboard');
cek('alamat lama staf/dashboard menampilkan halaman yang sama (tab Dashboard)',
    $lama['kode'] === 200 && str_contains($lama['isi'], 'class="kpi-grid"') && str_contains($lama['isi'], 'Pengajuan terbaru'));
cek('tab Dashboard tidak memuat tabel filter', !str_contains($awal['isi'], 'name="bidang"'));
cek('tab daftar memuat filter dan tabel', str_contains($g($ver)['isi'], 'name="bidang"') && str_contains($g($ver)['isi'], 'class="tabel"'));
cek('tautan kartu angka menuju tab daftar dengan filter status', str_contains($awal['isi'], 'segmen=semua&amp;status='));

echo "== 3. Tab tahap dan jumlahnya ==\n";
$semua = $g($ver)['isi'];
foreach (['Alfa', 'Beta', 'Gamma', 'Delta'] as $n) {
    cek("tab Semua memuat Uji Gabung {$n}", str_contains($semua, "PT Uji Gabung {$n}"));
}
$hit = (array) $pdo->query("SELECT status_pengajuan, COUNT(*) FROM tbl_pengajuan_vendor WHERE status_pengajuan <> 'draf' GROUP BY status_pengajuan")->fetchAll(PDO::FETCH_KEY_PAIR);
$jml = static fn (array $s) => array_sum(array_map(static fn ($x) => (int) ($hit[$x] ?? 0), $s));
$harap = [
    'Semua pengajuan' => array_sum($hit),
    'Perlu diperiksa' => $jml(['diajukan', 'sedang_diperiksa', 'perlu_perbaikan']),
    'Verifikasi' => $jml(['dokumen_terverifikasi', 'terjadwal']),
    'Klasifikasi dan TDR' => $jml(['terverifikasi', 'ada_rekomendasi', 'terklasifikasi', 'tdr_tercatat']),
    'Selesai dan arsip' => $jml(['selesai', 'diarsipkan']),
];
foreach ($harap as $label => $n) {
    $ok = (bool) preg_match('/' . preg_quote($label, '/') . '\s*<span class="hitung">' . $n . '<\/span>/', $semua);
    cek("jumlah pada tab \"{$label}\" = {$n} (sesuai database)", $ok);
}
$s = $g($ver, '&segmen=periksa')['isi'];
cek('tab Perlu diperiksa memuat Gamma, bukan Alfa', str_contains($s, 'Uji Gabung Gamma') && !str_contains($s, 'Uji Gabung Alfa'));
$s = $g($ver, '&segmen=jadwal')['isi'];
cek('tab Verifikasi memuat Delta beserta tanggal jadwal', str_contains($s, 'Uji Gabung Delta') && str_contains($s, date('Y', strtotime('+4 days'))) && !str_contains($s, 'Uji Gabung Gamma'));
$s = $g($ver, '&segmen=penetapan')['isi'];
cek('tab Klasifikasi dan TDR memuat Alfa dan Beta', str_contains($s, 'Uji Gabung Alfa') && str_contains($s, 'Uji Gabung Beta') && !str_contains($s, 'Uji Gabung Delta'));
cek('nilai segmen tidak dikenal diabaikan', $g($ver, '&segmen=ngawur')['kode'] === 200);

echo "== 4. Filter kategori, level, sumber, pencarian ==\n";
$s = $g($ver, '&bidang=' . rawurlencode($SERAGAM))['isi'];
cek('filter kategori seragam: Alfa dan Gamma', str_contains($s, 'Uji Gabung Alfa') && str_contains($s, 'Uji Gabung Gamma') && !str_contains($s, 'Uji Gabung Beta'));
$s = $g($ver, '&level=' . rawurlencode('Level A (simulasi)'))['isi'];
cek('filter Level A: Alfa saja', str_contains($s, 'Uji Gabung Alfa') && !str_contains($s, 'Uji Gabung Beta') && !str_contains($s, 'Uji Gabung Gamma'));
cek('kolom level menampilkan label level', str_contains($s, 'Level A (simulasi)'));
$s = $g($ver, '&level=' . rawurlencode('Level C (simulasi)') . '&bidang=' . rawurlencode($PELATIHAN))['isi'];
cek('kategori pelatihan + Level C: Beta saja', str_contains($s, 'Uji Gabung Beta') && !str_contains($s, 'Uji Gabung Alfa'));
$s = $g($ver, '&sumber=proses_manual')['isi'];
cek('filter sumber proses manual tidak memuat vendor uji portal', !str_contains($s, 'Uji Gabung'));
$s = $g($ver, '&q=Gamma')['isi'];
cek('pencarian nama: Gamma saja', str_contains($s, 'Uji Gabung Gamma') && !str_contains($s, 'Uji Gabung Alfa'));
$s = $g($ver, '&q=UJIG-0002')['isi'];
cek('pencarian NIB: Beta saja', str_contains($s, 'Uji Gabung Beta') && !str_contains($s, 'Uji Gabung Alfa'));
$s = $g($ver, '&status=diajukan')['isi'];
cek('filter status tunggal diajukan: Gamma', str_contains($s, 'Uji Gabung Gamma') && !str_contains($s, 'Uji Gabung Alfa'));
cek('gabungan filter tanpa hasil menampilkan keadaan kosong', str_contains($g($ver, '&segmen=selesai&q=Gamma')['isi'], 'Tidak ada pengajuan'));

echo "== 5. Ketahanan input ==\n";
cek('nama berisi HTML di-escape', !str_contains($semua, '<img src=x onerror=alert(9)>') && str_contains($semua, '&lt;img src=x'));
foreach (["' OR 1=1 --", '%', '_', "\x00", str_repeat('A', 4000)] as $p) {
    $r = $g($ver, '&q=' . rawurlencode($p));
    cek('input aneh pada pencarian aman', $r['kode'] === 200 && !str_contains($r['isi'], 'SQLSTATE') && !str_contains($r['isi'], 'Fatal'));
}
cek('halaman di luar jangkauan aman', $g($ver, '&halaman=99999')['kode'] === 200 && $g($ver, '&halaman=-3')['kode'] === 200);
$r = $g($ver, '&q[]=a&segmen[]=b&bidang[]=c');
cek('parameter array tidak menimbulkan galat', $r['kode'] === 200 && !str_contains($r['isi'], 'Warning'));
cek('tautan ekspor mengikuti filter', str_contains($g($ver, '&bidang=' . rawurlencode($SERAGAM))['isi'], 'rekanan/ekspor&amp;bidang=Seragam'));
cek('ringkasan matriks tersedia di halaman yang sama', str_contains($semua, 'Ringkasan rekanan per kategori dan level'));
cek('tombol Catat Pengajuan Manual hanya untuk yang berhak', str_contains($ver->minta('staf/vendor')['isi'], 'staf/manual') && !str_contains($plan->minta('staf/vendor')['isi'], 'staf/manual'));

echo "== 6. Pengaturan Vendor (satu halaman) ==\n";
$p = static fn (Klien $k, string $qs = '') => $k->minta('admin/pengaturan', 'GET', [], $qs);
cek('tanpa login diarahkan ke login', $p($anon)['kode'] === 302);
foreach (['vendor' => $vl, 'perencanaan' => $plan, 'verifikator' => $ver, 'berwenang' => $auth] as $n => $k) {
    cek("{$n} tidak boleh membuka Pengaturan (403)", $p($k)['kode'] === 403);
}
$ph = $p($adm);
cek('admin membuka Pengaturan', $ph['kode'] === 200);
foreach (['peran', 'pertanyaan', 'bobot', 'aturan', 'syarat', 'field'] as $b) {
    cek("bagian {$b} ada pada satu halaman", str_contains($ph['isi'], 'id="bagian-' . $b . '"'));
}
cek('isi bagian ikut tersaji (peran, pertanyaan, bobot, aturan)',
    str_contains($ph['isi'], 'demo_ver1') && str_contains($ph['isi'], 'Berapa lama perusahaan Anda') && str_contains($ph['isi'], 'bobot_berkas') && str_contains($ph['isi'], 'Level A (simulasi)'));
cek('bagian dari parameter terbuka (open)', (bool) preg_match('/id="bagian-bobot"\s+open/', $p($adm, '&bagian=bobot')['isi']));
cek('bagian tidak dikenal kembali ke peran', (bool) preg_match('/id="bagian-peran"\s+open/', $p($adm, '&bagian=ngawur')['isi']));
$r = $adm->kirim('admin/konfigurasi/simpan', ['jenis' => 'bobot', 'bobot_berkas' => '50', 'bobot_pertanyaan' => '30', 'bobot_verifikasi' => '20'], 'admin/pengaturan');
cek('simpan konfigurasi kembali ke halaman Pengaturan pada bagian yang sama',
    $r['kode'] === 302 && str_contains($r['lokasi'], 'admin/pengaturan') && str_contains($r['lokasi'], 'bagian=bobot'), $r['lokasi']);
cek('perubahan tersimpan', (string) $pdo->query("SELECT nilai FROM tbl_pengaturan_penilaian WHERE kunci='bobot_berkas'")->fetchColumn() === '50');
$r = $adm->kirim('admin/peran/simpan', ['id_user' => '906', 'peran' => 'ngawur', 'aktif' => '1'], 'admin/pengaturan');
cek('simpan peran kembali ke bagian peran', str_contains($r['lokasi'], 'admin/pengaturan') && str_contains($r['lokasi'], 'bagian=peran'), $r['lokasi']);
cek('pencarian pegawai pada halaman gabungan', str_contains($p($adm, '&bagian=peran&q=demo_plan')['isi'], 'Demo Perencanaan'));
cek('halaman lama peran dan konfigurasi tetap berfungsi',
    $adm->minta('admin/peran')['kode'] === 200 && $adm->minta('admin/konfigurasi', 'GET', [], '&jenis=aturan')['kode'] === 200);
cek('verifikator tetap tidak dapat menyimpan lewat endpoint (403)',
    $ver->kirim('admin/konfigurasi/simpan', ['jenis' => 'bobot', 'bobot_berkas' => '0', 'bobot_pertanyaan' => '100', 'bobot_verifikasi' => '0'], 'staf/dashboard')['kode'] === 403);

bersihkan($pdo);
$sisa = (int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchColumn();
cek('data uji dibersihkan', $sisa === 0);
$b = $pdo->query("SELECT kunci, nilai FROM tbl_pengaturan_penilaian WHERE kunci LIKE 'bobot\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
cek('bobot dikembalikan ke 40/40/20', $b['bobot_berkas'] === '40' && $b['bobot_pertanyaan'] === '40' && $b['bobot_verifikasi'] === '20');

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
