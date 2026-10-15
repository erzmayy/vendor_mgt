<?php
declare(strict_types=1);

/**
 * Uji otomatis Tahap 5: dashboard petugas, daftar/filter, pemeriksaan dokumen,
 * pengembalian, penjadwalan, pengelolaan peran, dan mode sesi SIAP.
 *   php -S 127.0.0.1:8099 -t public
 *   php tests/uji_tahap5.php http://127.0.0.1:8099
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Klien.php';
require __DIR__ . '/Bantu.php';

use App\Core\Database;
use App\Services\AuthService;

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
    if ($akun) {
        $in = implode(',', array_map('intval', $akun));
        $peng = $pdo->query(
            "SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor
              WHERE v.id_akun_vendor IN ({$in})"
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($peng) {
            $ip = implode(',', array_map('intval', $peng));
            $pdo->exec("DELETE FROM tbl_jadwal_verifikasi WHERE id_pengajuan IN ({$ip})");
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
    }
    // pulihkan keadaan awal data demo yang dipakai uji peran
    $pdo->exec("DELETE FROM tbl_peran_user WHERE id_user = 906");
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi = 'peran_diubah' AND id_objek = 906");
    $pdo->exec("UPDATE tbl_peran_user SET aktif = 1 WHERE id_user = 905 AND peran = 'admin'");
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi='login_gagal' AND JSON_UNQUOTE(JSON_EXTRACT(detail,'$.identitas')) LIKE '%demo\\_%'");
}

function berkas(string $isi, string $nama): CURLFile
{
    $dir = sys_get_temp_dir() . '/vmp_uji5';
    @mkdir($dir);
    file_put_contents($dir . '/' . $nama, $isi);
    return new CURLFile($dir . '/' . $nama, 'application/octet-stream', $nama);
}

/** Halaman detail sebagai teks (termasuk pesan flash). */
function detail(Klien $k, int $id): array
{
    return $k->minta('staf/pengajuan/detail', 'GET', [], '&id=' . $id);
}

$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
bersihkan($pdo);

echo "== Persiapan: vendor mengirim pengajuan ==\n";
$vend = new Klien($dasar);
$vend->kirim('daftar-vendor/proses', [
    'nama_perusahaan' => 'PT Uji Lima Sentosa', 'nama_kontak' => 'Kontak Lima', 'email' => 'lima@uji.test',
    'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
], 'daftar-vendor');
$vend->masukVendor('lima@uji.test', SANDI_VENDOR);
$vend->kirim('vendor/formulir/simpan', [
    'langkah' => '1', 'nama_perusahaan' => 'PT Uji Lima Sentosa', 'bentuk_badan_usaha' => 'PT', 'nib' => 'UJI5-0001',
    'bidang_usaha' => 'Pelatihan dan sertifikasi', 'alamat' => 'Jl. Lima Contoh No. 5, Bandung',
    'nama_penanggung_jawab' => 'Lima Uji', 'email_perusahaan' => 'lima-kantor@uji.test', 'no_telepon' => '022-555123',
], 'vendor/formulir');
$syarat = array_flip($pdo->query('SELECT id_syarat, kode_syarat FROM tbl_syarat_dokumen')->fetchAll(PDO::FETCH_KEY_PAIR));
foreach (['akta_pendirian', 'npwp', 'nib'] as $kode) {
    $vend->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $syarat[$kode], 'berkas' => berkas($PDF, "$kode.pdf")], 'vendor/formulir', '&langkah=3');
}
jawabSemuaPertanyaan($vend, $pdo);
$vend->kirim('vendor/kirim', [], 'vendor/dashboard');
$row = $pdo->query("SELECT p.id_pengajuan, p.status_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor JOIN tbl_akun_vendor a ON a.id_akun_vendor=v.id_akun_vendor WHERE a.email='lima@uji.test'")->fetch();
$idP = (int) $row['id_pengajuan'];
cek('pengajuan uji berstatus diajukan', $row['status_pengajuan'] === 'diajukan');

// vendor kedua: tetap draf, tidak boleh terlihat petugas
$vend2 = new Klien($dasar);
$vend2->kirim('daftar-vendor/proses', [
    'nama_perusahaan' => 'PT Uji Draf Rahasia', 'nama_kontak' => 'Kontak Draf', 'email' => 'draf@uji.test',
    'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
], 'daftar-vendor');
$idDraf = (int) $pdo->query("SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor JOIN tbl_akun_vendor a ON a.id_akun_vendor=v.id_akun_vendor WHERE a.email='draf@uji.test'")->fetchColumn();

$ver = new Klien($dasar);  $ver->masukStaf('demo_ver1', SANDI);
$plan = new Klien($dasar); $plan->masukStaf('demo_plan', SANDI);
$auth = new Klien($dasar); $auth->masukStaf('demo_auth', SANDI);
$adm = new Klien($dasar);  $adm->masukStaf('demo_admin', SANDI);

echo "== 1. Dashboard dan sidebar ==\n";
$r = $ver->minta('staf/dashboard');
$totalDb = (int) $pdo->query("SELECT COUNT(*) FROM tbl_pengajuan_vendor WHERE status_pengajuan <> 'draf'")->fetchColumn();
cek('dashboard 200 dengan layout sidebar', $r['kode'] === 200 && str_contains($r['isi'], 'class="sidebar"'));
cek('total di dashboard sama dengan database', str_contains($r['isi'], '<strong>' . $totalDb . '</strong>'), "db={$totalDb}");
$diajukanDb = (int) $pdo->query("SELECT COUNT(*) FROM tbl_pengajuan_vendor WHERE status_pengajuan='diajukan'")->fetchColumn();
cek('KPI "Perlu diperiksa" sesuai database', (bool) preg_match('/<div class="angka">' . $diajukanDb . '<\/div>\s*<div class="nama">Perlu diperiksa/', $r['isi']));
cek('menu sidebar verifikator tidak memuat Peran Pengguna', !str_contains($r['isi'], 'admin/peran'));
cek('menu sidebar admin memuat Pengaturan Vendor', str_contains($adm->minta('staf/dashboard')['isi'], 'admin/pengaturan'));
cek('menu sidebar verifikator tidak memuat Pengaturan Vendor', !str_contains($ver->minta('staf/dashboard')['isi'], 'admin/pengaturan'));
cek('menu sidebar perencanaan tidak memuat Penetapan Klasifikasi', !str_contains($plan->minta('staf/dashboard')['isi'], 'staf/penetapan'));

echo "== 2. Daftar pengajuan: cari dan filter ==\n";
$q = static fn (Klien $k, string $qs) => $k->minta('staf/pengajuan', 'GET', [], $qs);
$r = $q($ver, '');
cek('daftar memuat pengajuan terkirim', $r['kode'] === 200 && str_contains($r['isi'], 'PT Uji Lima Sentosa'));
cek('draf vendor tidak tampil di daftar', !str_contains($r['isi'], 'PT Uji Draf Rahasia'));
cek('pencarian nama menemukan', str_contains($q($ver, '&q=Lima+Sentosa')['isi'], 'PT Uji Lima Sentosa'));
cek('pencarian NIB menemukan', str_contains($q($ver, '&q=UJI5-0001')['isi'], 'PT Uji Lima Sentosa'));
cek('pencarian tanpa hasil', str_contains($q($ver, '&q=zzzzzz-tidak-ada')['isi'], '0 pengajuan ditemukan'));
cek('karakter % tidak menjadi wildcard', str_contains($q($ver, '&q=%25')['isi'], '0 pengajuan ditemukan'));
$r = $q($ver, "&q=" . rawurlencode("' OR 1=1 -- "));
cek('input mirip SQL injection aman (tidak ada galat, 0 hasil)', $r['kode'] === 200 && str_contains($r['isi'], '0 pengajuan ditemukan'));
cek('filter status diajukan memuat vendor uji', str_contains($q($ver, '&status=diajukan')['isi'], 'PT Uji Lima Sentosa'));
cek('filter status selesai tidak memuat vendor uji', !str_contains($q($ver, '&status=selesai')['isi'], 'PT Uji Lima Sentosa'));
cek('filter sumber proses_manual tidak memuat vendor uji', !str_contains($q($ver, '&sumber=proses_manual')['isi'], 'PT Uji Lima Sentosa'));
cek('nilai status tidak dikenal diabaikan (tanpa galat)', $q($ver, '&status=ngawur')['kode'] === 200);
cek('halaman tidak valid ditangani', $q($ver, '&halaman=-5')['kode'] === 200 && $q($ver, '&halaman=abc')['kode'] === 200);
cek('vendor tidak dapat membuka daftar petugas (403)', $vend->minta('staf/pengajuan')['kode'] === 403);

echo "== 3. Detail pengajuan dan batas hak ==\n";
$r = detail($ver, $idP);
cek('verifikator melihat detail dan tautan dokumen', $r['kode'] === 200 && str_contains($r['isi'], 'dokumen/unduh'));
$r = detail($plan, $idP);
cek('perencanaan melihat detail tanpa tautan dokumen', $r['kode'] === 200 && !str_contains($r['isi'], 'dokumen/unduh') && str_contains($r['isi'], 'PT Uji Lima Sentosa'));
cek('detail pengajuan draf tidak dapat dibuka petugas (404)', detail($ver, $idDraf)['kode'] === 404);
cek('detail id tidak ada (404)', detail($ver, 99999999)['kode'] === 404);
cek('detail id bukan angka (404)', $ver->minta('staf/pengajuan/detail', 'GET', [], '&id=abc')['kode'] === 404);
cek('perencanaan tidak boleh mulai periksa (403)', $plan->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idP], 'staf/dashboard')['kode'] === 403);
cek('petugas berwenang tidak boleh mengembalikan (403)', $auth->kirim('staf/pengajuan/kembalikan', ['id_pengajuan' => (string) $idP, 'catatan' => 'catatan panjang sekali'], 'staf/dashboard')['kode'] === 403);
cek('vendor tidak boleh memakai rute pemeriksaan (403)', $vend->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idP], 'vendor/dashboard')['kode'] === 403);
$stat = static fn () => $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetchColumn();
cek('status tidak berubah oleh percobaan ditolak', $stat() === 'diajukan');

echo "== 4. Pemeriksaan dan pengembalian ==\n";
$ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
cek('verifikasi dokumen sebelum diperiksa ditolak', $stat() === 'diajukan');
$ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
cek('mulai periksa: status sedang_diperiksa', $stat() === 'sedang_diperiksa');
$dok = $pdo->query("SELECT d.id_dokumen, s.kode_syarat FROM tbl_dokumen_vendor d JOIN tbl_syarat_dokumen s ON s.id_syarat=d.id_syarat WHERE d.id_pengajuan={$idP}")->fetchAll(PDO::FETCH_KEY_PAIR);
$idNpwp = (int) array_search('npwp', $dok, true);
$statDok = static fn (int $id) => $pdo->query("SELECT status_pemeriksaan FROM tbl_dokumen_vendor WHERE id_dokumen={$id}")->fetchColumn();

$ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idP, 'id_dokumen' => (string) $idNpwp, 'hasil' => 'tidak_sesuai', 'catatan' => ''], 'staf/dashboard');
cek('tidak sesuai tanpa catatan ditolak', $statDok($idNpwp) === 'belum_diperiksa');
$ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idP, 'id_dokumen' => (string) $idNpwp, 'hasil' => 'ngawur', 'catatan' => 'x'], 'staf/dashboard');
cek('nilai hasil tidak dikenal ditolak', $statDok($idNpwp) === 'belum_diperiksa');
$r = $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idP, 'id_dokumen' => '999999', 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
$tampil = detail($ver, $idP);
cek('dokumen yang bukan milik pengajuan ditolak dengan pesan', $r['kode'] === 302 && str_contains($tampil['isi'], 'Dokumen tidak ditemukan pada pengajuan ini'));
$ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idP, 'id_dokumen' => (string) $idNpwp, 'hasil' => 'tidak_sesuai', 'catatan' => 'NPWP buram, mohon unggah ulang'], 'staf/dashboard');
cek('tidak sesuai dengan catatan tersimpan', $statDok($idNpwp) === 'tidak_sesuai');
foreach ($dok as $idD => $kode) {
    if ($kode !== 'npwp') {
        $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idP, 'id_dokumen' => (string) $idD, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
    }
}
$ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
cek('verifikasi ditolak selama ada dokumen tidak sesuai', $stat() === 'sedang_diperiksa');

$ver->kirim('staf/pengajuan/kembalikan', ['id_pengajuan' => (string) $idP, 'catatan' => 'pendek'], 'staf/dashboard');
cek('pengembalian dengan catatan terlalu pendek ditolak', $stat() === 'sedang_diperiksa');
$ver->kirim('staf/pengajuan/kembalikan', ['id_pengajuan' => (string) $idP, 'catatan' => 'Dokumen NPWP buram, mohon unggah ulang yang jelas.'], 'staf/dashboard');
$p = $pdo->query("SELECT * FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetch();
cek('pengajuan dikembalikan dengan catatan', $p['status_pengajuan'] === 'perlu_perbaikan' && str_contains((string) $p['catatan_perbaikan'], 'NPWP buram') && (int) $p['jumlah_pengembalian'] === 1);
$dash = $vend->minta('vendor/dashboard');
cek('vendor melihat catatan perbaikan dan dokumen perlu diganti', str_contains($dash['isi'], 'NPWP buram') && str_contains($dash['isi'], 'Perlu diganti'));

$vend->kirim('vendor/kirim', [], 'vendor/dashboard');
cek('kirim ulang ditolak selama dokumen tidak sesuai belum diganti', $stat() === 'perlu_perbaikan');
cek('dashboard vendor menyebut dokumen yang perlu diganti', str_contains($vend->minta('vendor/dashboard')['isi'], 'perlu diganti'));

$vend->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $syarat['npwp'], 'berkas' => berkas($PDF . "baru\n", 'npwp2.pdf')], 'vendor/formulir', '&langkah=3');
cek('unggah ulang mengembalikan dokumen ke belum_diperiksa', $statDok($idNpwp) === 'belum_diperiksa');
$vend->kirim('vendor/kirim', [], 'vendor/dashboard');
cek('vendor mengirim ulang', $stat() === 'diajukan');
$ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
foreach (array_keys($dok) as $idD) {
    $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idP, 'id_dokumen' => (string) $idD, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
}
$ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
cek('semua dokumen sesuai -> dokumen_terverifikasi', $stat() === 'dokumen_terverifikasi');

echo "== 5. Penjadwalan ==\n";
$besok = date('Y-m-d\TH:i', strtotime('+3 days 10:00'));
$lusa = date('Y-m-d\TH:i', strtotime('+5 days 13:30'));
$jml = static fn (string $w = '') => (int) $pdo->query("SELECT COUNT(*) FROM tbl_jadwal_verifikasi WHERE id_pengajuan={$idP} {$w}")->fetchColumn();
$jd = static fn (array $o = []) => array_merge([
    'id_pengajuan' => (string) $idP, 'tanggal_verifikasi' => $besok, 'metode' => 'kunjungan_lapangan',
    'lokasi' => 'Kantor Vendor, Bandung', 'id_verifikator' => '902', 'catatan' => 'Siapkan dokumen asli',
], $o);

cek('verifikator tidak boleh menjadwalkan (403)', $ver->kirim('staf/jadwal/simpan', $jd(), 'staf/dashboard')['kode'] === 403);
$plan->kirim('staf/jadwal/simpan', $jd(['tanggal_verifikasi' => date('Y-m-d\TH:i', strtotime('-1 day'))]), 'staf/dashboard');
cek('jadwal di masa lalu ditolak', $jml() === 0 && $stat() === 'dokumen_terverifikasi');
$plan->kirim('staf/jadwal/simpan', $jd(['tanggal_verifikasi' => 'bukan-tanggal']), 'staf/dashboard');
cek('format tanggal salah ditolak', $jml() === 0);
$plan->kirim('staf/jadwal/simpan', $jd(['id_verifikator' => '905']), 'staf/dashboard');
cek('petugas yang bukan verifikator ditolak', $jml() === 0);
$plan->kirim('staf/jadwal/simpan', $jd(['lokasi' => '']), 'staf/dashboard');
cek('kunjungan lapangan tanpa lokasi ditolak', $jml() === 0);
$plan->kirim('staf/jadwal/simpan', $jd(['metode' => 'ngawur']), 'staf/dashboard');
cek('metode tidak dikenal ditolak', $jml() === 0);

$plan->kirim('staf/jadwal/simpan', $jd(), 'staf/dashboard');
cek('jadwal valid tersimpan dan status terjadwal', $jml("AND status_jadwal='aktif'") === 1 && $stat() === 'terjadwal');
$dashV = $vend->minta('vendor/dashboard');
cek('vendor melihat jadwal verifikasi', str_contains($dashV['isi'], date('d-m-Y', strtotime('+3 days'))) && str_contains($dashV['isi'], 'Kantor Vendor'));
cek('halaman jadwal petugas memuat vendor', str_contains($ver->minta('staf/jadwal')['isi'], 'PT Uji Lima Sentosa'));

$plan->kirim('staf/jadwal/simpan', $jd(['tanggal_verifikasi' => $lusa, 'metode' => 'daring', 'lokasi' => '']), 'staf/dashboard');
cek('jadwal ulang: yang lama nonaktif, yang baru aktif', $jml("AND status_jadwal='dijadwalkan_ulang'") === 1 && $jml("AND status_jadwal='aktif'") === 1 && $stat() === 'terjadwal');

echo "== 6. Peran pengguna (admin) ==\n";
cek('admin membuka halaman peran', $adm->minta('admin/peran')['kode'] === 200);
cek('verifikator tidak boleh membuka halaman peran (403)', $ver->minta('admin/peran')['kode'] === 403);
cek('pencarian pegawai menemukan akun demo', str_contains($adm->minta('admin/peran', 'GET', [], '&q=tanpa')['isi'], 'demo_tanpa_peran'));
cek('verifikator tidak boleh mengubah peran (403)', $ver->kirim('admin/peran/simpan', ['id_user' => '906', 'peran' => 'verifikator', 'aktif' => '1'], 'staf/dashboard')['kode'] === 403);
$tanpa = new Klien($dasar);
$r = $tanpa->masukStaf('demo_tanpa_peran', SANDI);
cek('sebelum dipetakan akun tidak dapat masuk', str_contains($r['lokasi'], 'masuk-staf'));
$adm->kirim('admin/peran/simpan', ['id_user' => '906', 'peran' => 'ngawur', 'aktif' => '1'], 'admin/peran');
cek('peran tidak dikenal ditolak', (int) $pdo->query('SELECT COUNT(*) FROM tbl_peran_user WHERE id_user=906')->fetchColumn() === 0);
$adm->kirim('admin/peran/simpan', ['id_user' => '999999', 'peran' => 'verifikator', 'aktif' => '1'], 'admin/peran');
cek('pegawai tidak ada ditolak', (int) $pdo->query('SELECT COUNT(*) FROM tbl_peran_user WHERE id_user=999999')->fetchColumn() === 0);
$adm->kirim('admin/peran/simpan', ['id_user' => '906', 'peran' => 'verifikator', 'aktif' => '1'], 'admin/peran');
$tanpa2 = new Klien($dasar);
$r = $tanpa2->masukStaf('demo_tanpa_peran', SANDI);
cek('setelah dipetakan akun dapat masuk', str_contains($r['lokasi'], 'staf/dashboard'));
$adm->kirim('admin/peran/simpan', ['id_user' => '906', 'peran' => 'verifikator', 'aktif' => '0'], 'admin/peran');
$tanpa3 = new Klien($dasar);
$r = $tanpa3->masukStaf('demo_tanpa_peran', SANDI);
cek('setelah dinonaktifkan akun ditolak lagi', str_contains($r['lokasi'], 'masuk-staf'));
$adm->kirim('admin/peran/simpan', ['id_user' => '905', 'peran' => 'admin', 'aktif' => '0'], 'admin/peran');
$a = $pdo->query("SELECT aktif FROM tbl_peran_user WHERE id_user=905 AND peran='admin'")->fetchColumn();
cek('administrator aktif terakhir tidak dapat dinonaktifkan', (int) $a === 1);

echo "== 7. Mode sesi SIAP (logika pemulihan pengguna) ==\n";
$svc = new AuthService();
$u = $svc->dariSesiSiap(['user_id' => 902]);
cek('user_id valid + peran aktif -> pengguna dipulihkan', $u !== null && $u['peran'] === ['verifikator'] && $u['id'] === 902);
cek('user_id string angka diterima', ($svc->dariSesiSiap(['user_id' => '901'])['peran'] ?? null) === ['perencanaan']);
cek('user_id tanpa peran -> ditolak', $svc->dariSesiSiap(['user_id' => 906]) === null);
cek('user_id tidak ada -> ditolak', $svc->dariSesiSiap(['user_id' => 99999999]) === null);
cek('user_id bukan angka -> ditolak', $svc->dariSesiSiap(['user_id' => '1 OR 1=1']) === null);
cek('tanpa user_id -> ditolak', $svc->dariSesiSiap([]) === null);

echo "== 8. Audit log ==\n";
foreach (['dokumen_diperiksa', 'jadwal_dibuat', 'jadwal_diubah', 'peran_diubah', 'status_diubah'] as $aksi) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE aksi='{$aksi}'")->fetchColumn();
    cek("audit log memuat {$aksi}", $n > 0);
}
$idDok = (int) array_key_first($dok);
$ver->minta('dokumen/unduh', 'GET', [], '&id=' . $idDok);
$n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE aksi='dokumen_dilihat' AND id_pengajuan={$idP}")->fetchColumn();
cek('pembukaan dokumen oleh petugas tercatat', $n >= 1);

bersihkan($pdo);
$sisa = (int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchColumn()
      + (int) $pdo->query('SELECT COUNT(*) FROM tbl_peran_user WHERE id_user=906')->fetchColumn();
cek('data uji dibersihkan', $sisa === 0);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
