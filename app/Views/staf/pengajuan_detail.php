<?php
/**
 * @var array $p @var array $dokumen @var array $jadwal @var array $riwayat @var array $nama
 * @var array $verifikator @var array $fields @var array $tambahan @var array $status @var array $bisa
 * @var array $penilaian @var ?array $hasil @var ?array $rekomendasi @var array $penetapan @var array $tdr @var array $aturan @var array $duplikat @var array $syarat
 */
use App\Core\Csrf;

$st = $p['status_pengajuan'];
$id = (int) $p['id_pengajuan'];
$namaStaf = static fn ($idUser) => $idUser && isset($nama[(int) $idUser]) ? $nama[(int) $idUser]['nama'] : 'Petugas';
?>
<div class="kartu">
  <div class="baris-judul">
    <div>
      <h1><?= h($p['nama_perusahaan']) ?></h1>
      <div class="lembut">No. <strong><?= h($p['nomor_pengajuan']) ?></strong> &middot;
        Sumber: <?= $p['sumber_pengajuan'] === 'proses_manual' ? 'Proses manual' : 'Portal Vendor Management' ?> &middot;
        Diajukan: <?= tgl($p['tanggal_diajukan']) ?></div>
    </div>
    <?= lencanaStatus($st, $status) ?>
  </div>
  <?php if ((int) $p['jumlah_pengembalian'] > 0): ?>
    <p class="lembut">Pernah dikembalikan: <?= (int) $p['jumlah_pengembalian'] ?> kali.</p>
  <?php endif; ?>
</div>

<div class="grid-dua">
  <div class="kartu">
    <h2>Data perusahaan</h2>
    <dl class="ringkasan">
      <dt>Bentuk badan usaha</dt><dd><?= h($p['bentuk_badan_usaha'] ?? '-') ?></dd>
      <dt>NIB / identitas</dt><dd><?= h($p['nib'] ?? '-') ?></dd>
      <dt>Bidang usaha</dt><dd><?= h($p['bidang_usaha'] ?? '-') ?></dd>
      <dt>Alamat</dt><dd><?= nl2br(h($p['alamat'] ?? '-')) ?></dd>
      <dt>Penanggung jawab</dt><dd><?= h($p['nama_penanggung_jawab'] ?? '-') ?></dd>
      <dt>Email</dt><dd><?= h($p['email_perusahaan'] ?? '-') ?></dd>
      <dt>Telepon</dt><dd><?= h($p['no_telepon'] ?? '-') ?></dd>
      <?php foreach ($fields as $f): ?>
        <dt><?= h($f['label']) ?></dt><dd><?= h((string) ($tambahan[$f['kode_field']] ?? '-')) ?></dd>
      <?php endforeach; ?>
    </dl>
  </div>

  <div class="kartu">
    <h2>Jadwal verifikasi</h2>
    <?php if ($jadwal === []): ?>
      <p class="lembut">Belum dijadwalkan.</p>
    <?php else: ?>
      <ul class="linimasa">
        <?php foreach ($jadwal as $j): ?>
          <li>
            <strong><?= tgl($j['tanggal_verifikasi']) ?></strong>
            <span class="status status-<?= $j['status_jadwal'] === 'aktif' ? 'hijau' : 'abu' ?>"><?= h(str_replace('_', ' ', $j['status_jadwal'])) ?></span>
            <div class="lembut"><?= h(str_replace('_', ' ', $j['metode'])) ?><?= $j['lokasi'] ? ' &middot; ' . h($j['lokasi']) : '' ?></div>
            <div class="lembut">Petugas: <?= h($namaStaf($j['id_verifikator'])) ?></div>
            <?php if ($j['catatan']): ?><div class="lembut"><?= h($j['catatan']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($bisa['jadwal'] && in_array($st, ['dokumen_terverifikasi', 'terjadwal'], true)): ?>
      <div class="kotak-aksi">
        <strong><?= $st === 'terjadwal' ? 'Jadwalkan ulang' : 'Tentukan jadwal verifikasi' ?></strong>
        <form method="post" action="<?= h(url('staf/jadwal/simpan')) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
          <label for="tgl">Tanggal dan jam</label>
          <input type="datetime-local" id="tgl" name="tanggal_verifikasi" required>
          <label for="metode">Metode</label>
          <select id="metode" name="metode" required>
            <option value="kunjungan_lapangan">Kunjungan lapangan</option>
            <option value="daring">Daring</option>
            <option value="dokumen">Pemeriksaan dokumen</option>
          </select>
          <label for="lokasi">Lokasi</label>
          <input type="text" id="lokasi" name="lokasi" maxlength="200" placeholder="Wajib untuk kunjungan lapangan">
          <label for="ver">Petugas verifikasi</label>
          <select id="ver" name="id_verifikator" required>
            <option value="">Pilih</option>
            <?php foreach ($verifikator as $v): ?>
              <option value="<?= (int) $v['id'] ?>"><?= h($v['nama']) ?> (<?= h($v['userid']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <label for="cat">Catatan (opsional)</label>
          <textarea id="cat" name="catatan" rows="2" maxlength="500"></textarea>
          <p><button class="tombol" type="submit">Simpan jadwal</button></p>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="kartu">
  <h2>Dokumen persyaratan <span class="lencana lencana-simulasi">contoh sementara</span></h2>
  <?php if ($dokumen === []): ?>
    <p class="lembut">Belum ada dokumen.</p>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Dokumen</th><th>Berkas</th><th>Pemeriksaan</th><?php if ($bisa['periksa'] && $st === 'sedang_diperiksa'): ?><th>Tindakan</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($dokumen as $d): ?>
      <tr>
        <td><?= h($d['nama_dokumen']) ?><?= (int) $d['wajib'] === 1 ? ' <span class="wajib">wajib</span>' : '' ?></td>
        <td>
          <?php if ($bisa['periksa']): ?>
            <a href="<?= h(url('dokumen/unduh')) ?>&amp;id=<?= (int) $d['id_dokumen'] ?>" target="_blank" rel="noopener"><?= h($d['nama_file_asli']) ?></a>
          <?php else: ?>
            <?= h($d['nama_file_asli']) ?>
          <?php endif; ?>
          <div class="lembut"><?= ukuranBerkas((int) $d['ukuran_byte']) ?> &middot; <?= tgl($d['diunggah_pada']) ?></div>
        </td>
        <td>
          <?php if ($d['status_pemeriksaan'] === 'sesuai'): ?><span class="status status-hijau">Sesuai</span>
          <?php elseif ($d['status_pemeriksaan'] === 'tidak_sesuai'): ?><span class="status status-kuning">Tidak sesuai</span>
          <?php else: ?><span class="status status-abu">Belum diperiksa</span><?php endif; ?>
          <?php if ($d['catatan_pemeriksa']): ?><div class="lembut"><?= h($d['catatan_pemeriksa']) ?></div><?php endif; ?>
        </td>
        <?php if ($bisa['periksa'] && $st === 'sedang_diperiksa'): ?>
        <td class="kolom-aksi">
          <form method="post" action="<?= h(url('staf/dokumen/periksa')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
            <input type="hidden" name="id_dokumen" value="<?= (int) $d['id_dokumen'] ?>">
            <input type="text" name="catatan" maxlength="500" placeholder="Catatan (wajib bila tidak sesuai)" aria-label="Catatan pemeriksaan <?= h($d['nama_dokumen']) ?>">
            <button class="tombol tombol-kecil" type="submit" name="hasil" value="sesuai">Sesuai</button>
            <button class="tombol tombol-kecil tombol-sekunder" type="submit" name="hasil" value="tidak_sesuai">Tidak sesuai</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <?php if ($bisa['periksa'] && $st === 'diajukan'): ?>
    <div class="kotak-aksi">
      <form method="post" action="<?= h(url('staf/pengajuan/mulai-periksa')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
        <button class="tombol" type="submit">Mulai pemeriksaan</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($st === 'sedang_diperiksa' && ($bisa['periksa'] || $bisa['kembalikan'])): ?>
    <div class="grid-dua">
      <?php if ($bisa['periksa']): ?>
      <div class="kotak-aksi">
        <strong>Dokumen lengkap dan sesuai</strong>
        <p class="lembut">Hanya dapat dilakukan jika semua dokumen wajib ada dan berstatus Sesuai.</p>
        <form method="post" action="<?= h(url('staf/pengajuan/verifikasi-dokumen')) ?>" data-konfirmasi="Tandai seluruh dokumen terverifikasi?">
          <?= Csrf::field() ?>
          <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
          <button class="tombol" type="submit">Verifikasi dokumen</button>
        </form>
      </div>
      <?php endif; ?>
      <?php if ($bisa['kembalikan']): ?>
      <div class="kotak-aksi">
        <strong>Kembalikan untuk perbaikan</strong>
        <form method="post" action="<?= h(url('staf/pengajuan/kembalikan')) ?>" data-konfirmasi="Kembalikan pengajuan ke vendor?">
          <?= Csrf::field() ?>
          <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
          <label for="catatan_kembali">Catatan untuk vendor</label>
          <textarea id="catatan_kembali" name="catatan" rows="3" minlength="10" maxlength="1000" required></textarea>
          <p><button class="tombol tombol-sekunder" type="submit">Kembalikan</button></p>
        </form>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($st === 'perlu_perbaikan' && $p['catatan_perbaikan']): ?>
  <div class="peringatan"><strong>Catatan perbaikan untuk vendor:</strong><br><?= nl2br(h($p['catatan_perbaikan'])) ?></div>
<?php endif; ?>

<?php if ($duplikat !== []): ?>
<div class="peringatan">
  <strong>Kemungkinan data vendor ganda</strong>
  <ul class="daftar-hak">
    <?php foreach ($duplikat as $d): ?>
      <li>
        <?php if ($d['id_pengajuan']): ?>
          <a href="<?= h(url('staf/pengajuan/detail')) ?>&amp;id=<?= (int) $d['id_pengajuan'] ?>"><?= h($d['vendor']['nama_perusahaan']) ?></a>
        <?php else: ?>
          <?= h($d['vendor']['nama_perusahaan']) ?>
        <?php endif; ?>
        &mdash; <?= h(implode('; ', $d['alasan'])) ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($bisa['manual'] && $p['sumber_pengajuan'] === 'proses_manual' && in_array($st, ['diajukan', 'sedang_diperiksa'], true)): ?>
<div class="kartu">
  <h2>Unggah dokumen (pengajuan manual)</h2>
  <p class="lembut">Dokumen yang diterima melalui proses manual dicatat oleh petugas di sini.</p>
  <?php foreach ($syarat as $s): ?>
    <form class="baris-dokumen" method="post" action="<?= h(url('staf/dokumen/unggah')) ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
      <input type="hidden" name="id_syarat" value="<?= (int) $s['id_syarat'] ?>">
      <div><strong><?= h($s['nama_dokumen']) ?></strong> <?= (int) $s['wajib'] === 1 ? '<span class="wajib">wajib</span>' : '' ?>
        <div class="lembut">Maks. <?= (int) $s['ukuran_maks_kb'] ?> KB &middot; <?= h(strtoupper((string) $s['format_diizinkan'])) ?></div></div>
      <div class="aksi-dokumen">
        <input type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png" required aria-label="Berkas <?= h($s['nama_dokumen']) ?>">
        <button class="tombol tombol-kecil" type="submit">Unggah</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($bisa['hasil'] && $st === 'terjadwal'): ?>
<div class="kartu">
  <h2>Catat hasil verifikasi <span class="lencana lencana-simulasi">kriteria contoh</span></h2>
  <p class="lembut">Tiga nilai di bawah adalah kriteria contoh untuk simulasi, bukan kriteria resmi PPU.</p>
  <form method="post" action="<?= h(url('staf/hasil/simpan')) ?>" data-konfirmasi="Simpan hasil verifikasi? Hasil tidak dapat diubah setelah disimpan.">
    <?= Csrf::field() ?>
    <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
    <div class="baris-form">
      <div><label for="s1">Kelengkapan administrasi (0-100)</label><input type="text" id="s1" name="skor_contoh_1" inputmode="numeric" maxlength="3" required></div>
      <div><label for="s2">Kesiapan operasional (0-100)</label><input type="text" id="s2" name="skor_contoh_2" inputmode="numeric" maxlength="3" required></div>
      <div><label for="s3">Kepatuhan (0-100)</label><input type="text" id="s3" name="skor_contoh_3" inputmode="numeric" maxlength="3" required></div>
    </div>
    <label for="kes">Kesimpulan</label>
    <select id="kes" name="kesimpulan" required>
      <option value="memenuhi">Memenuhi</option>
      <option value="memenuhi_sebagian">Memenuhi sebagian</option>
      <option value="tidak_memenuhi">Tidak memenuhi</option>
    </select>
    <label for="tem">Temuan</label>
    <textarea id="tem" name="temuan" rows="4" minlength="5" maxlength="2000" required></textarea>
    <p><button class="tombol" type="submit">Simpan hasil dan buat rekomendasi</button></p>
  </form>
</div>
<?php endif; ?>

<?php if ($bisa['rekomendasi'] && !empty($penilaian['komponen'])): ?>
<div class="kartu">
  <h2>Penilaian otomatis <span class="lencana lencana-simulasi">SIMULASI</span></h2>
  <p class="lembut">
    <?= $penilaian['tersimpan']
        ? 'Rincian perhitungan yang dipakai pada rekomendasi (tersimpan, tidak berubah).'
        : 'Pratinjau dari data saat ini. Skor akhir dihitung ulang saat hasil verifikasi dicatat.' ?>
    Bobot dan batas level adalah contoh, bukan ketentuan resmi PPU.
  </p>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Komponen</th><th>Skor</th><th>Bobot</th><th>Keterangan</th></tr></thead>
    <tbody>
    <?php foreach ($penilaian['komponen'] as $k): ?>
      <tr>
        <td><?= h($k['label']) ?></td>
        <td><?= $k['skor'] !== null ? h((string) $k['skor']) : '-' ?></td>
        <td><?= (int) $k['bobot'] ?><?= empty($k['dipakai']) ? ' <span class="lembut">(tidak dipakai)</span>' : '' ?></td>
        <td class="lembut"><?= h($k['detail']) ?></td>
      </tr>
    <?php endforeach; ?>
      <tr>
        <td><strong>Skor total</strong></td>
        <td colspan="3"><strong><?= $penilaian['total'] !== null ? h((string) $penilaian['total']) : '-' ?></strong></td>
      </tr>
    </tbody>
  </table></div>

  <?php $butir = $penilaian['komponen']['pertanyaan']['butir'] ?? []; ?>
  <?php if ($butir): ?>
    <h2 style="margin-top:16px">Jawaban pertanyaan penilaian</h2>
    <?php

    $bisaTandai = $bisa['periksa'] && !$penilaian['tersimpan'] && in_array($st, ['sedang_diperiksa', 'terjadwal'], true);
    ?>
    <div class="tabel-bungkus"><table class="tabel">
      <thead><tr><th>Pertanyaan</th><th>Jawaban vendor</th><th>Poin</th><th>Bobot</th><?php if ($bisaTandai): ?><th>Pemeriksaan</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($butir as $i => $b):
          $idQ = $b["id_pertanyaan"] ?? null; ?>
        <tr>
          <td><?= h($b['pertanyaan']) ?></td>
          <td><?= h($b['jawaban']) ?>
            <?php if ($b['ditandai']): ?><div><span class="status status-kuning">Ditandai tidak sesuai kenyataan</span></div>
              <?php if ($b['catatan']): ?><div class="lembut"><?= h($b['catatan']) ?></div><?php endif; ?>
            <?php endif; ?></td>
          <td><?= (int) $b['poin'] ?></td>
          <td><?= (int) $b['bobot'] ?></td>
          <?php if ($bisaTandai): ?>
          <td class="kolom-aksi">
            <?php if ($idQ !== null): ?>
            <form method="post" action="<?= h(url('staf/jawaban/tandai')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
              <input type="hidden" name="id_pertanyaan" value="<?= (int) $idQ ?>">
              <?php if (!$b['ditandai']): ?>
                <input type="text" name="catatan" maxlength="500" placeholder="Alasan (wajib)" aria-label="Alasan penandaan">
                <button class="tombol tombol-kecil tombol-sekunder" type="submit" name="tindakan" value="tandai">Tandai tidak sesuai</button>
              <?php else: ?>
                <button class="tombol tombol-kecil" type="submit" name="tindakan" value="hapus">Hapus tanda</button>
              <?php endif; ?>
            </form>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($bisa['rekomendasi'] && ($hasil || $rekomendasi)): ?>
<div class="kartu">
  <h2>Hasil verifikasi dan rekomendasi <span class="lencana lencana-simulasi">SIMULASI</span></h2>
  <?php if ($hasil): ?>
    <dl class="ringkasan">
      <dt>Kelengkapan administrasi</dt><dd><?= (int) $hasil['skor_contoh_1'] ?></dd>
      <dt>Kesiapan operasional</dt><dd><?= (int) $hasil['skor_contoh_2'] ?></dd>
      <dt>Kepatuhan</dt><dd><?= (int) $hasil['skor_contoh_3'] ?></dd>
      <dt>Kesimpulan</dt><dd><?= h(str_replace('_', ' ', $hasil['kesimpulan'])) ?></dd>
      <dt>Temuan</dt><dd><?= nl2br(h($hasil['temuan'])) ?></dd>
      <dt>Dicatat</dt><dd><?= tgl($hasil['dicatat_pada']) ?> oleh <?= h($namaStaf($hasil['dicatat_oleh'])) ?></dd>
    </dl>
  <?php endif; ?>
  <?php if ($rekomendasi): ?>
    <p><strong>Rekomendasi sistem:</strong> <?= h($rekomendasi['label_klasifikasi']) ?>
       (skor <?= h((string) $rekomendasi['skor_total']) ?>)
       <span class="lencana lencana-simulasi">aturan contoh, bukan aturan resmi PPU</span></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($bisa['rekomendasi'] && $penetapan): ?>
<div class="kartu">
  <h2>Penetapan klasifikasi <span class="lencana lencana-simulasi">SIMULASI</span></h2>
  <ul class="linimasa">
    <?php foreach ($penetapan as $i => $t): ?>
      <li>
        <strong><?= h($t['label_klasifikasi']) ?></strong>
        <?= $i === 0 ? '<span class="status status-hijau">berlaku</span>' : '<span class="status status-abu">digantikan</span>' ?>
        <div class="lembut"><?= tgl($t['ditetapkan_pada']) ?> oleh <?= h($namaStaf($t['ditetapkan_oleh'])) ?>
          <?= (int) $t['berbeda_dari_rekomendasi'] === 1 ? ' &middot; berbeda dari rekomendasi' : '' ?></div>
        <?php if ($t['alasan_perubahan']): ?><div class="lembut">Alasan: <?= h($t['alasan_perubahan']) ?></div><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($bisa['tetapkan'] && $rekomendasi && in_array($st, ['ada_rekomendasi', 'terklasifikasi'], true)): ?>
<div class="kartu">
  <h2><?= $st === 'terklasifikasi' ? 'Koreksi penetapan' : 'Tetapkan klasifikasi' ?> <span class="lencana lencana-simulasi">SIMULASI</span></h2>
  <form method="post" action="<?= h(url('staf/penetapan/simpan')) ?>" data-konfirmasi="Simpan penetapan klasifikasi (simulasi)?">
    <?= Csrf::field() ?>
    <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
    <label for="klas">Klasifikasi</label>
    <select id="klas" name="label_klasifikasi" required>
      <?php
      $dipilih = $penetapan[0]['label_klasifikasi'] ?? $rekomendasi['label_klasifikasi'];
      foreach ($aturan as $a): ?>
        <option value="<?= h($a['label_klasifikasi']) ?>" <?= $dipilih === $a['label_klasifikasi'] ? 'selected' : '' ?>>
          <?= h($a['label_klasifikasi']) ?><?= $a['label_klasifikasi'] === $rekomendasi['label_klasifikasi'] ? ' - rekomendasi sistem' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label for="alasan">Alasan <span class="lembut">(wajib bila berbeda dari rekomendasi atau saat koreksi)</span></label>
    <textarea id="alasan" name="alasan" rows="3" maxlength="1000"></textarea>
    <p><button class="tombol" type="submit">Simpan penetapan</button></p>
  </form>
</div>
<?php endif; ?>

<?php if ($tdr || ($bisa['tdr'] && $st === 'terklasifikasi')): ?>
<div class="kartu">
  <h2>Catatan proses TDR <span class="lencana lencana-simulasi">SIMULASI</span></h2>
  <p class="lembut">Catatan ini hanya simulasi proses. Ini bukan dokumen TDR resmi dan tidak menyatakan TDR telah diterbitkan.</p>
  <?php foreach ($tdr as $t): ?>
    <div class="kotak-aksi">
      <strong><?= h(date('d-m-Y', strtotime($t['tanggal_proses']))) ?></strong>
      <div><?= nl2br(h($t['keterangan'])) ?></div>
      <div class="lembut">Dicatat <?= tgl($t['dicatat_pada']) ?> oleh <?= h($namaStaf($t['dicatat_oleh'])) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if ($bisa['tdr'] && $st === 'terklasifikasi'): ?>
    <div class="kotak-aksi">
      <form method="post" action="<?= h(url('staf/tdr/simpan')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
        <label for="tp">Tanggal proses</label>
        <input type="date" id="tp" name="tanggal_proses" max="<?= h(date('Y-m-d')) ?>" value="<?= h(date('Y-m-d')) ?>" required>
        <label for="ket">Keterangan proses</label>
        <textarea id="ket" name="keterangan" rows="3" minlength="5" maxlength="2000" required></textarea>
        <p><button class="tombol" type="submit">Catat proses TDR (simulasi)</button></p>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($bisa['selesai'] && in_array($st, ['tdr_tercatat', 'selesai'], true)): ?>
<div class="kartu">
  <h2><?= $st === 'tdr_tercatat' ? 'Selesaikan pengajuan' : 'Arsipkan pengajuan' ?></h2>
  <form method="post" action="<?= h(url($st === 'tdr_tercatat' ? 'staf/pengajuan/selesaikan' : 'staf/pengajuan/arsipkan')) ?>"
        data-konfirmasi="<?= $st === 'tdr_tercatat' ? 'Tandai pengajuan selesai?' : 'Arsipkan pengajuan ini?' ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
    <button class="tombol" type="submit"><?= $st === 'tdr_tercatat' ? 'Tandai selesai' : 'Arsipkan' ?></button>
  </form>
</div>
<?php endif; ?>

<div class="kartu">
  <h2>Riwayat status</h2>
  <ul class="linimasa">
    <?php foreach ($riwayat as $r):
        $pelaku = $r['tipe_pelaku'] === 'staf' ? $namaStaf($r['id_pelaku']) : ($r['tipe_pelaku'] === 'vendor' ? 'Vendor' : 'Sistem'); ?>
      <li>
        <strong><?= h($status['status'][$r['status_tujuan']]['label'] ?? $r['status_tujuan']) ?></strong>
        <span class="lembut"><?= tgl($r['waktu']) ?> &middot; <?= h($pelaku) ?></span>
        <?php if ($r['catatan']): ?><div class="lembut"><?= h($r['catatan']) ?></div><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p><a href="<?= h(url('staf/pengajuan')) ?>">&larr; Kembali ke daftar</a></p>
</div>
