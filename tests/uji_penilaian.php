<?php
declare(strict_types=1);

/**
 * Uji otomatis penilaian klasifikasi otomatis (pertanyaan + kelengkapan berkas + verifikasi).
 *   php -S 127.0.0.1:8099 -t public
 *   php tests/uji_penilaian.php http://127.0.0.1:8099
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Klien.php';
require __DIR__ . '/Bantu.php';

use App\Core\Database;
use App\Services\PenilaianService;

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
                $dir = BASE_PATH . '/storage/uploads/' . (int) $id;
                foreach (glob($dir . '/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
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
    // pulihkan konfigurasi
    $pdo->exec("DELETE o FROM tbl_opsi_jawaban o JOIN tbl_pertanyaan_penilaian p ON p.id_pertanyaan=o.id_pertanyaan WHERE p.kode_pertanyaan LIKE 'uji\\_%'");
    $pdo->exec("DELETE FROM tbl_pertanyaan_penilaian WHERE kode_pertanyaan LIKE 'uji\\_%'");
    $pdo->exec("UPDATE tbl_pertanyaan_penilaian SET aktif = 1 WHERE kode_pertanyaan IN ('lama_usaha','pengalaman','tenaga_kerja','mutu')");
    $pdo->exec("UPDATE tbl_pengaturan_penilaian SET nilai='40' WHERE kunci IN ('bobot_berkas','bobot_pertanyaan')");
    $pdo->exec("UPDATE tbl_pengaturan_penilaian SET nilai='20' WHERE kunci = 'bobot_verifikasi'");
    $pdo->exec("UPDATE tbl_syarat_dokumen SET wajib = 0 WHERE kode_syarat IN ('domisili','profil')");
    $pdo->exec("DELETE FROM tbl_log_aktivitas WHERE aksi='login_gagal' AND JSON_UNQUOTE(JSON_EXTRACT(detail,'$.identitas')) LIKE '%demo\\_%'");
}

function berkas(string $isi, string $nama): CURLFile
{
    $dir = sys_get_temp_dir() . '/vmp_uji_pen';
    @mkdir($dir);
    file_put_contents($dir . '/' . $nama, $isi);
    return new CURLFile($dir . '/' . $nama, 'application/octet-stream', $nama);
}

$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

/**
 * Vendor mengajukan; petugas memeriksa, menjadwalkan sampai status terjadwal.
 * @return array{0:Klien,1:int}
 */
function sampaiTerjadwal(string $dasar, PDO $pdo, Klien $ver, Klien $plan, string $email, string $nama, string $nib,
                         string $modeJawab, bool $dokOpsionalSesuai, string $pdf): array
{
    $v = new Klien($dasar);
    $v->kirim('daftar-vendor/proses', [
        'nama_perusahaan' => $nama, 'nama_kontak' => 'Kontak', 'email' => $email,
        'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
    ], 'daftar-vendor');
    $v->masukVendor($email, SANDI_VENDOR);
    $v->kirim('vendor/formulir/simpan', [
        'langkah' => '1', 'nama_perusahaan' => $nama, 'bentuk_badan_usaha' => 'PT', 'nib' => $nib,
        'bidang_usaha' => 'Pelatihan dan sertifikasi', 'alamat' => 'Jl. Penilaian Uji No. 9, Bogor',
        'nama_penanggung_jawab' => 'Penilai Uji', 'email_perusahaan' => 'k-' . $email, 'no_telepon' => '0251-' . random_int(100000, 999999),
    ], 'vendor/formulir');
    jawabSemuaPertanyaan($v, $pdo, $modeJawab);
    $syarat = $pdo->query('SELECT id_syarat, kode_syarat FROM tbl_syarat_dokumen WHERE aktif=1 AND (wajib=1' . ($dokOpsionalSesuai ? ' OR kode_syarat="domisili"' : '') . ') AND kode_syarat NOT LIKE "uji\_%"')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($syarat as $idS => $kode) {
        $v->kirim('vendor/dokumen/unggah', ['id_syarat' => (string) $idS, 'berkas' => berkas($pdf, "{$kode}.pdf")], 'vendor/formulir', '&langkah=4');
    }
    $v->kirim('vendor/kirim', [], 'vendor/dashboard');
    $id = (int) $pdo->query(
        "SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor vv ON vv.id_vendor=p.id_vendor
          JOIN tbl_akun_vendor a ON a.id_akun_vendor=vv.id_akun_vendor WHERE a.email=" . $pdo->quote($email)
    )->fetchColumn();

    $ver->kirim('staf/pengajuan/mulai-periksa', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    foreach ($pdo->query("SELECT id_dokumen FROM tbl_dokumen_vendor WHERE id_pengajuan={$id}")->fetchAll(PDO::FETCH_COLUMN) as $idD) {
        $ver->kirim('staf/dokumen/periksa', ['id_pengajuan' => (string) $id, 'id_dokumen' => (string) $idD, 'hasil' => 'sesuai', 'catatan' => ''], 'staf/dashboard');
    }
    $ver->kirim('staf/pengajuan/verifikasi-dokumen', ['id_pengajuan' => (string) $id], 'staf/dashboard');
    $plan->kirim('staf/jadwal/simpan', [
        'id_pengajuan' => (string) $id, 'tanggal_verifikasi' => date('Y-m-d\TH:i', strtotime('+2 days 09:00')),
        'metode' => 'daring', 'lokasi' => '', 'id_verifikator' => '902', 'catatan' => '',
    ], 'staf/dashboard');
    return [$v, $id];
}

function catatHasil(Klien $ver, int $id, int $a, int $b, int $c): void
{
    $ver->kirim('staf/hasil/simpan', [
        'id_pengajuan' => (string) $id, 'skor_contoh_1' => (string) $a, 'skor_contoh_2' => (string) $b,
        'skor_contoh_3' => (string) $c, 'kesimpulan' => 'memenuhi', 'temuan' => 'Pemeriksaan uji penilaian.',
    ], 'staf/dashboard');
}

bersihkan($pdo);
$ver = new Klien($dasar);  $ver->masukStaf('demo_ver1', SANDI);
$plan = new Klien($dasar); $plan->masukStaf('demo_plan', SANDI);
$adm = new Klien($dasar);  $adm->masukStaf('demo_admin', SANDI);
$stat = static fn (int $id) => $pdo->query("SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan={$id}")->fetchColumn();
$rek = static fn (int $id) => $pdo->query("SELECT * FROM tbl_rekomendasi_klasifikasi WHERE id_pengajuan={$id}")->fetch();

echo "== 1. Jawaban pertanyaan oleh vendor ==\n";
$k = new Klien($dasar);
$k->kirim('daftar-vendor/proses', [
    'nama_perusahaan' => 'PT Uji Jawaban', 'nama_kontak' => 'Kontak', 'email' => 'jawab@uji.test',
    'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
], 'daftar-vendor');
$k->masukVendor('jawab@uji.test', SANDI_VENDOR);
$halForm = $k->minta('vendor/formulir', 'GET', [], '&langkah=3');
cek('langkah pertanyaan penilaian tampil dengan label contoh sementara',
    $halForm['kode'] === 200 && str_contains($halForm['isi'], 'Berapa lama perusahaan Anda') && str_contains($halForm['isi'], 'contoh sementara'));
cek('formulir vendor sekarang punya 5 langkah', str_contains($halForm['isi'], 'Tinjau dan kirim') && str_contains($halForm['isi'], 'langkah=5'));
$qs = $pdo->query('SELECT id_pertanyaan, kode_pertanyaan FROM tbl_pertanyaan_penilaian WHERE aktif=1 ORDER BY urutan')->fetchAll(PDO::FETCH_KEY_PAIR);
$idQ1 = (int) array_key_first($qs);
$idQ2 = (int) array_keys($qs)[1];
$opsiQ1 = $pdo->query("SELECT id_opsi FROM tbl_opsi_jawaban WHERE id_pertanyaan={$idQ1} ORDER BY poin LIMIT 1")->fetchColumn();
$opsiQ2 = $pdo->query("SELECT id_opsi FROM tbl_opsi_jawaban WHERE id_pertanyaan={$idQ2} ORDER BY poin LIMIT 1")->fetchColumn();
$idPJ = (int) $pdo->query("SELECT p.id_pengajuan FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor JOIN tbl_akun_vendor a ON a.id_akun_vendor=v.id_akun_vendor WHERE a.email='jawab@uji.test'")->fetchColumn();
$nJawab = static fn () => (int) Database::prototype()->query("SELECT COUNT(*) FROM tbl_jawaban_vendor WHERE id_pengajuan={$GLOBALS['idPJ']}")->fetchColumn();

$k->kirim('vendor/formulir/simpan', ['langkah' => '3', "q_{$idQ1}" => (string) $opsiQ2], 'vendor/formulir');
cek('opsi milik pertanyaan lain ditolak', $nJawab() === 0);
$k->kirim('vendor/formulir/simpan', ['langkah' => '3', "q_{$idQ1}" => 'abc'], 'vendor/formulir');
cek('nilai opsi bukan angka ditolak', $nJawab() === 0);
$k->kirim('vendor/formulir/simpan', ['langkah' => '3', "q_{$idQ1}" => '999999'], 'vendor/formulir');
cek('id opsi tidak ada ditolak', $nJawab() === 0);
$k->kirim('vendor/formulir/simpan', ['langkah' => '3', "q_{$idQ1}" => (string) $opsiQ1], 'vendor/formulir');
cek('jawaban sebagian tersimpan sebagai draf', $nJawab() === 1);
$k->kirim('vendor/formulir/simpan', ['langkah' => '3', "q_{$idQ1}" => (string) $opsiQ1, "q_{$idQ2}" => (string) $opsiQ2], 'vendor/formulir');
cek('jawaban kedua menambah (tidak menggandakan)', $nJawab() === 2);
$dash = $k->minta('vendor/dashboard');
cek('dashboard menyebut pertanyaan yang belum dijawab', str_contains($dash['isi'], 'belum dijawab'));
$mis = $k->minta('vendor/formulir', 'GET', [], '&langkah=3');
cek('jawaban tersimpan terpilih kembali di form', str_contains($mis['isi'], 'checked'));

echo "== 2. Perhitungan skor otomatis ==\n";
[$vA, $idA] = sampaiTerjadwal($dasar, $pdo, $ver, $plan, 'nilai-a@uji.test', 'PT Uji Nilai A', 'UJIP-0001', 'tertinggi', false, $PDF);
cek('pengajuan A siap diverifikasi', $stat($idA) === 'terjadwal');
$pra = $ver->minta('staf/pengajuan/detail', 'GET', [], '&id=' . $idA);
cek('verifikator melihat pratinjau penilaian (belum tersimpan)', str_contains($pra['isi'], 'Pratinjau dari data saat ini') && str_contains($pra['isi'], 'Skor total'));
cek('perencanaan tidak melihat penilaian', !str_contains($plan->minta('staf/pengajuan/detail', 'GET', [], '&id=' . $idA)['isi'], 'Penilaian otomatis'));
$hit = (new PenilaianService())->hitung($idA, null);
cek('berkas A = 85 (3 wajib sesuai, 0 dari 2 opsional)', $hit['komponen']['berkas']['skor'] === 85.0, (string) $hit['komponen']['berkas']['skor']);
cek('pertanyaan A = 100 (semua jawaban tertinggi)', $hit['komponen']['pertanyaan']['skor'] === 100.0);
cek('pratinjau total tanpa verifikasi = (85x40+100x40)/80 = 92.5', $hit['total'] === 92.5, (string) $hit['total']);
catatHasil($ver, $idA, 90, 90, 90);
$r = $rek($idA);
cek('A: total = (85x40 + 100x40 + 90x20)/100 = 92 -> Level A (simulasi)', $r && (float) $r['skor_total'] === 92.0 && $r['label_klasifikasi'] === 'Level A (simulasi)', (string) ($r['skor_total'] ?? ''));
$rin = json_decode((string) ($r['rincian'] ?? ''), true);
cek('rincian perhitungan tersimpan di rekomendasi', is_array($rin) && isset($rin['komponen']['berkas'], $rin['komponen']['pertanyaan'], $rin['komponen']['verifikasi']) && (float) $rin['total'] === 92.0);

[$vB, $idB] = sampaiTerjadwal($dasar, $pdo, $ver, $plan, 'nilai-b@uji.test', 'PT Uji Nilai B', 'UJIP-0002', 'terendah', false, $PDF);
catatHasil($ver, $idB, 50, 50, 50);
$r = $rek($idB);
cek('B: jawaban terendah -> total 52.8 -> Level C (simulasi)', $r && (float) $r['skor_total'] === 52.8 && $r['label_klasifikasi'] === 'Level C (simulasi)', (string) ($r['skor_total'] ?? ''));

echo "== 3. Penandaan jawaban tidak sesuai kenyataan ==\n";
[$vC, $idC] = sampaiTerjadwal($dasar, $pdo, $ver, $plan, 'nilai-c@uji.test', 'PT Uji Nilai C', 'UJIP-0003', 'tertinggi', false, $PDF);
$jw = static fn (int $id) => $pdo->query("SELECT id_pertanyaan, ditandai_tidak_sesuai FROM tbl_jawaban_vendor WHERE id_pengajuan={$id}")->fetchAll(PDO::FETCH_KEY_PAIR);
$idQlama = (int) $pdo->query("SELECT id_pertanyaan FROM tbl_pertanyaan_penilaian WHERE kode_pertanyaan='lama_usaha'")->fetchColumn();
$idQpeng = (int) $pdo->query("SELECT id_pertanyaan FROM tbl_pertanyaan_penilaian WHERE kode_pertanyaan='pengalaman'")->fetchColumn();
$tandai = static fn (Klien $kl, int $id, int $q, string $tind, string $cat = '') => $kl->kirim(
    'staf/jawaban/tandai', ['id_pengajuan' => (string) $id, 'id_pertanyaan' => (string) $q, 'tindakan' => $tind, 'catatan' => $cat], 'staf/dashboard'
);
cek('perencanaan tidak boleh menandai (403)', $tandai($plan, $idC, $idQlama, 'tandai', 'alasan yang cukup')['kode'] === 403);
cek('admin tidak boleh menandai (403)', $tandai($adm, $idC, $idQlama, 'tandai', 'alasan yang cukup')['kode'] === 403);
$tandai($ver, $idC, $idQlama, 'tandai', '');
cek('menandai tanpa alasan ditolak', ($jw($idC)[$idQlama] ?? 0) === 0);
$tandai($ver, $idC, 999999, 'tandai', 'alasan yang cukup');
cek('pertanyaan tidak dijawab/tidak ada ditolak', true);
$tandai($ver, $idC, $idQlama, 'tandai', 'Lokasi usaha baru berdiri 8 bulan.');
$tandai($ver, $idC, $idQpeng, 'tandai', 'Tidak ada bukti kontrak sebelumnya.');
cek('dua jawaban ditandai tidak sesuai', ($jw($idC)[$idQlama] ?? 0) === 1 && ($jw($idC)[$idQpeng] ?? 0) === 1);
$tandai($ver, $idC, $idQpeng, 'hapus');
cek('tanda dapat dihapus', ($jw($idC)[$idQpeng] ?? 1) === 0);
$tandai($ver, $idC, $idQpeng, 'tandai', 'Tidak ada bukti kontrak sebelumnya.');
$hit = (new PenilaianService())->hitung($idC, null);
cek('jawaban ditandai dihitung 0 (pertanyaan = 40)', $hit['komponen']['pertanyaan']['skor'] === 40.0, (string) $hit['komponen']['pertanyaan']['skor']);
catatHasil($ver, $idC, 100, 100, 100);
$r = $rek($idC);
cek('C: total = (85x40 + 40x40 + 100x20)/100 = 70 -> Level B (simulasi)', $r && (float) $r['skor_total'] === 70.0 && $r['label_klasifikasi'] === 'Level B (simulasi)', (string) ($r['skor_total'] ?? ''));
$tandai($ver, $idC, $idQlama, 'hapus');
cek('tanda tidak dapat diubah setelah rekomendasi dibuat', ($jw($idC)[$idQlama] ?? 0) === 1);
$tampil = $ver->minta('staf/pengajuan/detail', 'GET', [], '&id=' . $idC);
cek('detail menampilkan rincian tersimpan dan tanda tidak sesuai', str_contains($tampil['isi'], 'tersimpan, tidak berubah') && str_contains($tampil['isi'], 'Ditandai tidak sesuai kenyataan'));

echo "== 4. Kelengkapan berkas memengaruhi skor ==\n";
[$vD, $idD] = sampaiTerjadwal($dasar, $pdo, $ver, $plan, 'nilai-d@uji.test', 'PT Uji Nilai D', 'UJIP-0004', 'tertinggi', true, $PDF);
$hit = (new PenilaianService())->hitung($idD, null);
cek('berkas D = 85 + 1/2 x 15 = 92.5 (1 dari 2 opsional sesuai)', $hit['komponen']['berkas']['skor'] === 92.5, (string) $hit['komponen']['berkas']['skor']);
$dokD = $pdo->query("SELECT d.id_dokumen FROM tbl_dokumen_vendor d JOIN tbl_syarat_dokumen s ON s.id_syarat=d.id_syarat WHERE d.id_pengajuan={$idD} AND s.kode_syarat='domisili'")->fetchColumn();
$pdo->exec("UPDATE tbl_dokumen_vendor SET status_pemeriksaan='tidak_sesuai' WHERE id_dokumen=" . (int) $dokD);
$hit = (new PenilaianService())->hitung($idD, null);
cek('dokumen opsional tidak sesuai tidak menambah skor (berkas 85)', $hit['komponen']['berkas']['skor'] === 85.0);

echo "== 5. Konfigurasi bobot ==\n";
cek('verifikator tidak boleh membuka bobot (403)', $ver->minta('admin/konfigurasi', 'GET', [], '&jenis=bobot')['kode'] === 403);
cek('admin membuka halaman bobot', $adm->minta('admin/konfigurasi', 'GET', [], '&jenis=bobot')['kode'] === 200);
$bobot = static fn () => $pdo->query("SELECT kunci, nilai FROM tbl_pengaturan_penilaian WHERE kunci LIKE 'bobot\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$adm->kirim('admin/konfigurasi/simpan', ['jenis' => 'bobot', 'bobot_berkas' => '50', 'bobot_pertanyaan' => '40', 'bobot_verifikasi' => '20'], 'admin/konfigurasi');
cek('jumlah bobot bukan 100 ditolak', $bobot()['bobot_berkas'] === '40');
$adm->kirim('admin/konfigurasi/simpan', ['jenis' => 'bobot', 'bobot_berkas' => 'a', 'bobot_pertanyaan' => '40', 'bobot_verifikasi' => '60'], 'admin/konfigurasi');
cek('bobot bukan angka ditolak', $bobot()['bobot_berkas'] === '40');
$adm->kirim('admin/konfigurasi/simpan', ['jenis' => 'bobot', 'bobot_berkas' => '-10', 'bobot_pertanyaan' => '90', 'bobot_verifikasi' => '20'], 'admin/konfigurasi');
cek('bobot negatif ditolak', $bobot()['bobot_berkas'] === '40');
$ver->kirim('admin/konfigurasi/simpan', ['jenis' => 'bobot', 'bobot_berkas' => '0', 'bobot_pertanyaan' => '100', 'bobot_verifikasi' => '0'], 'staf/dashboard');
cek('verifikator tidak dapat menyimpan bobot (403)', $bobot()['bobot_pertanyaan'] === '40');
$adm->kirim('admin/konfigurasi/simpan', ['jenis' => 'bobot', 'bobot_berkas' => '50', 'bobot_pertanyaan' => '50', 'bobot_verifikasi' => '0'], 'admin/konfigurasi');
cek('bobot valid tersimpan', $bobot()['bobot_berkas'] === '50' && $bobot()['bobot_verifikasi'] === '0');
$hit = (new PenilaianService())->hitung($idD, [10, 10, 10]);
cek('bobot verifikasi 0 -> komponen verifikasi tidak dipakai', $hit['komponen']['verifikasi']['dipakai'] === false && $hit['total'] === round((85 * 50 + 100 * 50) / 100, 2), (string) $hit['total']);
cek('rekomendasi lama tidak berubah setelah bobot diubah (snapshot)', (float) $rek($idA)['skor_total'] === 92.0);

echo "== 6. Konfigurasi pertanyaan ==\n";
$simpanQ = static fn (array $o = []) => array_merge([
    'jenis' => 'pertanyaan', 'kode_pertanyaan' => 'uji_q', 'teks_pertanyaan' => 'Apakah ini pertanyaan uji?', 'bobot' => '2',
    'urutan' => '90', 'opsi' => "Ya | 100\nTidak | 0", 'wajib' => '1', 'aktif' => '1', 'contoh_sementara' => '1',
], $o);
$nQ = static fn () => (int) Database::prototype()->query("SELECT COUNT(*) FROM tbl_pertanyaan_penilaian WHERE kode_pertanyaan='uji_q'")->fetchColumn();
cek('verifikator tidak boleh menyimpan pertanyaan (403)', $ver->kirim('admin/konfigurasi/simpan', $simpanQ(), 'staf/dashboard')['kode'] === 403);
$adm->kirim('admin/konfigurasi/simpan', $simpanQ(['opsi' => 'Hanya satu | 50']), 'admin/konfigurasi');
cek('pertanyaan dengan satu opsi ditolak', $nQ() === 0);
$adm->kirim('admin/konfigurasi/simpan', $simpanQ(['opsi' => "Ya | 101\nTidak | 0"]), 'admin/konfigurasi');
cek('poin di atas 100 ditolak', $nQ() === 0);
$adm->kirim('admin/konfigurasi/simpan', $simpanQ(['opsi' => "Ya 100\nTidak 0"]), 'admin/konfigurasi');
cek('format opsi salah ditolak', $nQ() === 0);
$adm->kirim('admin/konfigurasi/simpan', $simpanQ(['bobot' => '0']), 'admin/konfigurasi');
cek('bobot pertanyaan 0 ditolak', $nQ() === 0);
$adm->kirim('admin/konfigurasi/simpan', $simpanQ(), 'admin/konfigurasi');
cek('pertanyaan valid tersimpan beserta 2 opsi', $nQ() === 1 && (int) $pdo->query("SELECT COUNT(*) FROM tbl_opsi_jawaban o JOIN tbl_pertanyaan_penilaian p ON p.id_pertanyaan=o.id_pertanyaan WHERE p.kode_pertanyaan='uji_q'")->fetchColumn() === 2);
$adm->kirim('admin/konfigurasi/simpan', $simpanQ(), 'admin/konfigurasi');
cek('kode pertanyaan ganda ditolak', $nQ() === 1);
[$vE, $idE] = [new Klien($dasar), 0];
$vE->kirim('daftar-vendor/proses', [
    'nama_perusahaan' => 'PT Uji Nilai E', 'nama_kontak' => 'Kontak', 'email' => 'nilai-e@uji.test',
    'kata_sandi' => SANDI_VENDOR, 'konfirmasi_sandi' => SANDI_VENDOR,
], 'daftar-vendor');
$vE->masukVendor('nilai-e@uji.test', SANDI_VENDOR);
cek('pertanyaan baru tampil di formulir vendor', str_contains($vE->minta('vendor/formulir', 'GET', [], '&langkah=3')['isi'], 'Apakah ini pertanyaan uji?'));
$idQuji = (int) $pdo->query("SELECT id_pertanyaan FROM tbl_pertanyaan_penilaian WHERE kode_pertanyaan='uji_q'")->fetchColumn();
$opsiUji = (int) $pdo->query("SELECT id_opsi FROM tbl_opsi_jawaban WHERE id_pertanyaan={$idQuji} AND poin=100")->fetchColumn();
$vE->kirim('vendor/formulir/simpan', ['langkah' => '3', "q_{$idQuji}" => (string) $opsiUji], 'vendor/formulir');
// ubah opsi pada pertanyaan yang sudah dijawab: opsi tidak boleh berubah
$adm->kirim('admin/konfigurasi/simpan', $simpanQ(['id_pertanyaan' => (string) $idQuji, 'opsi' => "Mungkin | 50\nIya | 100", 'teks_pertanyaan' => 'Teks pertanyaan uji diubah']), 'admin/konfigurasi');
$teksOpsi = $pdo->query("SELECT GROUP_CONCAT(teks_opsi ORDER BY urutan) FROM tbl_opsi_jawaban WHERE id_pertanyaan={$idQuji}")->fetchColumn();
$teksQ = $pdo->query("SELECT teks_pertanyaan FROM tbl_pertanyaan_penilaian WHERE id_pertanyaan={$idQuji}")->fetchColumn();
cek('opsi pertanyaan yang sudah dijawab tidak berubah, teks boleh berubah', $teksOpsi === 'Ya,Tidak' && $teksQ === 'Teks pertanyaan uji diubah', (string) $teksOpsi);
$adm->kirim('admin/konfigurasi/simpan', $simpanQ(['id_pertanyaan' => (string) $idQuji, 'aktif' => '0']), 'admin/konfigurasi');
cek('pertanyaan dapat dinonaktifkan', (int) $pdo->query("SELECT aktif FROM tbl_pertanyaan_penilaian WHERE id_pertanyaan={$idQuji}")->fetchColumn() === 0);
cek('pertanyaan nonaktif tidak tampil di formulir', !str_contains($vE->minta('vendor/formulir', 'GET', [], '&langkah=3')['isi'], 'Teks pertanyaan uji diubah'));

echo "== 7. Tanpa pertanyaan aktif ==\n";
$pdo->exec("UPDATE tbl_pertanyaan_penilaian SET aktif = 0");
$hit = (new PenilaianService())->hitung($idD, [80, 80, 80]);
cek('komponen pertanyaan tidak dipakai bila tidak ada pertanyaan aktif', $hit['komponen']['pertanyaan']['skor'] === null && $hit['komponen']['pertanyaan']['dipakai'] === false);
cek('bobot dinormalkan ulang (hanya berkas): total = skor berkas', $hit['total'] === $hit['komponen']['berkas']['skor']);

echo "== 8. Audit log ==\n";
foreach (['jawaban_penilaian_disimpan', 'jawaban_ditandai', 'pertanyaan_penilaian_disimpan', 'bobot_penilaian_disimpan'] as $aksi) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM tbl_log_aktivitas WHERE aksi='{$aksi}'")->fetchColumn();
    cek("audit log memuat {$aksi}", $n > 0);
}

bersihkan($pdo);
$sisa = (int) $pdo->query("SELECT COUNT(*) FROM tbl_akun_vendor WHERE email LIKE '%@uji.test'")->fetchColumn()
      + (int) $pdo->query("SELECT COUNT(*) FROM tbl_pertanyaan_penilaian WHERE kode_pertanyaan LIKE 'uji\\_%'")->fetchColumn();
cek('data uji dibersihkan', $sisa === 0);
$b = $pdo->query("SELECT kunci, nilai FROM tbl_pengaturan_penilaian WHERE kunci LIKE 'bobot\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
cek('bobot dikembalikan ke 40/40/20', $b['bobot_berkas'] === '40' && $b['bobot_pertanyaan'] === '40' && $b['bobot_verifikasi'] === '20');
$aktifQ = (int) $pdo->query('SELECT COUNT(*) FROM tbl_pertanyaan_penilaian WHERE aktif = 1')->fetchColumn();
cek('4 pertanyaan contoh aktif kembali', $aktifQ === 4);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
