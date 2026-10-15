<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Teks;
use App\Repositories\PenilaianRepository;
use DomainException;
use PDOException;

/**
 * Konfigurasi oleh Administrator: aturan klasifikasi, persyaratan dokumen, field formulir.
 * Tidak ada penghapusan: item dinonaktifkan agar data lama tetap konsisten.
 * Semua nilai awal adalah CONTOH SEMENTARA; penanda `contoh_sementara` dapat dimatikan
 * Administrator setelah ketentuan resmi PPU dikonfirmasi.
 */
final class KonfigurasiService
{
    private const FORMAT = ['pdf', 'jpg', 'jpeg', 'png'];

    private function int(mixed $v, int $min, int $max, string $nama): int
    {
        if (!is_string($v) && !is_int($v) || !preg_match('/^\d+$/', (string) $v) || (int) $v < $min || (int) $v > $max) {
            throw new DomainException("{$nama} harus angka {$min}-{$max}.");
        }
        return (int) $v;
    }

    private function simpanBaris(callable $aksi, string $pesanGanda): void
    {
        try {
            $aksi();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new DomainException($pesanGanda);
            }
            throw $e;
        }
    }

    /** Baris opsi "teks | poin" -> [[teks, poin], ...] */
    private function parseOpsi(string $teks): array
    {
        $hasil = [];
        foreach (explode("\n", str_replace("\r", '', $teks)) as $baris) {
            $baris = trim($baris);
            if ($baris === '') {
                continue;
            }
            $bagian = explode('|', $baris);
            if (count($bagian) !== 2) {
                throw new DomainException('Format opsi: "teks jawaban | poin" (satu per baris).');
            }
            $label = Teks::rapikan($bagian[0]);
            $poin = trim($bagian[1]);
            if ($label === '' || mb_strlen($label) > 200 || !ctype_digit($poin) || (int) $poin > 100) {
                throw new DomainException('Teks opsi 1-200 karakter dan poin berupa angka 0-100.');
            }
            $hasil[] = [$label, (int) $poin];
        }
        if (count($hasil) < 2 || count($hasil) > 8) {
            throw new DomainException('Setiap pertanyaan memerlukan 2-8 opsi jawaban.');
        }
        return $hasil;
    }

    public function simpanPertanyaan(array $in): void
    {
        $id = (int) ($in['id_pertanyaan'] ?? 0);
        $teks = Teks::rapikan($in['teks_pertanyaan'] ?? '');
        if (mb_strlen($teks) < 5 || mb_strlen($teks) > 255) {
            throw new DomainException('Teks pertanyaan 5-255 karakter.');
        }
        $bobot = $this->int($in['bobot'] ?? '', 1, 100, 'Bobot pertanyaan');
        $urutan = $this->int($in['urutan'] ?? '0', 0, 999, 'Urutan');
        $wajib = ($in['wajib'] ?? '') === '1' ? 1 : 0;
        $aktif = ($in['aktif'] ?? '') === '1' ? 1 : 0;
        $contoh = ($in['contoh_sementara'] ?? '') === '1' ? 1 : 0;
        $pdo = Database::prototype();
        $penilaian = new PenilaianRepository();

        $ubahOpsi = true;
        if ($id > 0 && $penilaian->adaJawabanUntuk($id)) {
            $ubahOpsi = false; // opsi yang sudah dijawab tidak boleh diubah agar skor lama tetap konsisten
        }
        $opsi = $ubahOpsi ? $this->parseOpsi((string) ($in['opsi'] ?? '')) : [];

        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE tbl_pertanyaan_penilaian SET teks_pertanyaan=?, bobot=?, wajib=?, urutan=?, aktif=?, contoh_sementara=? WHERE id_pertanyaan=?'
                )->execute([$teks, $bobot, $wajib, $urutan, $aktif, $contoh, $id]);
            } else {
                $kode = strtolower(trim((string) ($in['kode_pertanyaan'] ?? '')));
                if (!preg_match('/^[a-z0-9_]{3,40}$/', $kode)) {
                    throw new DomainException('Kode 3-40 karakter: huruf kecil, angka, garis bawah.');
                }
                $this->simpanBaris(function () use ($pdo, $kode, $teks, $bobot, $wajib, $urutan, $aktif, $contoh): void {
                    $pdo->prepare(
                        'INSERT INTO tbl_pertanyaan_penilaian (kode_pertanyaan,teks_pertanyaan,bobot,wajib,urutan,aktif,contoh_sementara)
                         VALUES (?,?,?,?,?,?,?)'
                    )->execute([$kode, $teks, $bobot, $wajib, $urutan, $aktif, $contoh]);
                }, 'Kode pertanyaan sudah digunakan.');
                $id = (int) $pdo->lastInsertId();
            }
            if ($ubahOpsi) {
                $pdo->prepare('DELETE FROM tbl_opsi_jawaban WHERE id_pertanyaan = ?')->execute([$id]);
                $ins = $pdo->prepare('INSERT INTO tbl_opsi_jawaban (id_pertanyaan,teks_opsi,poin,urutan) VALUES (?,?,?,?)');
                foreach ($opsi as $i => [$label, $poin]) {
                    $ins->execute([$id, $label, $poin, $i + 1]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        AuditService::catat('pertanyaan_penilaian_disimpan', ['teks' => $teks, 'bobot' => $bobot, 'aktif' => $aktif, 'opsi_diubah' => $ubahOpsi], 'pertanyaan', $id);
    }

    public function simpanBobot(array $in): void
    {
        $b = [
            'bobot_berkas'     => $this->int($in['bobot_berkas'] ?? '', 0, 100, 'Bobot kelengkapan berkas'),
            'bobot_pertanyaan' => $this->int($in['bobot_pertanyaan'] ?? '', 0, 100, 'Bobot pertanyaan penilaian'),
            'bobot_verifikasi' => $this->int($in['bobot_verifikasi'] ?? '', 0, 100, 'Bobot hasil verifikasi'),
        ];
        if (array_sum($b) !== 100) {
            throw new DomainException('Jumlah ketiga bobot harus tepat 100 (saat ini ' . array_sum($b) . ').');
        }
        (new PenilaianRepository())->simpanBobot($b);
        AuditService::catat('bobot_penilaian_disimpan', $b, 'pengaturan');
    }
    public function simpanAturan(array $in): void
    {
        $id = (int) ($in['id_aturan'] ?? 0);
        $label = Teks::rapikan($in['label_klasifikasi'] ?? '');
        if (mb_strlen($label) < 3 || mb_strlen($label) > 60) {
            throw new DomainException('Label klasifikasi 3-60 karakter.');
        }
        foreach (['skor_min', 'skor_maks'] as $k) {
            $v = (string) ($in[$k] ?? '');
            if (!is_numeric($v) || (float) $v < 0 || (float) $v > 100) {
                throw new DomainException('Skor minimum dan maksimum harus angka 0-100.');
            }
        }
        $min = round((float) $in['skor_min'], 2);
        $maks = round((float) $in['skor_maks'], 2);
        if ($min > $maks) {
            throw new DomainException('Skor minimum tidak boleh melebihi skor maksimum.');
        }
        $urutan = $this->int($in['urutan'] ?? '0', 0, 999, 'Urutan');
        $aktif = ($in['aktif'] ?? '') === '1' ? 1 : 0;
        $contoh = ($in['contoh_sementara'] ?? '') === '1' ? 1 : 0;

        if ($aktif === 1) {
            $s = Database::prototype()->prepare(
                'SELECT label_klasifikasi FROM tbl_aturan_klasifikasi
                  WHERE aktif = 1 AND id_aturan <> ? AND NOT (skor_maks < ? OR skor_min > ?) LIMIT 1'
            );
            $s->execute([$id, $min, $maks]);
            if ($bentrok = $s->fetchColumn()) {
                throw new DomainException("Rentang skor bertabrakan dengan aturan aktif \"{$bentrok}\".");
            }
        }

        $pdo = Database::prototype();
        $this->simpanBaris(function () use ($pdo, $id, $label, $min, $maks, $urutan, $aktif, $contoh): void {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE tbl_aturan_klasifikasi SET label_klasifikasi=?, skor_min=?, skor_maks=?, urutan=?, aktif=?, contoh_sementara=?
                      WHERE id_aturan=?'
                )->execute([$label, $min, $maks, $urutan, $aktif, $contoh, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO tbl_aturan_klasifikasi (label_klasifikasi,skor_min,skor_maks,urutan,aktif,contoh_sementara)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$label, $min, $maks, $urutan, $aktif, $contoh]);
            }
        }, 'Label klasifikasi sudah digunakan.');
        AuditService::catat('aturan_klasifikasi_disimpan', ['label' => $label, 'min' => $min, 'maks' => $maks, 'aktif' => $aktif], 'aturan', $id ?: null);
    }

    public function simpanSyarat(array $in): void
    {
        $id = (int) ($in['id_syarat'] ?? 0);
        $nama = Teks::rapikan($in['nama_dokumen'] ?? '');
        if (mb_strlen($nama) < 3 || mb_strlen($nama) > 120) {
            throw new DomainException('Nama dokumen 3-120 karakter.');
        }
        $ket = Teks::rapikan($in['keterangan'] ?? '');
        if (mb_strlen($ket) > 255) {
            throw new DomainException('Keterangan maksimal 255 karakter.');
        }
        $format = array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($in['format_diizinkan'] ?? ''))))));
        if ($format === [] || array_diff($format, self::FORMAT) !== []) {
            throw new DomainException('Format yang diizinkan hanya: ' . implode(', ', self::FORMAT) . ' (pisahkan dengan koma).');
        }
        $ukuran = $this->int($in['ukuran_maks_kb'] ?? '', 50, 2048, 'Ukuran maksimum (KB)');
        $urutan = $this->int($in['urutan'] ?? '0', 0, 999, 'Urutan');
        $wajib = ($in['wajib'] ?? '') === '1' ? 1 : 0;
        $aktif = ($in['aktif'] ?? '') === '1' ? 1 : 0;
        $contoh = ($in['contoh_sementara'] ?? '') === '1' ? 1 : 0;
        $pdo = Database::prototype();

        if ($id > 0) {
            $pdo->prepare(
                'UPDATE tbl_syarat_dokumen SET nama_dokumen=?, keterangan=?, wajib=?, format_diizinkan=?, ukuran_maks_kb=?, urutan=?, aktif=?, contoh_sementara=?
                  WHERE id_syarat=?'
            )->execute([$nama, $ket !== '' ? $ket : null, $wajib, implode(',', $format), $ukuran, $urutan, $aktif, $contoh, $id]);
        } else {
            $kode = strtolower(trim((string) ($in['kode_syarat'] ?? '')));
            if (!preg_match('/^[a-z0-9_]{3,40}$/', $kode)) {
                throw new DomainException('Kode 3-40 karakter: huruf kecil, angka, garis bawah.');
            }
            $this->simpanBaris(function () use ($pdo, $kode, $nama, $ket, $wajib, $format, $ukuran, $urutan, $aktif, $contoh): void {
                $pdo->prepare(
                    'INSERT INTO tbl_syarat_dokumen (kode_syarat,nama_dokumen,keterangan,wajib,format_diizinkan,ukuran_maks_kb,urutan,aktif,contoh_sementara)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute([$kode, $nama, $ket !== '' ? $ket : null, $wajib, implode(',', $format), $ukuran, $urutan, $aktif, $contoh]);
            }, 'Kode persyaratan sudah digunakan.');
        }
        AuditService::catat('syarat_dokumen_disimpan', ['nama' => $nama, 'aktif' => $aktif, 'wajib' => $wajib], 'syarat', $id ?: null);
    }

    public function simpanField(array $in): void
    {
        $id = (int) ($in['id_field'] ?? 0);
        $label = Teks::rapikan($in['label'] ?? '');
        if (mb_strlen($label) < 3 || mb_strlen($label) > 100) {
            throw new DomainException('Label 3-100 karakter.');
        }
        $tipe = (string) ($in['tipe_input'] ?? '');
        if (!in_array($tipe, ['teks', 'angka', 'tanggal', 'pilihan', 'area_teks'], true)) {
            throw new DomainException('Tipe isian tidak valid.');
        }
        $opsi = trim((string) ($in['opsi_pilihan'] ?? ''));
        if ($tipe === 'pilihan') {
            $baris = array_filter(array_map('trim', explode("\n", str_replace("\r", '', $opsi))));
            if (count($baris) < 2 || count($baris) > 30) {
                throw new DomainException('Tipe pilihan memerlukan 2-30 opsi (satu per baris).');
            }
            $opsi = implode("\n", $baris);
        } else {
            $opsi = '';
        }
        $urutan = $this->int($in['urutan'] ?? '0', 0, 999, 'Urutan');
        $wajib = ($in['wajib'] ?? '') === '1' ? 1 : 0;
        $aktif = ($in['aktif'] ?? '') === '1' ? 1 : 0;
        $contoh = ($in['contoh_sementara'] ?? '') === '1' ? 1 : 0;
        $pdo = Database::prototype();

        if ($id > 0) {
            $pdo->prepare(
                'UPDATE tbl_field_formulir SET label=?, tipe_input=?, opsi_pilihan=?, wajib=?, urutan=?, aktif=?, contoh_sementara=? WHERE id_field=?'
            )->execute([$label, $tipe, $opsi !== '' ? $opsi : null, $wajib, $urutan, $aktif, $contoh, $id]);
        } else {
            $kode = strtolower(trim((string) ($in['kode_field'] ?? '')));
            if (!preg_match('/^[a-z0-9_]{3,40}$/', $kode)) {
                throw new DomainException('Kode 3-40 karakter: huruf kecil, angka, garis bawah.');
            }
            $this->simpanBaris(function () use ($pdo, $kode, $label, $tipe, $opsi, $wajib, $urutan, $aktif, $contoh): void {
                $pdo->prepare(
                    'INSERT INTO tbl_field_formulir (kode_field,label,tipe_input,opsi_pilihan,wajib,urutan,aktif,contoh_sementara)
                     VALUES (?,?,?,?,?,?,?,?)'
                )->execute([$kode, $label, $tipe, $opsi !== '' ? $opsi : null, $wajib, $urutan, $aktif, $contoh]);
            }, 'Kode field sudah digunakan.');
        }
        AuditService::catat('field_formulir_disimpan', ['label' => $label, 'aktif' => $aktif, 'wajib' => $wajib], 'field', $id ?: null);
    }
}
