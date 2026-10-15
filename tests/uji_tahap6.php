<?php
declare(strict_types=1);

/**
 * Uji otomatis Tahap 6: hasil verifikasi, rekomendasi, penetapan, TDR (simulasi),
 * penyelesaian, arsip, pencatatan manual + duplikat, dan konfigurasi admin.
 *   php -S 127.0.0.1:8099 -t public
 *   php tests/uji_tahap6.php http://127.0.0.1:8099
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Klien.php';
require __DIR__ . '/Bantu.php';

use App\Core\Database;
use App\Services\KlasifikasiService;

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

function hapusPengajuan(PDO $pdo, array $idPengajuan): void
{
    if (!$idPengajuan) {
        return;
    }
    $ip = implode(',', array_map('intval', $idPengajuan));
    foreach (['tbl_jawaban_vendor', 'tbl_catatan_tdr', 'tbl_penetapan_klasifikasi', 'tbl_rekomendasi_klasifikasi', 'tbl_hasil_verifikasi',
              'tbl_jadwal_verifikasi', 'tbl_dokumen_vendor', 'tbl_riwayat_status', 'tbl_log_aktivitas'] as $t) {
        $pdo->exec("DELETE FROM {$t} WHERE id_pengajuan IN ({$ip})");
    }
    $pdo->exec("DELETE FROM tbl_pengajuan_vendor WHERE id_pengajuan IN ({$ip})");
    foreach ($idPengajuan as $id) {
        $dir = BASE_PATH . '/storage/uploads/' . (int) $id;
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}

function bersihkan(PDO $pdo): void
{
    $vendor = $pdo->query(
        "SELECT v.id_vendor FROM tbl_vendor v LEFT JOIN tbl_akun_vendor a ON a.id_akun_vendor = v.id_akun_vendor
          WHERE a.email LIKE '%@uji.test' OR v.nib LIKE 'UJI6-%' OR v.nama_perusahaan LIKE 'PT Uji Manual%'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($vendor) {
        $iv = implode(',', array_map('intval', $vendor));
        $peng = $pdo->query("SELECT id_pengajuan FROM tbl_pengajuan_vendor WHERE id_vendor IN ({$iv})")->fetchAll(PDO::FETCH_COLUMN);
        hapusPengajuan($pdo, $peng);
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
    // pulihkan konfigurasi yang diubah uji
    $pdo->exec("DELETE FROM tbl_aturan_klasifikasi WHERE label_klasifikasi LIKE 'Level Uji%'");
    $pdo->exec("UPDATE tbl_aturan_klasifikasi SET aktif = 1 WHERE label_klasifikasi = 'Level C (simulasi)'");
    $pdo->exec("DELETE FROM tbl_syarat_dokumen WHERE kode_syarat LIKE 'uji_%'");
    $pdo->exec("DELETE FROM tbl_field_formulir WHERE kode_field LIKE 'uji_%'");
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi='login_gagal' AND JSON_UNQUOTE(JSON_EXTRACT(detail,'$.identitas')) LIKE '%demo\\_%'");
}

function berkas(string $isi, string $nama): CURLFile
{
    $dir = sys_get_temp_dir() . '/vmp_uji6';
    @mkdir($dir);
    file_put_contents($dir . '/' . $nama, $isi);
    return new CURLFile($dir . '/' . $nama, 'application/octet-stream', $nama);
}

function detail(Klien $k, int $id): array
{
    return $k->minta('staf/pengajuan/detail', 'GET', [], '&id=' . $id);
}

$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

/** Vendor mendaftar, mengisi, mengunggah, dan mengirim pengajuan. @return array{0:Klien,1:int} */
function ajukan(string $dasar, PDO $pdo, string $email, string $nama, string $nib, string $pdf): array
{
    $v = new Klien($dasar);
    $v->kirim('daftar-vendor/proses', [
        'nama_perusahaan' => $nama, 'nama_kontak' => 'Kontak Uji', 'email' => $email,
        'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
    ], 'daftar-vendor');
    $v->masukVendor($email, SANDI_VENDOR);
    $v->kirim('vendor/formulir/simpan', [
        'langkah' => '1', 'nama_perusahaan' => $nama, 'bentuk_badan_usaha' => 'PT', 'nib' => $nib,
        'bidang_usaha' => 'Pelatihan dan sertifikasi', 'alamat' => 'Jl. Uji Enam No. 6, Surabaya',
        'nama_penanggung_jawab' => 'Penanggung Uji', 'email_perusahaan' => 'kantor-' . $email, 'no_telepon' => '031-6660' . random_int(100, 999),
    ], 'vendor/formulir');
    $syarat = $pdo->query('SELECT id_syarat, kode_syarat FROM tbl_syarat_dokumen WHERE aktif=1 AND wajib=1 AND kode_syarat NOT LIKE "uji\_%"')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($syarat as $idS => $kode) {
        $v->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $idS, 'berkas' => berkas($pdf, "{$kode}.pdf")], 'vendor/formulir', '&langkah=3');
    }
    jawabSemuaPertanyaan($v, $pdo);
    $v->kirim('vendor/kirim', [], 'vendor/dashboard');
    $id = (int) $pdo->query(
        "SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor
          JOIN tbl_akun_vendor a ON a.id_akun_vendor=v.id_akun_vendor WHERE a.email=" . $pdo->quote($email)
    )->fetchColumn();
    return [$v, $id];
}

bersihkan($pdo);

$ver = new Klien($dasar);  $ver->masukStaf('demo_ver1', SANDI);
$plan = new Klien($dasar); $plan->masukStaf('demo_plan', SANDI);
$auth = new Klien($dasar); $auth->masukStaf('demo_auth', SANDI);
$adm = new Klien($dasar);  $adm->masukStaf('demo_admin', SANDI);

echo "== 1. Persiapan alur sampai terjadwal ==\n";
[$vend, $idP] = ajukan($dasar, $pdo, 'enam@uji.test', 'PT Uji Enam Makmur', 'UJI6-0001', $PDF);
$stat = static fn (int $id) => $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$id}")->fetchColumn();
cek('pengajuan terkirim', $stat($idP) === 'diajukan');

$ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
foreach ($pdo->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$idP}")->fetchAll(PDO::FETCH_COLUMN) as $idD) {
    $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $idP, 'id_dokumen' => (string) $idD, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
}
$ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
$plan->kirim('staf/jadwal/simpan', [
    'id_pengajuan' => (string) $idP, 'tanggal_verifikasi' => date('Y-m-d\TH:i', strtotime('+2 days 09:00')),
    'metode' => 'daring', 'lokasi' => '', 'id_verifikator' => '902', 'catatan' => '',
], 'staf/dashboard');
cek('pengajuan terjadwal', $stat($idP) === 'terjadwal');

echo "== 2. Hasil verifikasi dan rekomendasi ==\n";
$hasil = static fn (array $o = []) => array_merge([
    'id_pengajuan' => (string) $idP, 'skor_contoh_1' => '90', 'skor_contoh_2' => '85', 'skor_contoh_3' => '80',
    'kesimpulan' => 'memenuhi', 'temuan' => 'Lokasi usaha sesuai dengan data pendaftaran.',
], $o);
cek('petugas berwenang tidak boleh mencatat hasil (403)', $auth->kirim('staf/hasil/simpan', $hasil(), 'staf/dashboard')['kode'] === 403);
cek('perencanaan tidak boleh mencatat hasil (403)', $plan->kirim('staf/hasil/simpan', $hasil(), 'staf/dashboard')['kode'] === 403);
foreach ([
    'nilai 101'        => ['skor_contoh_1' => '101'],
    'nilai negatif'    => ['skor_contoh_2' => '-5'],
    'nilai bukan angka' => ['skor_contoh_3' => 'tinggi'],
    'nilai kosong'     => ['skor_contoh_1' => ''],
    'kesimpulan ngawur' => ['kesimpulan' => 'bagus'],
    'temuan kosong'    => ['temuan' => ''],
] as $nama => $ubah) {
    $ver->kirim('staf/hasil/simpan', $hasil($ubah), 'staf/dashboard');
    cek("hasil ditolak: {$nama}", $stat($idP) === 'terjadwal');
}
$ver->kirim('staf/hasil/simpan', $hasil(), 'staf/dashboard');
cek('hasil valid: status ada_rekomendasi', $stat($idP) === 'ada_rekomendasi');
$rek = $pdo->query("SELECT * FROM tbl_rekomendasi_klasifikasi WHERE id_pengajuan={$idP}")->fetch();
// berkas 85 (3 wajib sesuai, tanpa opsional) x40 + pertanyaan 100 x40 + verifikasi 85 x20 = 91
cek('rekomendasi otomatis: skor tertimbang 91 -> Level A (simulasi)', $rek && $rek['label_klasifikasi'] === 'Level A (simulasi)' && (float) $rek['skor_total'] === 91.0 && (int) $rek['simulasi'] === 1, (string) ($rek['skor_total'] ?? ''));
$jd = $pdo->query("SELECT status_jadwal FROM tbl_jadwal_verifikasi WHERE id_pengajuan={$idP}")->fetchColumn();
cek('jadwal ditandai selesai', $jd === 'selesai');
$ver->kirim('staf/hasil/simpan', $hasil(['skor_contoh_1' => '10']), 'staf/dashboard');
$nHasil = (int) $pdo->query("SELECT COUNT(*) FROM tbl_hasil_verifikasi WHERE id_pengajuan={$idP}")->fetchColumn();
cek('hasil tidak dapat dicatat dua kali', $nHasil === 1);
$p = detail($plan, $idP);
cek('perencanaan tidak melihat rekomendasi/hasil', $p['kode'] === 200 && !str_contains($p['isi'], 'Rekomendasi sistem'));
$v = detail($ver, $idP);
cek('verifikator melihat rekomendasi berlabel SIMULASI', str_contains($v['isi'], 'Rekomendasi sistem') && str_contains($v['isi'], 'SIMULASI'));

echo "== 3. Aturan rekomendasi (batas nilai) ==\n";
$svc = new KlasifikasiService();
$kasus = [[100.0, 'Level A (simulasi)'], [80.0, 'Level A (simulasi)'], [79.99, 'Level B (simulasi)'],
          [60.0, 'Level B (simulasi)'], [59.99, 'Level C (simulasi)'], [0.0, 'Level C (simulasi)']];
foreach ($kasus as [$skor, $harap]) {
    cek("skor {$skor} -> {$harap}", $svc->cocokkanAturan($skor)['label'] === $harap);
}
cek('rata-rata tiga nilai dibulatkan 2 desimal', KlasifikasiService::skorTotal(80, 81, 81) === 80.67);

echo "== 4. Penetapan klasifikasi ==\n";
$tp = static fn (array $o = []) => array_merge(['id_pengajuan' => (string) $idP, 'label_klasifikasi' => 'Level A (simulasi)', 'alasan' => ''], $o);
cek('verifikator tidak boleh menetapkan (403)', $ver->kirim('staf/penetapan/simpan', $tp(), 'staf/dashboard')['kode'] === 403);
cek('admin tidak boleh menetapkan (403)', $adm->kirim('staf/penetapan/simpan', $tp(), 'staf/dashboard')['kode'] === 403);
$auth->kirim('staf/penetapan/simpan', $tp(['label_klasifikasi' => 'Level B (simulasi)']), 'staf/dashboard');
cek('berbeda dari rekomendasi tanpa alasan ditolak', $stat($idP) === 'ada_rekomendasi');
$auth->kirim('staf/penetapan/simpan', $tp(['label_klasifikasi' => 'Kategori Karangan', 'alasan' => 'alasan yang cukup panjang']), 'staf/dashboard');
cek('label di luar aturan aktif ditolak', $stat($idP) === 'ada_rekomendasi');
$auth->kirim('staf/penetapan/simpan', $tp(), 'staf/dashboard');
cek('penetapan sesuai rekomendasi: status terklasifikasi', $stat($idP) === 'terklasifikasi');
$pen = $pdo->query("SELECT * FROM tbl_penetapan_klasifikasi WHERE id_pengajuan={$idP}")->fetchAll();
cek('penetapan tercatat (simulasi, oleh petugas berwenang)', count($pen) === 1 && (int) $pen[0]['simulasi'] === 1 && (int) $pen[0]['ditetapkan_oleh'] === 904 && (int) $pen[0]['berbeda_dari_rekomendasi'] === 0);

$auth->kirim('staf/penetapan/simpan', $tp(['label_klasifikasi' => 'Level B (simulasi)']), 'staf/dashboard');
cek('koreksi tanpa alasan ditolak', (int) $pdo->query("SELECT COUNT(*) FROM tbl_penetapan_klasifikasi WHERE id_pengajuan={$idP}")->fetchColumn() === 1);
$auth->kirim('staf/penetapan/simpan', $tp(['label_klasifikasi' => 'Level B (simulasi)', 'alasan' => 'Hasil kunjungan menunjukkan kapasitas belum penuh.']), 'staf/dashboard');
$pen = $pdo->query("SELECT * FROM tbl_penetapan_klasifikasi WHERE id_pengajuan={$idP} ORDER BY id_penetapan")->fetchAll();
cek('koreksi menambah riwayat (baris lama tetap)', count($pen) === 2 && $pen[0]['label_klasifikasi'] === 'Level A (simulasi)' && $pen[1]['label_klasifikasi'] === 'Level B (simulasi)');
cek('koreksi mencatat alasan dan penanda berbeda', (int) $pen[1]['berbeda_dari_rekomendasi'] === 1 && str_contains((string) $pen[1]['alasan_perubahan'], 'kapasitas'));
cek('status tetap terklasifikasi setelah koreksi', $stat($idP) === 'terklasifikasi');
$v = detail($auth, $idP);
cek('halaman menampilkan riwayat penetapan dan penanda SIMULASI', str_contains($v['isi'], 'digantikan') && str_contains($v['isi'], 'SIMULASI'));

echo "== 5. Catatan TDR (simulasi) ==\n";
$tdr = static fn (array $o = []) => array_merge([
    'id_pengajuan' => (string) $idP, 'tanggal_proses' => date('Y-m-d'), 'keterangan' => 'Draf TDR disiapkan untuk simulasi.',
], $o);
cek('verifikator tidak boleh mencatat TDR (403)', $ver->kirim('staf/tdr/simpan', $tdr(), 'staf/dashboard')['kode'] === 403);
cek('perencanaan tidak boleh mencatat TDR (403)', $plan->kirim('staf/tdr/simpan', $tdr(), 'staf/dashboard')['kode'] === 403);
$auth->kirim('staf/tdr/simpan', $tdr(['tanggal_proses' => date('Y-m-d', strtotime('+5 days'))]), 'staf/dashboard');
cek('tanggal TDR di masa depan ditolak', $stat($idP) === 'terklasifikasi');
$auth->kirim('staf/tdr/simpan', $tdr(['tanggal_proses' => '31-12-2026']), 'staf/dashboard');
cek('format tanggal TDR salah ditolak', $stat($idP) === 'terklasifikasi');
$auth->kirim('staf/tdr/simpan', $tdr(['keterangan' => 'x']), 'staf/dashboard');
cek('keterangan TDR terlalu pendek ditolak', $stat($idP) === 'terklasifikasi');
$auth->kirim('staf/tdr/simpan', $tdr(), 'staf/dashboard');
cek('TDR valid: status tdr_tercatat', $stat($idP) === 'tdr_tercatat');
$t = $pdo->query("SELECT * FROM tbl_catatan_tdr WHERE id_pengajuan={$idP}")->fetch();
cek('catatan TDR bertanda simulasi', $t && (int) $t['simulasi'] === 1);
cek('halaman menyatakan bukan dokumen TDR resmi', str_contains(detail($auth, $idP)['isi'], 'bukan dokumen TDR resmi'));
$auth->kirim('staf/penetapan/simpan', $tp(['label_klasifikasi' => 'Level C (simulasi)', 'alasan' => 'alasan yang cukup panjang']), 'staf/dashboard');
cek('penetapan tidak dapat diubah setelah TDR dicatat', (int) $pdo->query("SELECT COUNT(*) FROM tbl_penetapan_klasifikasi WHERE id_pengajuan={$idP}")->fetchColumn() === 2);

$ant = $auth->minta('staf/penetapan');
cek('antrean penetapan tampil tanpa galat PHP dan memuat pengajuan',
    $ant['kode'] === 200 && !str_contains($ant['isi'], 'Fatal error') && str_contains($ant['isi'], 'PT Uji Enam Makmur'));

echo "== 6. Selesai dan arsip ==\n";
cek('verifikator tidak boleh menyelesaikan (403)', $ver->kirim('staf/pengajuan/selesaikan', ['id_pengajuan' => (string) $idP], 'staf/dashboard')['kode'] === 403);
$auth->kirim('staf/pengajuan/arsipkan', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
cek('mengarsipkan sebelum selesai ditolak', $stat($idP) === 'tdr_tercatat');
$auth->kirim('staf/pengajuan/selesaikan', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
cek('diselesaikan', $stat($idP) === 'selesai');
$arsipSebelum = $ver->minta('staf/arsip');
cek('pengajuan selesai tampil di halaman arsip', str_contains($arsipSebelum['isi'], 'PT Uji Enam Makmur'));
$auth->kirim('staf/pengajuan/arsipkan', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
$row = $pdo->query("SELECT status_pengajuan, diarsipkan_pada, diarsipkan_oleh FROM tbl_pengajuan_vendor WHERE id_pengajuan={$idP}")->fetch();
cek('diarsipkan dengan waktu dan petugas', $row['status_pengajuan'] === 'diarsipkan' && $row['diarsipkan_pada'] !== null && (int) $row['diarsipkan_oleh'] === 904);
$auth->kirim('staf/pengajuan/arsipkan', ['id_pengajuan' => (string) $idP], 'staf/dashboard');
cek('arsip ganda tidak mengubah apa pun', $stat($idP) === 'diarsipkan');
cek('vendor melihat status Diarsipkan', str_contains($vend->minta('vendor/dashboard')['isi'], 'Diarsipkan'));
cek('arsip: pencarian tanpa hasil', str_contains($ver->minta('staf/arsip', 'GET', [], '&q=tidak-ada-zzz')['isi'], '0 pengajuan'));
$riw = (int) $pdo->query("SELECT COUNT(*) FROM tbl_riwayat_status WHERE id_pengajuan={$idP}")->fetchColumn();
cek('riwayat status lengkap (>= 11 perpindahan)', $riw >= 11, (string) $riw);

echo "== 7. Pencatatan manual dan duplikat ==\n";
$man = [
    'nama_perusahaan' => 'PT Uji Manual Sejahtera', 'bentuk_badan_usaha' => 'PT', 'nib' => 'UJI6-M001',
    'bidang_usaha' => 'Seragam / desain', 'alamat' => 'Jl. Manual Uji No. 7, Medan',
    'nama_penanggung_jawab' => 'Pak Manual', 'email_perusahaan' => 'manual@uji6.test', 'no_telepon' => '061-7771234',
];
cek('perencanaan tidak boleh mencatat manual (403)', $plan->minta('staf/manual')['kode'] === 403);
cek('vendor tidak boleh mencatat manual (403)', $vend->minta('staf/manual')['kode'] === 403);
cek('verifikator membuka form manual', $ver->minta('staf/manual')['kode'] === 200);
$jmlVendor = static fn () => (int) $pdo->query("SELECT COUNT(*) FROM tbl_vendor WHERE nama_perusahaan LIKE 'PT Uji Manual%'")->fetchColumn();

$ver->kirim('staf/manual/simpan', array_merge($man, ['email_perusahaan' => 'salah']), 'staf/manual');
cek('data manual tidak valid ditolak', $jmlVendor() === 0);
$r = $ver->kirim('staf/manual/simpan', $man, 'staf/manual');
$m = $pdo->query("SELECT p.*, v.id_akun_vendor FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor WHERE v.nib='UJI6-M001'")->fetch();
cek('pengajuan manual dibuat (sumber proses_manual, status diajukan)', $m && $m['sumber_pengajuan'] === 'proses_manual' && $m['status_pengajuan'] === 'diajukan' && $m['id_akun_vendor'] === null);
cek('pengajuan manual mencatat petugas dan nomor', (int) $m['dicatat_oleh'] === 902 && str_starts_with($m['nomor_pengajuan'], 'VM-'));
$idM = (int) $m['id_pengajuan'];
cek('diarahkan ke detail pengajuan manual', str_contains($r['lokasi'], 'staf/pengajuan/detail'));

// duplikat: nama sama (variasi penulisan), NIB berbeda
$dupNama = array_merge($man, ['nama_perusahaan' => 'UJI MANUAL SEJAHTERA, PT.', 'nib' => 'UJI6-M002', 'email_perusahaan' => 'lain@uji6.test', 'no_telepon' => '061-0000000']);
$ver->kirim('staf/manual/simpan', $dupNama, 'staf/manual');
$hal = $ver->minta('staf/manual');
cek('nama mirip terdeteksi duplikat dan belum disimpan', str_contains($hal['isi'], 'Kemungkinan data vendor ganda') && (int) $pdo->query("SELECT COUNT(*) FROM tbl_vendor WHERE nib='UJI6-M002'")->fetchColumn() === 0);
$ver->kirim('staf/manual/simpan', $dupNama + ['konfirmasi_duplikat' => '1', 'alasan_duplikat' => ''], 'staf/manual');
cek('konfirmasi duplikat tanpa alasan ditolak', (int) $pdo->query("SELECT COUNT(*) FROM tbl_vendor WHERE nib='UJI6-M002'")->fetchColumn() === 0);
$ver->kirim('staf/manual/simpan', $dupNama + ['konfirmasi_duplikat' => '1', 'alasan_duplikat' => 'Cabang berbeda dengan kantor pusat.'], 'staf/manual');
$idM2 = (int) $pdo->query("SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor WHERE v.nib='UJI6-M002'")->fetchColumn();
cek('duplikat dengan konfirmasi dan alasan tersimpan', $idM2 > 0);
$rw = $pdo->query("SELECT catatan FROM tbl_riwayat_status WHERE id_pengajuan={$idM2}")->fetchColumn();
cek('alasan duplikat tercatat di riwayat', str_contains((string) $rw, 'Cabang berbeda'));
cek('detail menampilkan kemungkinan duplikat', str_contains(detail($ver, $idM2)['isi'], 'Kemungkinan data vendor ganda'));

// NIB sama: diblokir keras
$ver->kirim('staf/manual/simpan', array_merge($man, ['nama_perusahaan' => 'PT Uji Manual Lain', 'konfirmasi_duplikat' => '1', 'alasan_duplikat' => 'Tetap simpan walau NIB sama.']), 'staf/manual');
cek('NIB yang sama tidak dapat disimpan walau dikonfirmasi', (int) $pdo->query("SELECT COUNT(*) FROM tbl_vendor WHERE nib='UJI6-M001'")->fetchColumn() === 1);
// duplikat terhadap vendor portal (email/telepon)
$ver->kirim('staf/manual/simpan', array_merge($man, [
    'nama_perusahaan' => 'PT Uji Manual Beda', 'nib' => 'UJI6-M003', 'email_perusahaan' => 'kantor-enam@uji.test', 'no_telepon' => '000-111222',
]), 'staf/manual');
cek('email sama dengan vendor portal terdeteksi', str_contains($ver->minta('staf/manual')['isi'], 'Email perusahaan sama'));

// unggah dokumen oleh petugas
$syaratWajib = $pdo->query('SELECT id_syarat FROM tbl_syarat_dokumen WHERE aktif=1 AND wajib=1 AND kode_syarat NOT LIKE "uji\_%"')->fetchAll(PDO::FETCH_COLUMN);
$unggahStaf = static fn (Klien $k, int $idP2, int $idS, string $nama = 'm.pdf') => $k->kirim(
    'staf/dokumen/unggah', ['id_pengajuan' => (string) $idP2, 'id_syarat' => (string) $idS, 'berkas' => berkas($GLOBALS['PDF'], $nama)], 'staf/dashboard'
);
cek('perencanaan tidak boleh mengunggah dokumen manual (403)', $unggahStaf($plan, $idM, (int) $syaratWajib[0])['kode'] === 403);
cek('vendor tidak boleh memakai rute unggah petugas (403)', $unggahStaf($vend, $idM, (int) $syaratWajib[0])['kode'] === 403);
$unggahStaf($ver, $idM, (int) $syaratWajib[0]);
$nDok = static fn (int $id) => (int) $pdo->query("SELECT COUNT(*) FROM tbl_dokumen_vendor WHERE id_pengajuan={$id}")->fetchColumn();
cek('petugas mengunggah dokumen untuk pengajuan manual', $nDok($idM) === 1);
// pengajuan portal (sudah diarsipkan) tidak boleh
$unggahStaf($ver, $idP, (int) $syaratWajib[0]);
cek('tidak boleh mengunggah untuk pengajuan portal', $nDok($idP) === 3);
foreach (array_slice($syaratWajib, 1) as $idS) {
    $unggahStaf($ver, $idM, (int) $idS);
}
cek('seluruh dokumen wajib manual terunggah', $nDok($idM) === count($syaratWajib));
$ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $idM], 'staf/dashboard');
cek('pengajuan manual dapat diperiksa seperti biasa', $stat($idM) === 'sedang_diperiksa');
cek('filter sumber proses_manual menampilkan pengajuan manual', str_contains($ver->minta('staf/pengajuan', 'GET', [], '&sumber=proses_manual')['isi'], 'PT Uji Manual Sejahtera'));
cek('filter sumber portal tidak menampilkan pengajuan manual', !str_contains($ver->minta('staf/pengajuan', 'GET', [], '&sumber=portal_vendor_management')['isi'], 'PT Uji Manual Sejahtera'));

echo "== 8. Konfigurasi administrator ==\n";
cek('verifikator tidak boleh membuka konfigurasi (403)', $ver->minta('admin/konfigurasi', 'GET', [], '&jenis=aturan')['kode'] === 403);
foreach (['aturan', 'syarat', 'field'] as $j) {
    cek("admin membuka konfigurasi {$j}", $adm->minta('admin/konfigurasi', 'GET', [], "&jenis={$j}")['kode'] === 200);
}
cek('jenis konfigurasi tidak dikenal -> 404', $adm->minta('admin/konfigurasi', 'GET', [], '&jenis=ngawur')['kode'] === 404);
cek('verifikator tidak boleh menyimpan konfigurasi (403)', $ver->kirim('admin/konfigurasi/simpan', ['jenis' => 'aturan'], 'staf/dashboard')['kode'] === 403);

$aturanBaru = static fn (array $o = []) => array_merge([
    'jenis' => 'aturan', 'label_klasifikasi' => 'Level Uji', 'skor_min' => '0', 'skor_maks' => '59.99', 'urutan' => '9', 'aktif' => '1', 'contoh_sementara' => '1',
], $o);
$nAturan = static fn () => (int) $pdo->query("SELECT COUNT(*) FROM tbl_aturan_klasifikasi WHERE label_klasifikasi LIKE 'Level Uji%'")->fetchColumn();
$adm->kirim('admin/konfigurasi/simpan', $aturanBaru(), 'admin/konfigurasi', '');
cek('rentang bertabrakan dengan aturan aktif ditolak', $nAturan() === 0);
$adm->kirim('admin/konfigurasi/simpan', $aturanBaru(['skor_min' => '70', 'skor_maks' => '60']), 'admin/konfigurasi');
cek('skor minimum > maksimum ditolak', $nAturan() === 0);
$adm->kirim('admin/konfigurasi/simpan', $aturanBaru(['skor_maks' => '150', 'aktif' => '0']), 'admin/konfigurasi');
cek('skor di luar 0-100 ditolak', $nAturan() === 0);
$adm->kirim('admin/konfigurasi/simpan', $aturanBaru(['aktif' => '0']), 'admin/konfigurasi');
cek('aturan nonaktif boleh ditambah', $nAturan() === 1);
$adm->kirim('admin/konfigurasi/simpan', $aturanBaru(['aktif' => '0']), 'admin/konfigurasi');
cek('label ganda ditolak', $nAturan() === 1);

// ganti aturan C dengan aturan uji, rekomendasi ikut berubah
$idC = (int) $pdo->query("SELECT id_aturan FROM tbl_aturan_klasifikasi WHERE label_klasifikasi='Level C (simulasi)'")->fetchColumn();
$adm->kirim('admin/konfigurasi/simpan', [
    'jenis' => 'aturan', 'id_aturan' => (string) $idC, 'label_klasifikasi' => 'Level C (simulasi)', 'skor_min' => '0', 'skor_maks' => '59.99',
    'urutan' => '3', 'aktif' => '0', 'contoh_sementara' => '1',
], 'admin/konfigurasi');
$idU = (int) $pdo->query("SELECT id_aturan FROM tbl_aturan_klasifikasi WHERE label_klasifikasi='Level Uji'")->fetchColumn();
$adm->kirim('admin/konfigurasi/simpan', $aturanBaru(['id_aturan' => (string) $idU, 'aktif' => '1']), 'admin/konfigurasi');
cek('aturan uji diaktifkan setelah C dinonaktifkan', (int) $pdo->query("SELECT aktif FROM tbl_aturan_klasifikasi WHERE id_aturan={$idU}")->fetchColumn() === 1);
cek('rekomendasi mengikuti konfigurasi: skor 30 -> Level Uji', (new KlasifikasiService())->cocokkanAturan(30.0)['label'] === 'Level Uji');

// persyaratan dokumen
$syaratBaru = static fn (array $o = []) => array_merge([
    'jenis' => 'syarat', 'kode_syarat' => 'uji_dok', 'nama_dokumen' => 'Dokumen Uji Tambahan', 'keterangan' => '',
    'format_diizinkan' => 'pdf', 'ukuran_maks_kb' => '500', 'urutan' => '99', 'wajib' => '1', 'aktif' => '1', 'contoh_sementara' => '1',
], $o);
$adm->kirim('admin/konfigurasi/simpan', $syaratBaru(['format_diizinkan' => 'exe']), 'admin/konfigurasi');
cek('format berbahaya (exe) ditolak pada konfigurasi', (int) $pdo->query("SELECT COUNT(*) FROM tbl_syarat_dokumen WHERE kode_syarat='uji_dok'")->fetchColumn() === 0);
$adm->kirim('admin/konfigurasi/simpan', $syaratBaru(['ukuran_maks_kb' => '99999']), 'admin/konfigurasi');
cek('ukuran melebihi batas ditolak', (int) $pdo->query("SELECT COUNT(*) FROM tbl_syarat_dokumen WHERE kode_syarat='uji_dok'")->fetchColumn() === 0);
$adm->kirim('admin/konfigurasi/simpan', $syaratBaru(['kode_syarat' => 'Kode Salah!']), 'admin/konfigurasi');
cek('kode tidak valid ditolak', (int) $pdo->query("SELECT COUNT(*) FROM tbl_syarat_dokumen WHERE kode_syarat LIKE 'Kode%'")->fetchColumn() === 0);
$adm->kirim('admin/konfigurasi/simpan', $syaratBaru(), 'admin/konfigurasi');
cek('persyaratan baru tersimpan', (int) $pdo->query("SELECT COUNT(*) FROM tbl_syarat_dokumen WHERE kode_syarat='uji_dok'")->fetchColumn() === 1);

[$v2, $idP2] = ajukan($dasar, $pdo, 'tujuh@uji.test', 'PT Uji Tujuh', 'UJI6-0007', $PDF);
cek('persyaratan wajib baru menghalangi pengiriman', $stat($idP2) === 'draf' && str_contains($v2->minta('vendor/dashboard')['isi'], 'Dokumen Uji Tambahan'));
$idUji = (int) $pdo->query("SELECT id_syarat FROM tbl_syarat_dokumen WHERE kode_syarat='uji_dok'")->fetchColumn();
$adm->kirim('admin/konfigurasi/simpan', $syaratBaru(['id_syarat' => (string) $idUji, 'aktif' => '0']), 'admin/konfigurasi');
$v2->kirim('vendor/kirim', [], 'vendor/dashboard');
cek('setelah persyaratan dinonaktifkan pengajuan dapat dikirim', $stat($idP2) === 'diajukan');

// field formulir
$adm->kirim('admin/konfigurasi/simpan', [
    'jenis' => 'field', 'kode_field' => 'uji_field', 'label' => 'Isian Uji Konfigurasi', 'tipe_input' => 'angka',
    'opsi_pilihan' => '', 'urutan' => '50', 'wajib' => '0', 'aktif' => '1', 'contoh_sementara' => '1',
], 'admin/konfigurasi');
$halForm = (new Klien($dasar));
$halForm->masukVendor('tujuh@uji.test', SANDI_VENDOR);
cek('field baru tampil di formulir vendor', str_contains($halForm->minta('vendor/formulir', 'GET', [], '&langkah=2')['isi'], 'Isian Uji Konfigurasi'));
$adm->kirim('admin/konfigurasi/simpan', [
    'jenis' => 'field', 'kode_field' => 'uji_pilihan', 'label' => 'Pilihan Uji', 'tipe_input' => 'pilihan',
    'opsi_pilihan' => 'hanya satu', 'urutan' => '51', 'aktif' => '1',
], 'admin/konfigurasi');
cek('tipe pilihan dengan satu opsi ditolak', (int) $pdo->query("SELECT COUNT(*) FROM tbl_field_formulir WHERE kode_field='uji_pilihan'")->fetchColumn() === 0);

echo "== 9. Audit log ==\n";
foreach (['hasil_verifikasi_dicatat', 'klasifikasi_ditetapkan', 'klasifikasi_dikoreksi', 'tdr_dicatat',
          'pengajuan_manual_dicatat', 'aturan_klasifikasi_disimpan', 'syarat_dokumen_disimpan', 'field_formulir_disimpan'] as $aksi) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE aksi='{$aksi}'")->fetchColumn();
    cek("audit log memuat {$aksi}", $n > 0);
}

bersihkan($pdo);
$sisa = (int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchColumn()
      + (int) $pdo->query("SELECT COUNT(*) FROM tbl_vendor WHERE nama_perusahaan LIKE 'PT Uji%'")->fetchColumn()
      + (int) $pdo->query("SELECT COUNT(*) FROM tbl_aturan_klasifikasi WHERE label_klasifikasi LIKE 'Level Uji%'")->fetchColumn()
      + (int) $pdo->query("SELECT COUNT(*) FROM tbl_syarat_dokumen WHERE kode_syarat LIKE 'uji_%'")->fetchColumn()
      + (int) $pdo->query("SELECT COUNT(*) FROM tbl_field_formulir WHERE kode_field LIKE 'uji_%'")->fetchColumn();
cek('data uji dan konfigurasi dipulihkan', $sisa === 0);
$c = (int) $pdo->query("SELECT aktif FROM tbl_aturan_klasifikasi WHERE label_klasifikasi='Level C (simulasi)'")->fetchColumn();
cek('aturan Level C aktif kembali', $c === 1);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
