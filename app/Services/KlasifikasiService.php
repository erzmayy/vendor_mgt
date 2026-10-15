<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Teks;
use App\Repositories\KlasifikasiRepository;
use App\Repositories\PengajuanRepository;
use DomainException;

/**
 * Hasil verifikasi -> rekomendasi klasifikasi (berdasar aturan konfigurasi) -> penetapan
 * oleh Petugas Berwenang -> catatan TDR -> selesai -> arsip.
 *
 * SEMUA hasil di sini adalah SIMULASI: aturan, skor, dan kategori berasal dari konfigurasi
 * contoh (tbl_aturan_klasifikasi), bukan ketentuan resmi PPU. Catatan TDR bukan dokumen TDR resmi.
 */
final class KlasifikasiService
{
    public function __construct(
        private PengajuanRepository $pengajuan = new PengajuanRepository(),
        private KlasifikasiRepository $repo = new KlasifikasiRepository(),
        private WorkflowService $alur = new WorkflowService(),
    ) {
    }

    private function pelaku(): array
    {
        $p = Auth::pengguna();
        return ['tipe' => 'staf', 'id' => (int) $p['id'], 'peran' => $p['peran']];
    }

    private function ambil(int $id): array
    {
        $p = $this->pengajuan->detail($id);
        if ($p === null) {
            throw new DomainException('Pengajuan tidak ditemukan.');
        }
        return $p;
    }

    /** Rata-rata tiga skor contoh, dibulatkan 2 desimal. */
    public static function skorTotal(int $a, int $b, int $c): float
    {
        return round(($a + $b + $c) / 3, 2);
    }

    /** @return array{id_aturan:?int,label:string}  label bawaan bila tidak ada aturan yang cocok */
    public function cocokkanAturan(float $skor): array
    {
        foreach ($this->repo->aturanAktif() as $a) {
            if ($skor >= (float) $a['skor_min'] && $skor <= (float) $a['skor_maks']) {
                return ['id_aturan' => (int) $a['id_aturan'], 'label' => (string) $a['label_klasifikasi']];
            }
        }
        return ['id_aturan' => null, 'label' => 'Tidak ada aturan yang cocok'];
    }

    public function catatHasil(int $idPengajuan, array $in): void
    {
        $p = $this->ambil($idPengajuan);
        if ($p['status_pengajuan'] !== 'terjadwal') {
            throw new DomainException('Hasil verifikasi hanya dapat dicatat saat pengajuan berstatus "Verifikasi terjadwal".');
        }
        $skor = [];
        foreach (['skor_contoh_1', 'skor_contoh_2', 'skor_contoh_3'] as $k) {
            $v = $in[$k] ?? '';
            if (!is_string($v) || !ctype_digit($v) || (int) $v > 100) {
                throw new DomainException('Setiap nilai harus berupa angka bulat 0-100.');
            }
            $skor[$k] = (int) $v;
        }
        $kesimpulan = (string) ($in['kesimpulan'] ?? '');
        if (!in_array($kesimpulan, ['memenuhi', 'memenuhi_sebagian', 'tidak_memenuhi'], true)) {
            throw new DomainException('Kesimpulan tidak valid.');
        }
        $temuan = trim((string) ($in['temuan'] ?? ''));
        if (mb_strlen($temuan) < 5 || mb_strlen($temuan) > 2000) {
            throw new DomainException('Temuan wajib diisi (5-2000 karakter).');
        }

        $pelaku = $this->pelaku();
        $pdo = Database::prototype();
        $pdo->beginTransaction();
        try {
            $j = $pdo->prepare("SELECT id_jadwal FROM tbl_jadwal_verifikasi WHERE id_pengajuan = ? AND status_jadwal = 'aktif' LIMIT 1");
            $j->execute([$idPengajuan]);
            $idJadwal = $j->fetchColumn() ?: null;

            $pdo->prepare(
                'INSERT INTO tbl_hasil_verifikasi
                   (id_pengajuan,id_jadwal,skor_contoh_1,skor_contoh_2,skor_contoh_3,temuan,kesimpulan,dicatat_oleh)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$idPengajuan, $idJadwal, $skor['skor_contoh_1'], $skor['skor_contoh_2'], $skor['skor_contoh_3'], $temuan, $kesimpulan, $pelaku['id']]);
            $idHasil = (int) $pdo->lastInsertId();

            if ($idJadwal) {
                $pdo->prepare("UPDATE tbl_jadwal_verifikasi SET status_jadwal = 'selesai' WHERE id_jadwal = ?")->execute([$idJadwal]);
            }

            $this->alur->ubahStatus($idPengajuan, 'terverifikasi', $pelaku, 'Hasil verifikasi dicatat');

            // Skor total otomatis: kelengkapan berkas + pertanyaan penilaian + hasil verifikasi (bobot dari konfigurasi)
            $hitung = (new PenilaianService())->hitung(
                $idPengajuan,
                [$skor['skor_contoh_1'], $skor['skor_contoh_2'], $skor['skor_contoh_3']]
            );
            $total = $hitung['total'] ?? self::skorTotal($skor['skor_contoh_1'], $skor['skor_contoh_2'], $skor['skor_contoh_3']);
            $cocok = $this->cocokkanAturan($total);
            $pdo->prepare(
                'INSERT INTO tbl_rekomendasi_klasifikasi (id_pengajuan,id_hasil,id_aturan,label_klasifikasi,skor_total,rincian,simulasi)
                 VALUES (?,?,?,?,?,?,1)'
            )->execute([
                $idPengajuan, $idHasil, $cocok['id_aturan'], $cocok['label'], $total,
                json_encode(['total' => $total, 'komponen' => $hitung['komponen']], JSON_UNESCAPED_UNICODE),
            ]);

            $this->alur->ubahStatus(
                $idPengajuan, 'ada_rekomendasi', ['tipe' => 'sistem', 'id' => null, 'peran' => []],
                'Rekomendasi (simulasi): ' . $cocok['label'] . ', skor ' . $total
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        AuditService::catat('hasil_verifikasi_dicatat', ['kesimpulan' => $kesimpulan, 'skor_total' => $total], 'hasil', $idHasil, $idPengajuan);
    }

    public function tetapkan(int $idPengajuan, string $label, string $alasan): void
    {
        $p = $this->ambil($idPengajuan);
        $status = $p['status_pengajuan'];
        if (!in_array($status, ['ada_rekomendasi', 'terklasifikasi'], true)) {
            throw new DomainException('Klasifikasi hanya dapat ditetapkan setelah ada rekomendasi dan sebelum catatan TDR.');
        }
        $rek = $this->repo->rekomendasi($idPengajuan);
        if ($rek === null) {
            throw new DomainException('Belum ada rekomendasi untuk pengajuan ini.');
        }

        $diizinkan = array_column($this->repo->aturanAktif(), 'label_klasifikasi');
        if (!in_array($label, $diizinkan, true)) {
            throw new DomainException('Pilih klasifikasi dari daftar aturan yang aktif.');
        }
        $alasan = Teks::rapikan($alasan);
        $berbeda = $label !== $rek['label_klasifikasi'];
        $koreksi = $status === 'terklasifikasi';
        if (($berbeda || $koreksi) && (mb_strlen($alasan) < 10 || mb_strlen($alasan) > 1000)) {
            throw new DomainException(
                $koreksi ? 'Koreksi penetapan wajib disertai alasan (10-1000 karakter).'
                         : 'Klasifikasi berbeda dari rekomendasi wajib disertai alasan (10-1000 karakter).'
            );
        }

        $pelaku = $this->pelaku();
        $pdo = Database::prototype();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO tbl_penetapan_klasifikasi
                   (id_pengajuan,id_rekomendasi,label_klasifikasi,berbeda_dari_rekomendasi,alasan_perubahan,simulasi,ditetapkan_oleh)
                 VALUES (?,?,?,?,?,1,?)'
            )->execute([$idPengajuan, $rek['id_rekomendasi'], $label, $berbeda ? 1 : 0, $alasan !== '' ? $alasan : null, $pelaku['id']]);
            $idPen = (int) $pdo->lastInsertId();

            if ($status === 'ada_rekomendasi') {
                $this->alur->ubahStatus($idPengajuan, 'terklasifikasi', $pelaku, 'Klasifikasi (simulasi) ditetapkan: ' . $label);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        AuditService::catat($koreksi ? 'klasifikasi_dikoreksi' : 'klasifikasi_ditetapkan',
            ['label' => $label, 'berbeda' => $berbeda], 'penetapan', $idPen, $idPengajuan);
    }

    public function catatTdr(int $idPengajuan, string $tanggal, string $keterangan): void
    {
        $p = $this->ambil($idPengajuan);
        if ($p['status_pengajuan'] !== 'terklasifikasi') {
            throw new DomainException('Catatan TDR hanya dapat dibuat setelah klasifikasi ditetapkan.');
        }
        $t = \DateTime::createFromFormat('!Y-m-d', $tanggal); // "!" = jam 00:00, agar tanggal hari ini tidak dianggap masa depan
        if (!$t || $t->format('Y-m-d') !== $tanggal) {
            throw new DomainException('Tanggal proses tidak valid.');
        }
        if ($t > new \DateTime('today')) {
            throw new DomainException('Tanggal proses tidak boleh di masa depan.');
        }
        $keterangan = trim($keterangan);
        if (mb_strlen($keterangan) < 5 || mb_strlen($keterangan) > 2000) {
            throw new DomainException('Keterangan wajib diisi (5-2000 karakter).');
        }

        $pelaku = $this->pelaku();
        $pdo = Database::prototype();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO tbl_catatan_tdr (id_pengajuan,tanggal_proses,keterangan,simulasi,dicatat_oleh) VALUES (?,?,?,1,?)'
            )->execute([$idPengajuan, $t->format('Y-m-d'), $keterangan, $pelaku['id']]);
            $idTdr = (int) $pdo->lastInsertId();
            $this->alur->ubahStatus($idPengajuan, 'tdr_tercatat', $pelaku, 'Proses TDR (simulasi) dicatat');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        AuditService::catat('tdr_dicatat', [], 'tdr', $idTdr, $idPengajuan);
    }

    public function selesaikan(int $idPengajuan): void
    {
        $this->ambil($idPengajuan);
        $this->alur->ubahStatus($idPengajuan, 'selesai', $this->pelaku(), 'Pengajuan diselesaikan');
    }

    public function arsipkan(int $idPengajuan): void
    {
        $this->ambil($idPengajuan);
        $pelaku = $this->pelaku();
        $pdo = Database::prototype();
        $pdo->beginTransaction();
        try {
            $this->alur->ubahStatus($idPengajuan, 'diarsipkan', $pelaku, 'Pengajuan diarsipkan');
            $pdo->prepare('UPDATE tbl_pengajuan_vendor SET diarsipkan_pada = NOW(), diarsipkan_oleh = ? WHERE id_pengajuan = ?')
                ->execute([$pelaku['id'], $idPengajuan]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
