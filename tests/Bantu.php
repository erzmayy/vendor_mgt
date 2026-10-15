<?php
declare(strict_types=1);

/**
 * Pembantu uji: menjawab seluruh pertanyaan penilaian aktif lewat HTTP.
 * $mode: 'tertinggi' (opsi berpoin tertinggi) atau 'terendah'.
 */
function jawabSemuaPertanyaan(Klien $k, PDO $pdo, string $mode = 'tertinggi'): void
{
    $urut = $mode === 'tertinggi' ? 'DESC' : 'ASC';
    $q = $pdo->query('SELECT id_pertanyaan FROM tbl_pertanyaan_penilaian WHERE aktif = 1')->fetchAll(PDO::FETCH_COLUMN);
    $data = ['langkah' => '3'];
    foreach ($q as $idQ) {
        $opsi = $pdo->query("SELECT id_opsi FROM tbl_opsi_jawaban WHERE id_pertanyaan = " . (int) $idQ . " ORDER BY poin {$urut}, id_opsi LIMIT 1")->fetchColumn();
        $data['q_' . $idQ] = (string) $opsi;
    }
    $k->kirim('vendor/formulir/simpan', $data, 'vendor/formulir');
}
