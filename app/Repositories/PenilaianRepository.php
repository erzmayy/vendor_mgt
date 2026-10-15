<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Data pertanyaan penilaian, opsi jawaban, jawaban vendor, dan pengaturan bobot. */
final class PenilaianRepository
{
    /** @return array<int,array> pertanyaan beserta kunci 'opsi' (daftar opsi) */
    private function denganOpsi(array $pertanyaan): array
    {
        if ($pertanyaan === []) {
            return [];
        }
        $ids = array_map('intval', array_column($pertanyaan, 'id_pertanyaan'));
        $in = implode(',', $ids);
        $opsi = Database::prototype()->query(
            "SELECT * FROM tbl_opsi_jawaban WHERE id_pertanyaan IN ({$in}) ORDER BY urutan, id_opsi"
        )->fetchAll();
        $per = [];
        foreach ($opsi as $o) {
            $per[(int) $o['id_pertanyaan']][] = $o;
        }
        foreach ($pertanyaan as &$p) {
            $p['opsi'] = $per[(int) $p['id_pertanyaan']] ?? [];
        }
        return $pertanyaan;
    }

    public function pertanyaanAktif(): array
    {
        return $this->denganOpsi(Database::prototype()->query(
            'SELECT * FROM tbl_pertanyaan_penilaian WHERE aktif = 1 ORDER BY urutan, id_pertanyaan'
        )->fetchAll());
    }

    public function semuaPertanyaan(): array
    {
        return $this->denganOpsi(Database::prototype()->query(
            'SELECT * FROM tbl_pertanyaan_penilaian ORDER BY urutan, id_pertanyaan'
        )->fetchAll());
    }

    /** @return array<int,array> kunci = id_pertanyaan; memuat teks_opsi dan poin */
    public function jawaban(int $idPengajuan): array
    {
        $s = Database::prototype()->prepare(
            'SELECT j.*, o.teks_opsi, o.poin
               FROM tbl_jawaban_vendor j JOIN tbl_opsi_jawaban o ON o.id_opsi = j.id_opsi
              WHERE j.id_pengajuan = ?'
        );
        $s->execute([$idPengajuan]);
        $hasil = [];
        foreach ($s->fetchAll() as $b) {
            $hasil[(int) $b['id_pertanyaan']] = $b;
        }
        return $hasil;
    }

    /** Opsi harus milik pertanyaan yang dimaksud (dicek oleh pemanggil lewat cekOpsi). */
    public function opsiMilikPertanyaan(int $idOpsi, int $idPertanyaan): bool
    {
        $s = Database::prototype()->prepare(
            'SELECT 1 FROM tbl_opsi_jawaban WHERE id_opsi = ? AND id_pertanyaan = ?'
        );
        $s->execute([$idOpsi, $idPertanyaan]);
        return (bool) $s->fetchColumn();
    }

    public function simpanJawaban(int $idPengajuan, int $idPertanyaan, int $idOpsi): void
    {
        // jawaban baru menghapus tanda "tidak sesuai" dari verifikator sebelumnya
        Database::prototype()->prepare(
            'INSERT INTO tbl_jawaban_vendor (id_pengajuan,id_pertanyaan,id_opsi) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE id_opsi = VALUES(id_opsi), dijawab_pada = NOW(),
                 ditandai_tidak_sesuai = 0, catatan_verifikator = NULL, ditandai_oleh = NULL, ditandai_pada = NULL'
        )->execute([$idPengajuan, $idPertanyaan, $idOpsi]);
    }

    public function tandai(int $idPengajuan, int $idPertanyaan, bool $tidakSesuai, ?string $catatan, int $oleh): int
    {
        $s = Database::prototype()->prepare(
            'UPDATE tbl_jawaban_vendor
                SET ditandai_tidak_sesuai = ?, catatan_verifikator = ?, ditandai_oleh = ?, ditandai_pada = NOW()
              WHERE id_pengajuan = ? AND id_pertanyaan = ?'
        );
        $s->execute([$tidakSesuai ? 1 : 0, $catatan, $oleh, $idPengajuan, $idPertanyaan]);
        return $s->rowCount();
    }

    public function adaJawabanUntuk(int $idPertanyaan): bool
    {
        $s = Database::prototype()->prepare('SELECT 1 FROM tbl_jawaban_vendor WHERE id_pertanyaan = ? LIMIT 1');
        $s->execute([$idPertanyaan]);
        return (bool) $s->fetchColumn();
    }

    /** @return array<string,int> bobot_berkas, bobot_pertanyaan, bobot_verifikasi */
    public function bobot(): array
    {
        $baris = Database::prototype()->query(
            "SELECT kunci, nilai FROM tbl_pengaturan_penilaian WHERE kunci LIKE 'bobot\\_%'"
        )->fetchAll();
        $b = ['bobot_berkas' => 40, 'bobot_pertanyaan' => 40, 'bobot_verifikasi' => 20];
        foreach ($baris as $r) {
            $b[$r['kunci']] = (int) $r['nilai'];
        }
        return $b;
    }

    public function simpanBobot(array $bobot): void
    {
        $s = Database::prototype()->prepare(
            'INSERT INTO tbl_pengaturan_penilaian (kunci,nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
        );
        foreach ($bobot as $k => $v) {
            $s->execute([$k, (string) (int) $v]);
        }
    }
}
