<?php
declare(strict_types=1);

/**
 * Uji otomatis Tahap 4: registrasi vendor, profil, formulir, upload dokumen,
 * kepemilikan data, dan aturan perpindahan status.
 * Cara pakai (server harus berjalan):
 *   php -S 127.0.0.1:8099 -t public
 *   php tests/uji_tahap4.php http://127.0.0.1:8099
 * Data uji memakai email @uji.test dan dihapus otomatis di awal serta akhir.
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Klien.php';
require __DIR__ . '/Bantu.php';

use App\Core\Database;
use App\Services\WorkflowService;

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
    $akun = $pdo->query("SELECT id_akun_vendor FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchAll(PDO::FETCH_COLUMN);
    if (!$akun) {
        return;
    }
    $in = implode(',', array_map('intval', $akun));
    $peng = $pdo->query(
        "SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor
          WHERE v.id_akun_vendor IN ({$in})"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($peng) {
        $ip = implode(',', array_map('intval', $peng));
        $pdo->exec("DELETE FROM tbl_jawaban_vendor WHERE id_pengajuan IN ({$ip})");
            $pdo->exec("DELETE FROM tbl_dokumen_vendor WHERE id_pengajuan IN ({$ip})");
        $pdo->exec("DELETE FROM tbl_riwayat_status WHERE id_pengajuan IN ({$ip})");
        $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE id_pengajuan IN ({$ip})");
        $pdo->exec("DELETE FROM tbl_pengajuan_vendor WHERE id_pengajuan IN ({$ip})");
        foreach ($peng as $id) {
            $dir = BASE_PATH . '/storage/uploads/' . (int) $id;
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }
    $pdo->exec("DELETE FROM tbl_vendor WHERE id_akun_vendor IN ({$in})");
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE tipe_pelaku='vendor' AND id_pelaku IN ({$in})");
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi IN ('registrasi_vendor','profil_vendor_dibuat')
                  AND (JSON_EXTRACT(detail,'$.id_akun_vendor') IN ({$in}) OR id_objek IN ({$in}))");
    $pdo->exec("DELETE FROM tbl_akun_vendor WHERE id_akun_vendor IN ({$in})");
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi='login_gagal' AND JSON_UNQUOTE(JSON_EXTRACT(detail,'$.identitas')) LIKE 'vendor:%@uji.test'");
}

function berkasUji(string $isi, string $nama): CURLFile
{
    $dir = sys_get_temp_dir() . '/vmp_uji';
    @mkdir($dir);
    $path = $dir . '/' . $nama;
    file_put_contents($path, $isi);
    return new CURLFile($path, 'application/octet-stream', $nama);
}

function daftar(string $dasar, string $email, string $usaha = 'PT Uji Maju Jaya'): Klien
{
    $k = new Klien($dasar);
    $k->kirim('daftar-vendor/proses', [
        'nama_perusahaan' => $usaha, 'nama_kontak' => 'Kontak Uji', 'email' => $email,
        'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
    ], 'daftar-vendor');
    return $k;
}

$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
$PNG = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

bersihkan($pdo);

echo "== 1. Registrasi vendor ==\n";
$k = new Klien($dasar);
cek('halaman daftar-vendor terbuka', $k->minta('daftar-vendor')['kode'] === 200);
$r = $k->minta('daftar-vendor/proses', 'POST', ['email' => 'x@uji.test']);
cek('registrasi tanpa CSRF ditolak (400)', $r['kode'] === 400);

$kasus = [
    'sandi lemah (tanpa angka)'  => ['email' => 'lemah@uji.test', 'kata_sandi' => 'hurufsajahurufsaja', 'konfirmasi_sandi' => 'hurufsajahurufsaja'],
    'sandi terlalu pendek'       => ['email' => 'pendek@uji.test', 'kata_sandi' => 'abc123', 'konfirmasi_sandi' => 'abc123'],
    'konfirmasi tidak sama'      => ['email' => 'beda@uji.test', 'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => 'Lain12345678'],
    'email tidak valid'          => ['email' => 'bukan-email', 'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR],
];
foreach ($kasus as $nama => $data) {
    $c = new Klien($dasar);
    $r = $c->kirim('daftar-vendor/proses', $data + ['nama_perusahaan' => 'PT Uji', 'nama_kontak' => 'Uji Kontak'], 'daftar-vendor');
    $hal = $c->minta('daftar-vendor');
    cek("registrasi ditolak: {$nama}", $r['kode'] === 302 && str_contains($r['lokasi'], 'daftar-vendor') && str_contains($hal['isi'], 'galat-field'));
    $n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email = '" . $data['email'] . "'")->fetchColumn();
    cek("  akun tidak dibuat: {$nama}", $n === 0);
}
cek('isian sandi tidak dikembalikan ke form', !str_contains($hal['isi'], SANDI_VENDOR));

$a = daftar($dasar, 'a1@uji.test', 'PT Uji Maju Jaya');
$row = $pdo->query("SELECT * FROM tbl_akun_vendor WHERE email='a1@uji.test'")->fetch();
cek('akun vendor dibuat', (bool) $row);
cek('kata sandi tersimpan sebagai hash bcrypt', $row && str_starts_with($row['kata_sandi'], '$2y$') && !str_contains($row['kata_sandi'], SANDI_VENDOR));
$c = new Klien($dasar);
$r = $c->kirim('daftar-vendor/proses', [
    'nama_perusahaan' => 'PT Lain', 'nama_kontak' => 'Kontak', 'email' => 'A1@UJI.TEST',
    'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
], 'daftar-vendor');
$n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email='a1@uji.test'")->fetchColumn();
cek('email ganda (beda huruf besar) ditolak', $n === 1 && str_contains($c->minta('daftar-vendor')['isi'], 'sudah terdaftar'));

echo "== 2. Login, dashboard, XSS ==\n";
$r = $a->masukVendor('a1@uji.test', SANDI_VENDOR);
cek('vendor baru dapat login', $r['kode'] === 302 && str_contains($r['lokasi'], 'vendor/dashboard'), $r['lokasi']);
$dash = $a->minta('vendor/dashboard');
cek('dashboard menampilkan status Draf', $dash['kode'] === 200 && str_contains($dash['isi'], 'status-abu') && str_contains($dash['isi'], 'Draf'));
cek('dashboard memuat nama perusahaan', str_contains($dash['isi'], 'PT Uji Maju Jaya'));
$idAkunA = (int) $row['id_akun_vendor'];
$vA = $pdo->query("SELECT * FROM tbl_vendor WHERE id_akun_vendor={$idAkunA}")->fetch();
$pA = $pdo->query("SELECT * FROM tbl_pengajuan_vendor WHERE id_vendor={$vA['id_vendor']}")->fetch();
cek('profil vendor dan pengajuan draf terbentuk', $vA && $pA && $pA['status_pengajuan'] === 'draf' && $pA['sumber_pengajuan'] === 'portal_vendor_management');
$idP = (int) $pA['id_pengajuan'];

$x = daftar($dasar, 'x1@uji.test', '<img src=x onerror=alert(1)>Xss');
$x->masukVendor('x1@uji.test', SANDI_VENDOR);
$isi = $x->minta('vendor/dashboard')['isi'];
cek('nama perusahaan berisi HTML di-escape (XSS)', !str_contains($isi, '<img src=x') && str_contains($isi, '&lt;img'));

echo "== 3. Profil perusahaan dan informasi tambahan ==\n";
$profil = [
    'langkah' => '1', 'nama_perusahaan' => 'PT Uji Maju Jaya', 'bentuk_badan_usaha' => 'PT',
    'nib' => '1234567890123', 'bidang_usaha' => 'Seragam / desain',
    'alamat' => 'Jl. Contoh Raya No. 1, Jakarta', 'nama_penanggung_jawab' => 'Budi Uji',
    'email_perusahaan' => 'kantor@uji.test', 'no_telepon' => '021-5551234', 'simpan_lanjut' => '1',
];
$r = $a->kirim('vendor/formulir/simpan', array_merge($profil, ['email_perusahaan' => 'salah', 'bidang_usaha' => 'Ngawur']), 'vendor/formulir');
$hal = $a->minta('vendor/formulir');
cek('profil tidak valid ditolak dengan pesan per field', str_contains($hal['isi'], 'galat-field') || str_contains($a->minta('vendor/formulir', 'GET', [], '&langkah=1')['isi'], 'galat-field'));
$v = $pdo->query("SELECT * FROM tbl_vendor WHERE id_vendor={$vA['id_vendor']}")->fetch();
cek('profil tidak valid tidak tersimpan', $v['email_perusahaan'] === null);
$r = $a->kirim('vendor/formulir/simpan', $profil, 'vendor/formulir');
$v = $pdo->query("SELECT * FROM tbl_vendor WHERE id_vendor={$vA['id_vendor']}")->fetch();
cek('profil valid tersimpan', $r['kode'] === 302 && $v['email_perusahaan'] === 'kantor@uji.test' && $v['nib'] === '1234567890123');
cek('nama_perusahaan_normal dihitung', $v['nama_perusahaan_normal'] === 'uji maju jaya', (string) $v['nama_perusahaan_normal']);

$b = daftar($dasar, 'b1@uji.test', 'PT Vendor Dua');
$b->masukVendor('b1@uji.test', SANDI_VENDOR);
$b->kirim('vendor/formulir/simpan', array_merge($profil, ['nama_perusahaan' => 'PT Vendor Dua', 'email_perusahaan' => 'dua@uji.test']), 'vendor/formulir');
$nibB = $pdo->query("SELECT nib FROM tbl_vendor v JOIN tbl_akun_vendor a ON a.id_akun_vendor=v.id_akun_vendor WHERE a.email='b1@uji.test'")->fetchColumn();
cek('NIB ganda antar vendor ditolak', $nibB === null);

$r = $a->kirim('vendor/formulir/simpan', ['langkah' => '2', 'jumlah_karyawan' => 'banyak', 'tahun_berdiri' => '2010'], 'vendor/formulir');
$p = $pdo->query("SELECT data_tambahan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetchColumn();
cek('informasi tambahan tidak valid ditolak', $p === null);
$r = $a->kirim('vendor/formulir/simpan', ['langkah' => '2', 'jumlah_karyawan' => '25', 'tahun_berdiri' => '2010', 'situs_web' => 'https://uji.test'], 'vendor/formulir');
$p = json_decode((string) $pdo->query("SELECT data_tambahan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetchColumn(), true);
cek('informasi tambahan valid tersimpan', ($p['jumlah_karyawan'] ?? null) === '25' && ($p['tahun_berdiri'] ?? null) === '2010');
$r = $a->kirim('vendor/formulir/simpan', ['langkah' => '9'], 'vendor/formulir');
cek('langkah formulir tidak dikenal ditolak (400)', $r['kode'] === 400);

echo "== 4. Pengiriman sebelum lengkap ==\n";
$r = $a->kirim('vendor/kirim', [], 'vendor/dashboard');
$st = $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetchColumn();
cek('kirim tanpa dokumen wajib ditolak, status tetap draf', $st === 'draf');

echo "== 5. Upload dokumen ==\n";
$syarat = $pdo->query("SELECT id_syarat, kode_syarat FROM tbl_syarat_dokumen WHERE aktif=1")->fetchAll(PDO::FETCH_KEY_PAIR);
$idSyarat = array_flip($syarat);
$unggah = static function (Klien $kl, int $idS, CURLFile $f) {
    return $kl->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $idS, 'berkas' => $f], 'vendor/formulir', '&langkah=3');
};
$jumlahDok = static fn (int $idP) => (int) Database::prototype()->query("SELECT COUNT(*) FROM tbl_dokumen_vendor WHERE id_pengajuan={$idP}")->fetchColumn();

$unggah($a, $idSyarat['akta_pendirian'], berkasUji('<?php echo 1;', 'shell.php'));
cek('berkas .php ditolak', $jumlahDok($idP) === 0);
$unggah($a, $idSyarat['akta_pendirian'], berkasUji('ini hanya teks biasa', 'palsu.pdf'));
cek('berkas teks bernama .pdf ditolak (isi diperiksa)', $jumlahDok($idP) === 0);
$unggah($a, $idSyarat['akta_pendirian'], berkasUji($PDF, 'ganda.pdf.php'));
cek('ekstensi ganda .pdf.php ditolak', $jumlahDok($idP) === 0);
$unggah($a, $idSyarat['akta_pendirian'], berkasUji($PDF, 'pdf-rasa-png.png'));
cek('PDF dengan ekstensi .png ditolak', $jumlahDok($idP) === 0);
$unggah($a, $idSyarat['akta_pendirian'], berkasUji("%PDF-1.4\n" . str_repeat('A', 2200 * 1024), 'besar.pdf'));
cek('berkas melebihi batas ukuran ditolak', $jumlahDok($idP) === 0);
$unggah($a, 99999, berkasUji($PDF, 'x.pdf'));
cek('id persyaratan tidak dikenal ditolak', $jumlahDok($idP) === 0);

$unggah($a, $idSyarat['akta_pendirian'], berkasUji($PDF, 'akta saya.pdf'));
$unggah($a, $idSyarat['npwp'], berkasUji($PDF, 'npwp.pdf'));
$unggah($a, $idSyarat['nib'], berkasUji($PNG, 'nib.png'));
cek('3 dokumen wajib berhasil diunggah', $jumlahDok($idP) === 3);
$d = $pdo->query("SELECT * FROM tbl_dokumen_vendor WHERE id_pengajuan={$idP} AND id_syarat={$idSyarat['akta_pendirian']}")->fetch();
$jalur = BASE_PATH . "/storage/uploads/{$idP}/" . $d['nama_file_simpan'];
cek('berkas tersimpan di storage/ dengan nama acak', is_file($jalur) && preg_match('/^[a-f0-9]{32}\.pdf$/', $d['nama_file_simpan']) === 1);
cek('berkas tidak berada di folder public', !file_exists(BASE_PATH . '/public/storage') && !str_contains($jalur, '/public/'));
$idDokA = (int) $d['id_dokumen'];

$unggah($a, $idSyarat['akta_pendirian'], berkasUji($PDF . "tambahan\n", 'akta-baru.pdf'));
$d2 = $pdo->query("SELECT * FROM tbl_dokumen_vendor WHERE id_pengajuan={$idP} AND id_syarat={$idSyarat['akta_pendirian']}")->fetch();
cek('unggah ulang menimpa dokumen (tetap 3 baris, nama berkas baru)', $jumlahDok($idP) === 3 && $d2['nama_file_simpan'] !== $d['nama_file_simpan']);
clearstatcache();
cek('berkas lama dihapus dari penyimpanan', !is_file($jalur));

echo "== 6. Pengiriman pengajuan ==\n";
$a->kirim('vendor/kirim', [], 'vendor/dashboard');
$stSebelum = $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetchColumn();
cek('kirim ditolak selama pertanyaan penilaian belum dijawab', $stSebelum === 'draf');
jawabSemuaPertanyaan($a, $pdo);
$a->kirim('vendor/kirim', [], 'vendor/dashboard');
$p = $pdo->query("SELECT * FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetch();
cek('pengajuan lengkap berhasil dikirim (status diajukan)', $p['status_pengajuan'] === 'diajukan');
cek('nomor pengajuan terbentuk', str_starts_with($p['nomor_pengajuan'], 'VM-'), $p['nomor_pengajuan']);
cek('tanggal_diajukan terisi', $p['tanggal_diajukan'] !== null);
$rw = $pdo->query("SELECT COUNT(*) FROM tbl_riwayat_status WHERE id_pengajuan={$idP} AND status_tujuan='diajukan' AND tipe_pelaku='vendor'")->fetchColumn();
cek('riwayat status mencatat pengiriman', (int) $rw === 1);

$a->kirim('vendor/formulir/simpan', array_merge($profil, ['nama_perusahaan' => 'PT Diubah Setelah Kirim']), 'vendor/dashboard');
$nm = $pdo->query("SELECT nama_perusahaan FROM tbl_vendor WHERE id_vendor={$vA['id_vendor']}")->fetchColumn();
cek('profil tidak dapat diubah setelah dikirim', $nm === 'PT Uji Maju Jaya');
$unggah($a, $idSyarat['domisili'], berkasUji($PDF, 'domisili.pdf'));
cek('upload ditolak setelah dikirim', $jumlahDok($idP) === 3);
$a->kirim('vendor/dokumen/hapus', ['id_dokumen' => (string) $d2['id_dokumen']], 'vendor/dashboard');
cek('hapus dokumen ditolak setelah dikirim', $jumlahDok($idP) === 3);
$a->kirim('vendor/kirim', [], 'vendor/dashboard');
$jml = (int) $pdo->query("SELECT COUNT(*) FROM tbl_riwayat_status WHERE id_pengajuan={$idP} AND status_tujuan='diajukan'")->fetchColumn();
cek('kirim ganda tidak membuat riwayat ganda', $jml === 1);

echo "== 7. Akses dokumen ==\n";
$idDok = (int) $d2['id_dokumen'];
$r = $a->minta('dokumen/unduh', 'GET', [], '&id=' . $idDok);
cek('pemilik dapat mengunduh dokumennya', $r['kode'] === 200 && str_starts_with($r['isi'], '%PDF-'));
$r = $b->minta('dokumen/unduh', 'GET', [], '&id=' . $idDok);
cek('vendor lain tidak dapat membuka dokumen (404)', $r['kode'] === 404);
$r = (new Klien($dasar))->minta('dokumen/unduh', 'GET', [], '&id=' . $idDok);
cek('tanpa login diarahkan ke login', $r['kode'] === 302);
cek('id dokumen tidak ada -> 404', $a->minta('dokumen/unduh', 'GET', [], '&id=99999999')['kode'] === 404);
cek('id dokumen bukan angka -> 404', $a->minta('dokumen/unduh', 'GET', [], '&id=abc')['kode'] === 404);
$ver = new Klien($dasar);
$ver->masukStaf('demo_ver1', SANDI);
cek('petugas verifikasi dapat membuka dokumen', $ver->minta('dokumen/unduh', 'GET', [], '&id=' . $idDok)['kode'] === 200);
$plan = new Klien($dasar);
$plan->masukStaf('demo_plan', SANDI);
cek('divisi perencanaan tidak dapat membuka dokumen (403)', $plan->minta('dokumen/unduh', 'GET', [], '&id=' . $idDok)['kode'] === 403);
cek('staf tidak dapat memakai rute unggah vendor (403)', $ver->kirim('vendor/dokumen/unggah', ['id_syarat' => '1'], 'staf/dashboard')['kode'] === 403);

echo "== 8. Aturan perpindahan status ==\n";
$vendorP = ['tipe' => 'vendor', 'id' => $idAkunA, 'peran' => ['vendor']];
$staf = static fn (array $peran) => ['tipe' => 'staf', 'id' => 902, 'peran' => $peran];
cek('vendor tidak boleh diajukan -> sedang_diperiksa', !WorkflowService::boleh('diajukan', 'sedang_diperiksa', $vendorP));
cek('verifikator boleh diajukan -> sedang_diperiksa', WorkflowService::boleh('diajukan', 'sedang_diperiksa', $staf(['verifikator'])));
cek('admin tidak boleh mengubah status', !WorkflowService::boleh('diajukan', 'sedang_diperiksa', $staf(['admin'])) && !WorkflowService::boleh('draf', 'diajukan', $staf(['admin'])));
cek('loncat status ditolak (diajukan -> terklasifikasi)', !WorkflowService::boleh('diajukan', 'terklasifikasi', $staf(['berwenang'])));
cek('hanya perencanaan menjadwalkan', WorkflowService::boleh('dokumen_terverifikasi', 'terjadwal', $staf(['perencanaan'])) && !WorkflowService::boleh('dokumen_terverifikasi', 'terjadwal', $staf(['verifikator'])));
cek('hanya berwenang menetapkan klasifikasi', WorkflowService::boleh('ada_rekomendasi', 'terklasifikasi', $staf(['berwenang'])) && !WorkflowService::boleh('ada_rekomendasi', 'terklasifikasi', $staf(['verifikator'])));
cek('rekomendasi hanya oleh sistem', WorkflowService::boleh('terverifikasi', 'ada_rekomendasi', ['tipe' => 'sistem', 'id' => null, 'peran' => []]) && !WorkflowService::boleh('terverifikasi', 'ada_rekomendasi', $staf(['berwenang'])));

$alur = new WorkflowService();
$gagalOK = false;
try {
    $alur->ubahStatus($idP, 'sedang_diperiksa', $vendorP);
} catch (DomainException $e) {
    $gagalOK = true;
}
cek('ubahStatus oleh vendor yang tidak berwenang melempar galat', $gagalOK);
$st = $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetchColumn();
cek('status tidak berubah setelah percobaan ditolak', $st === 'diajukan');

$alur->ubahStatus($idP, 'sedang_diperiksa', $staf(['verifikator']));
$alur->ubahStatus($idP, 'perlu_perbaikan', $staf(['verifikator']), 'Dokumen NPWP buram');
$p = $pdo->query("SELECT * FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetch();
cek('verifikator dapat memeriksa lalu mengembalikan', $p['status_pengajuan'] === 'perlu_perbaikan' && (int) $p['jumlah_pengembalian'] === 1);
$dash = $a->minta('vendor/dashboard');
cek('vendor melihat status Perlu perbaikan', str_contains($dash['isi'], 'Perlu perbaikan') && str_contains($dash['isi'], 'status-kuning'));

$r = $a->kirim('vendor/formulir/simpan', array_merge($profil, ['langkah' => '1', 'alamat' => 'Jl. Perbaikan Alamat No. 9, Jakarta']), 'vendor/formulir');
$al = $pdo->query("SELECT alamat FROM tbl_vendor WHERE id_vendor={$vA['id_vendor']}")->fetchColumn();
cek('vendor dapat memperbaiki data setelah dikembalikan', str_contains((string) $al, 'Perbaikan'));
$a->kirim('vendor/kirim', [], 'vendor/dashboard');
$st = $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetchColumn();
cek('vendor dapat mengirim ulang', $st === 'diajukan');
$nomorAkhir = $pdo->query("SELECT nomor_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetchColumn();
cek('nomor pengajuan tidak berubah saat kirim ulang', $nomorAkhir === $p['nomor_pengajuan']);

echo "== 9. Audit log dan kebersihan ==\n";
foreach (['registrasi_vendor', 'dokumen_diunggah', 'status_diubah', 'profil_vendor_disimpan'] as $aksi) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE aksi='{$aksi}'")->fetchColumn();
    cek("audit log memuat {$aksi}", $n > 0);
}
$n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE detail LIKE '%" . SANDI_VENDOR . "%'")->fetchColumn();
cek('audit log tidak menyimpan kata sandi', $n === 0);

bersihkan($pdo);
$sisa = (int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchColumn();
cek('data uji dibersihkan', $sisa === 0);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
