<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Query sisi petugas atas pengajuan. Draf vendor tidak tampil bagi petugas. */
final class PengajuanRepository
{
    /** @return array<string,int> status => jumlah (tanpa draf) */
    public function hitungPerStatus(): array
    {
        $baris = Database::prototype()->query(
            "SELECT status_pengajuan, COUNT(*) n FROM tbl_pengajuan_vendor
              WHERE status_pengajuan <> 'draf' GROUP BY status_pengajuan"
        )->fetchAll();
        return array_column($baris, 'n', 'status_pengajuan');
    }

    /**
     * @param array{q?:string,status?:string,status_in?:string[],sumber?:string} $f
     * @return array{baris:array, total:int}
     */
    public function daftar(array $f, int $halaman, int $perHalaman): array
    {
        $where = ["p.status_pengajuan <> 'draf'"];
        $par = [];
        if (($f['q'] ?? '') !== '') {
            $q = '%' . addcslashes($f['q'], '%_\\') . '%';
            $where[] = '(v.nama_perusahaan LIKE :q1 OR p.nomor_pengajuan LIKE :q2 OR v.nib LIKE :q3)';
            $par += [':q1' => $q, ':q2' => $q, ':q3' => $q];
        }
        if (($f['status'] ?? '') !== '') {
            $where[] = 'p.status_pengajuan = :st';
            $par[':st'] = $f['status'];
        }
        if (!empty($f['status_in']) && is_array($f['status_in'])) {
            $ph = [];
            foreach (array_values($f['status_in']) as $i => $st) {
                $ph[] = ':si' . $i;
                $par[':si' . $i] = $st;
            }
            $where[] = 'p.status_pengajuan IN (' . implode(',', $ph) . ')';
        }
        if (($f['sumber'] ?? '') !== '') {
            $where[] = 'p.sumber_pengajuan = :sm';
            $par[':sm'] = $f['sumber'];
        }
        $w = implode(' AND ', $where);
        $pdo = Database::prototype();

        $c = $pdo->prepare("SELECT COUNT(*) FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor=p.id_vendor WHERE {$w}");
        $c->execute($par);
        $total = (int) $c->fetchColumn();

        $offset = max(0, ($halaman - 1) * $perHalaman);
        $s = $pdo->prepare(
            "SELECT p.id_pengajuan, p.nomor_pengajuan, p.status_pengajuan, p.sumber_pengajuan,
                    p.tanggal_diajukan, p.diperbarui_pada, v.nama_perusahaan, v.bidang_usaha, v.nib
               FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor = p.id_vendor
              WHERE {$w}
              ORDER BY COALESCE(p.tanggal_diajukan, p.dibuat_pada) DESC, p.id_pengajuan DESC
              LIMIT " . (int) $perHalaman . ' OFFSET ' . (int) $offset
        );
        $s->execute($par);
        return ['baris' => $s->fetchAll(), 'total' => $total];
    }

    public function detail(int $id): ?array
    {
        $s = Database::prototype()->prepare(
            "SELECT p.*, v.nama_perusahaan, v.bentuk_badan_usaha, v.nib, v.bidang_usaha, v.alamat,
                    v.nama_penanggung_jawab, v.email_perusahaan, v.no_telepon, a.email AS email_akun
               FROM tbl_pengajuan_vendor p
               JOIN tbl_vendor v ON v.id_vendor = p.id_vendor
               LEFT JOIN tbl_akun_vendor a ON a.id_akun_vendor = v.id_akun_vendor
              WHERE p.id_pengajuan = ? AND p.status_pengajuan <> 'draf'"
        );
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /** Dokumen terunggah beserta nama persyaratan. */
    public function dokumen(int $idPengajuan): array
    {
        $s = Database::prototype()->prepare(
            'SELECT d.*, s.nama_dokumen, s.wajib
               FROM tbl_dokumen_vendor d JOIN tbl_syarat_dokumen s ON s.id_syarat = d.id_syarat
              WHERE d.id_pengajuan = ? ORDER BY s.urutan, s.id_syarat'
        );
        $s->execute([$idPengajuan]);
        return $s->fetchAll();
    }

    public function jadwal(int $idPengajuan): array
    {
        $s = Database::prototype()->prepare(
            'SELECT * FROM tbl_jadwal_verifikasi WHERE id_pengajuan = ? ORDER BY id_jadwal DESC'
        );
        $s->execute([$idPengajuan]);
        return $s->fetchAll();
    }

    public function jadwalAktifDaftar(): array
    {
        return Database::prototype()->query(
            "SELECT j.*, p.nomor_pengajuan, p.status_pengajuan, v.nama_perusahaan
               FROM tbl_jadwal_verifikasi j
               JOIN tbl_pengajuan_vendor p ON p.id_pengajuan = j.id_pengajuan
               JOIN tbl_vendor v ON v.id_vendor = p.id_vendor
              WHERE j.status_jadwal = 'aktif' ORDER BY j.tanggal_verifikasi"
        )->fetchAll();
    }

    public function riwayat(int $idPengajuan): array
    {
        $s = Database::prototype()->prepare(
            'SELECT status_asal, status_tujuan, tipe_pelaku, id_pelaku, catatan, waktu
               FROM tbl_riwayat_status WHERE id_pengajuan = ? ORDER BY waktu DESC, id_riwayat DESC'
        );
        $s->execute([$idPengajuan]);
        return $s->fetchAll();
    }

    public function syaratWajibAktif(): array
    {
        return Database::prototype()->query(
            'SELECT id_syarat, nama_dokumen FROM tbl_syarat_dokumen WHERE aktif=1 AND wajib=1'
        )->fetchAll();
    }
    /**
     * Daftar tunggal untuk halaman Vendor Management: pengajuan (di luar draf) beserta level klasifikasi
     * terkini dan jadwal verifikasi aktif.
     *
     * @param array{q?:string,status?:string,status_in?:string[],sumber?:string,bidang?:string,level?:string} $f
     * @return array{baris:array, total:int}
     */
    public function daftarGabungan(array $f, int $halaman, int $perHalaman): array
    {
        $level = '(SELECT pk.label_klasifikasi FROM tbl_penetapan_klasifikasi pk
                    WHERE pk.id_pengajuan = p.id_pengajuan ORDER BY pk.id_penetapan DESC LIMIT 1)';
        $jadwal = "(SELECT j.tanggal_verifikasi FROM tbl_jadwal_verifikasi j
                     WHERE j.id_pengajuan = p.id_pengajuan AND j.status_jadwal = 'aktif'
                     ORDER BY j.id_jadwal DESC LIMIT 1)";

        $where = ["p.status_pengajuan <> 'draf'"];
        $par = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $where[] = '(v.nama_perusahaan LIKE :q1 OR p.nomor_pengajuan LIKE :q2 OR v.nib LIKE :q3)';
            $par += [':q1' => $like, ':q2' => $like, ':q3' => $like];
        }
        if (($f['status'] ?? '') !== '') {
            $where[] = 'p.status_pengajuan = :st';
            $par[':st'] = $f['status'];
        }
        if (!empty($f['status_in']) && is_array($f['status_in'])) {
            $ph = [];
            foreach (array_values($f['status_in']) as $i => $st) {
                $ph[] = ':si' . $i;
                $par[':si' . $i] = $st;
            }
            $where[] = 'p.status_pengajuan IN (' . implode(',', $ph) . ')';
        }
        if (($f['sumber'] ?? '') !== '') {
            $where[] = 'p.sumber_pengajuan = :sm';
            $par[':sm'] = $f['sumber'];
        }
        if (($f['bidang'] ?? '') !== '') {
            $where[] = 'v.bidang_usaha = :bd';
            $par[':bd'] = $f['bidang'];
        }
        if (($f['level'] ?? '') !== '') {
            $where[] = $level . ' = :lv';
            $par[':lv'] = $f['level'];
        }
        $w = implode(' AND ', $where);
        $pdo = Database::prototype();

        $c = $pdo->prepare("SELECT COUNT(*) FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor = p.id_vendor WHERE {$w}");
        $c->execute($par);
        $total = (int) $c->fetchColumn();

        $offset = max(0, ($halaman - 1) * $perHalaman);
        $s = $pdo->prepare(
            "SELECT p.id_pengajuan, p.nomor_pengajuan, p.status_pengajuan, p.sumber_pengajuan, p.tanggal_diajukan,
                    v.nama_perusahaan, v.bidang_usaha, v.nib, {$level} AS level, {$jadwal} AS jadwal
               FROM tbl_pengajuan_vendor p JOIN tbl_vendor v ON v.id_vendor = p.id_vendor
              WHERE {$w}
              ORDER BY COALESCE(p.tanggal_diajukan, p.dibuat_pada) DESC, p.id_pengajuan DESC
              LIMIT " . (int) $perHalaman . ' OFFSET ' . (int) $offset
        );
        $s->execute($par);
        return ['baris' => $s->fetchAll(), 'total' => $total];
    }
}
