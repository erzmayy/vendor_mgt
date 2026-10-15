<?php
declare(strict_types=1);

/**
 * Membuat DATA CONTOH untuk demonstrasi/presentasi (vendor fiktif di berbagai tahap).
 * Memakai alur HTTP yang sama dengan pengguna, jadi seluruh aturan berlaku.
 *
 *   php -S 127.0.0.1:8099 -t public        (di terminal lain)
 *   php tests/buat_data_contoh.php http://127.0.0.1:8099
 *
 * Aman dijalankan ulang: bila data contoh sudah ada, tidak membuat ulang.
 * Akun vendor contoh: <nama>@contoh.test, kata sandi sama dengan akun demo (README).
 * Hapus data contoh: php tests/buat_data_contoh.php <url> hapus
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Klien.php';
require __DIR__ . '/Bantu.php';

use App\Core\Database;

$dasar = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$hapus = ($argv[2] ?? '') === 'hapus';
const SANDI = 'DemoLokal#2026';

$pdo = Database::prototype();
$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

function berkas(string $isi, string $nama): CURLFile
{
    $dir = sys_get_temp_dir() . '/vmp_contoh';
    @mkdir($dir);
    file_put_contents($dir . '/' . $nama, $isi);
    return new CURLFile($dir . '/' . $nama, 'application/octet-stream', $nama);
}

function hapusContoh(PDO $pdo): void
{
    $vendor = $pdo->query(
        "SELECT v.id_vendor FROM tbl_vendor v LEFT JOIN tbl_akun_vendor a ON a.id_akun_vendor=v.id_akun_vendor
          WHERE a.email LIKE '%@contoh.test' AND a.email <> 'vendor.demo@contoh.test' OR v.nib LIKE 'CTH-%'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($vendor) {
        $iv = implode(',', array_map('intval', $vendor));
        $peng = $pdo->query("SELECT id_pengajuan FROM tbl_pengajuan_vendor WHERE id_vendor IN ({$iv})")->fetchAll(PDO::FETCH_COLUMN);
        if ($peng) {
            $ip = implode(',', array_map('intval', $peng));
            foreach (['tbl_jawaban_vendor', 'tbl_catatan_tdr', 'tbl_penetapan_klasifikasi', 'tbl_rekomendasi_klasifikasi',
                      'tbl_hasil_verifikasi', 'tbl_jadwal_verifikasi', 'tbl_dokumen_vendor', 'tbl_riwayat_status'] as $t) {
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
    $pdo->exec("DELETE FROM tbl_akun_vendor WHERE email LIKE '%@contoh.test' AND email <> 'vendor.demo@contoh.test'");
}

if ($hapus) {
    hapusContoh($pdo);
    echo "Data contoh dihapus.\n";
    exit(0);
}
if ((int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email LIKE 'contoh-%@contoh.test'")->fetchColumn() > 0) {
    echo "Data contoh sudah ada. Gunakan argumen 'hapus' untuk menghapusnya terlebih dahulu.\n";
    exit(0);
}

$ver = new Klien($dasar);  $ver->masukStaf('demo_ver1', SANDI);
$plan = new Klien($dasar); $plan->masukStaf('demo_plan', SANDI);
$auth = new Klien($dasar); $auth->masukStaf('demo_auth', SANDI);

/** Urutan tahap yang dicapai pengajuan contoh. */
const TAHAP = ['draf', 'diajukan', 'sedang_diperiksa', 'perlu_perbaikan', 'dokumen_terverifikasi', 'terjadwal',
               'ada_rekomendasi', 'terklasifikasi', 'tdr_tercatat', 'selesai', 'diarsipkan'];

$daftar = [
    ['slug' => 'draf',        'nama' => 'PT Contoh Draf Mandiri',        'nib' => 'CTH-0001', 'sampai' => 'draf',                 'jawab' => 'tertinggi', 'skor' => [0, 0, 0]],
    ['slug' => 'baru',        'nama' => 'CV Contoh Baru Kirim',          'nib' => 'CTH-0002', 'sampai' => 'diajukan',             'jawab' => 'tertinggi', 'skor' => [0, 0, 0]],
    ['slug' => 'diperiksa',   'nama' => 'PT Contoh Sedang Diperiksa',    'nib' => 'CTH-0003', 'sampai' => 'sedang_diperiksa',     'jawab' => 'tertinggi', 'skor' => [0, 0, 0]],
    ['slug' => 'perbaikan',   'nama' => 'UD Contoh Perlu Perbaikan',     'nib' => 'CTH-0004', 'sampai' => 'perlu_perbaikan',      'jawab' => 'terendah',  'skor' => [0, 0, 0]],
    ['slug' => 'menunggu',    'nama' => 'PT Contoh Menunggu Jadwal',     'nib' => 'CTH-0005', 'sampai' => 'dokumen_terverifikasi', 'jawab' => 'tertinggi', 'skor' => [0, 0, 0]],
    ['slug' => 'terjadwal',   'nama' => 'PT Contoh Terjadwal',           'nib' => 'CTH-0006', 'sampai' => 'terjadwal',            'jawab' => 'tertinggi', 'skor' => [0, 0, 0]],
    ['slug' => 'rekomendasi', 'nama' => 'PT Contoh Menunggu Penetapan',  'nib' => 'CTH-0007', 'sampai' => 'ada_rekomendasi',      'jawab' => 'tertinggi', 'skor' => [88, 82, 90]],
    ['slug' => 'klasifikasi', 'nama' => 'CV Contoh Terklasifikasi',      'nib' => 'CTH-0008', 'sampai' => 'terklasifikasi',       'jawab' => 'terendah',  'skor' => [70, 65, 75]],
    ['slug' => 'tdr',         'nama' => 'PT Contoh Proses TDR',          'nib' => 'CTH-0009', 'sampai' => 'tdr_tercatat',         'jawab' => 'tertinggi', 'skor' => [95, 90, 92]],
    ['slug' => 'selesai',     'nama' => 'PT Contoh Selesai',             'nib' => 'CTH-0010', 'sampai' => 'selesai',              'jawab' => 'tertinggi', 'skor' => [85, 80, 88]],
    ['slug' => 'arsip',       'nama' => 'PT Contoh Diarsipkan',          'nib' => 'CTH-0011', 'sampai' => 'diarsipkan',           'jawab' => 'terendah',  'skor' => [40, 45, 50]],
];

$idx = static fn (string $s) => array_search($s, TAHAP, true);

foreach ($daftar as $c) {
    $email = "contoh-{$c['slug']}@contoh.test";
    $v = new Klien($dasar);
    $v->kirim('daftar-vendor/proses', [
        'nama_perusahaan' => $c['nama'], 'nama_kontak' => 'Kontak ' . ucfirst($c['slug']), 'email' => $email,
        'kata_sandi' => SANDI, 'konfirmasi_sandi' => SANDI,
    ], 'daftar-vendor');
    // log pendaftaran akun contoh dihapus agar batas pendaftaran per jam tidak menghalangi pembuatan data contoh
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi IN ('registrasi_vendor','profil_vendor_dibuat') AND id_objek IN (SELECT id_akun_vendor FROM tbl_akun_vendor WHERE email LIKE '%@contoh.test')");
    $v->masukVendor($email, SANDI);
    $v->kirim('vendor/formulir/simpan', [
        'langkah' => '1', 'nama_perusahaan' => $c['nama'], 'bentuk_badan_usaha' => str_starts_with($c['nama'], 'CV') ? 'CV' : (str_starts_with($c['nama'], 'UD') ? 'UD' : 'PT'),
        'nib' => $c['nib'], 'bidang_usaha' => 'Pelatihan dan sertifikasi', 'alamat' => 'Jl. Contoh Raya No. ' . random_int(1, 99) . ', Jakarta Selatan',
        'nama_penanggung_jawab' => 'Penanggung Jawab ' . ucfirst($c['slug']), 'email_perusahaan' => "kantor-{$c['slug']}@contoh.test",
        'no_telepon' => '021-' . random_int(5000000, 5999999),
    ], 'vendor/formulir');
    $v->kirim('vendor/formulir/simpan', ['langkah' => '2', 'jumlah_karyawan' => (string) random_int(5, 80), 'tahun_berdiri' => (string) random_int(2005, 2022), 'situs_web' => "https://{$c['slug']}.contoh.test"], 'vendor/formulir');
    jawabSemuaPertanyaan($v, $pdo, $c['jawab']);
    $syarat = $pdo->query('SELECT id_syarat, kode_syarat FROM tbl_syarat_dokumen WHERE aktif=1 AND wajib=1')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($syarat as $idS => $kode) {
        $v->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $idS, 'berkas' => berkas($PDF, "{$kode}.pdf")], 'vendor/formulir', '&langkah=4');
    }
    echo "- {$c['nama']}: ";
    if ($idx($c['sampai']) <= $idx('draf')) {
        echo "draf\n";
        continue;
    }
    $v->kirim('vendor/kirim', [], 'vendor/dashboard');
    $id = (int) $pdo->query(
        "SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor vv ON vv.id_vendor=p.id_vendor
          JOIN tbl_akun_vendor a ON a.id_akun_vendor=vv.id_akun_vendor WHERE a.email=" . $pdo->quote($email)
    )->fetchColumn();
    $tahap = $idx($c['sampai']);
    $dokumen = static fn () => Database::prototype()->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$id}")->fetchAll(PDO::FETCH_COLUMN);

    if ($tahap >= $idx('sedang_diperiksa')) {
        $ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    }
    if ($c['sampai'] === 'perlu_perbaikan') {
        $d = $dokumen();
        $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $id, 'id_dokumen' => (string) $d[0], 'hasil' => 'tidak_sesuai', 'catatan' => 'Dokumen kurang jelas, mohon unggah ulang.'], 'staf/dashboard');
        $ver->kirim('staf/pengajuan/kembalikan', ['id_pengajuan' => (string) $id, 'catatan' => 'Salah satu dokumen kurang jelas dan perlu diunggah ulang.'], 'staf/dashboard');
    }
    if ($tahap >= $idx('dokumen_terverifikasi')) {
        foreach ($dokumen() as $d) {
            $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $id, 'id_dokumen' => (string) $d, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
        }
        $ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    }
    if ($tahap >= $idx('terjadwal')) {
        $plan->kirim('staf/jadwal/simpan', [
            'id_pengajuan' => (string) $id, 'tanggal_verifikasi' => date('Y-m-d\TH:i', strtotime('+' . random_int(2, 9) . ' days 10:00')),
            'metode' => 'kunjungan_lapangan', 'lokasi' => 'Kantor vendor (contoh)', 'id_verifikator' => '902', 'catatan' => 'Data contoh',
        ], 'staf/dashboard');
    }
    if ($tahap >= $idx('ada_rekomendasi')) {
        [$a, $b, $cc] = $c['skor'];
        $ver->kirim('staf/hasil/simpan', [
            'id_pengajuan' => (string) $id, 'skor_contoh_1' => (string) $a, 'skor_contoh_2' => (string) $b, 'skor_contoh_3' => (string) $cc,
            'kesimpulan' => 'memenuhi', 'temuan' => 'Data contoh: kondisi usaha sesuai dengan dokumen pendaftaran.',
        ], 'staf/dashboard');
    }
    if ($tahap >= $idx('terklasifikasi')) {
        $label = $pdo->query("SELECT label_klasifikasi FROM tbl_rekomendasi_klasifikasi WHERE id_pengajuan={$id}")->fetchColumn();
        $auth->kirim('staf/penetapan/simpan', ['id_pengajuan' => (string) $id, 'label_klasifikasi' => (string) $label, 'alasan' => ''], 'staf/dashboard');
    }
    if ($tahap >= $idx('tdr_tercatat')) {
        $auth->kirim('staf/tdr/simpan', ['id_pengajuan' => (string) $id, 'tanggal_proses' => date('Y-m-d'), 'keterangan' => 'Data contoh: proses TDR (simulasi) dicatat.'], 'staf/dashboard');
    }
    if ($tahap >= $idx('selesai')) {
        $auth->kirim('staf/pengajuan/selesaikan', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    }
    if ($tahap >= $idx('diarsipkan')) {
        $auth->kirim('staf/pengajuan/arsipkan', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    }
    echo $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$id}")->fetchColumn() . "\n";
}

// satu pengajuan manual
$ver->kirim('staf/manual/simpan', [
    'nama_perusahaan' => 'PT Contoh Proses Manual', 'bentuk_badan_usaha' => 'PT', 'nib' => 'CTH-0012',
    'bidang_usaha' => 'Seragam / desain', 'alamat' => 'Jl. Manual Contoh No. 12, Bandung',
    'nama_penanggung_jawab' => 'Petugas Contoh', 'email_perusahaan' => 'manual@contoh.test', 'no_telepon' => '022-5550012',
], 'staf/manual');
echo "- PT Contoh Proses Manual: diajukan (sumber proses manual)\n";
echo "\nSelesai. Akun vendor contoh: contoh-<nama>@contoh.test / " . SANDI . "\n";
