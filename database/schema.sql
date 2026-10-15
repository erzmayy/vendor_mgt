-- =====================================================================
-- PROTOTYPE VENDOR MANAGEMENT PT PERSONA PRIMA UTAMA (PPU)
-- Skema database LOKAL. Bukan database SIAP dan bukan database PPU.
--
-- PERHATIAN: file ini menghapus lalu membuat ulang seluruh tabel
-- prototype. Jalankan hanya di lingkungan lokal.
--
-- Konvensi:
--   * Nama tabel/kolom bahasa Indonesia, awalan tbl_
--   * tbl_user meniru BENTUK tbl_user SIAP (nama kolom sama), tetapi
--     isinya data fiktif dan memakai InnoDB + utf8mb4
--   * Kolom "xxx_oleh" menyimpan tbl_user.id TANPA foreign key, karena
--     pada mode lintas-database tbl_user SIAP bertipe MyISAM
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `vendor_management_prototype`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vendor_management_prototype`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS
  `tbl_jawaban_vendor`, `tbl_opsi_jawaban`, `tbl_pertanyaan_penilaian`, `tbl_pengaturan_penilaian`,
  `tbl_log_aktivitas`, `tbl_riwayat_status`, `tbl_catatan_tdr`,
  `tbl_penetapan_klasifikasi`, `tbl_rekomendasi_klasifikasi`,
  `tbl_aturan_klasifikasi`, `tbl_hasil_verifikasi`, `tbl_jadwal_verifikasi`,
  `tbl_dokumen_vendor`, `tbl_syarat_dokumen`, `tbl_field_formulir`,
  `tbl_pengajuan_vendor`, `tbl_vendor`, `tbl_akun_vendor`,
  `tbl_peran_user`, `tbl_user`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- AKUN STAF
-- ---------------------------------------------------------------------

-- Bentuk mengikuti tbl_user SIAP (dari dump yang diberikan).
-- Prototype hanya memakai bcrypt di kolom password_new.
-- Kolom password (MD5 di SIAP) sengaja dibiarkan kosong dan tidak dipakai.
CREATE TABLE `tbl_user` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `userid`         VARCHAR(50)  NOT NULL,
  `password`       VARCHAR(75)  NOT NULL DEFAULT '',
  `password_new`   VARCHAR(75)  DEFAULT NULL,
  `nama`           VARCHAR(75)  DEFAULT NULL,
  `dept`           VARCHAR(75)  DEFAULT NULL,
  `tgl_insert`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastlogin`      DATETIME DEFAULT NULL,
  `divisi`         VARCHAR(50)  DEFAULT NULL,
  `no_wa`          VARCHAR(25)  DEFAULT NULL,
  `ganti_password` ENUM('1','2') DEFAULT NULL,
  `email`          VARCHAR(100) DEFAULT NULL,
  `gender`         VARCHAR(7)   DEFAULT NULL,
  `atasan_id`      VARCHAR(10)  DEFAULT NULL,
  `atasan_nama`    VARCHAR(75)  DEFAULT NULL,
  `atasan_telp`    VARCHAR(25)  DEFAULT NULL,
  `kpu_kode`       VARCHAR(3)   DEFAULT NULL,
  `kpu_nama`       VARCHAR(75)  DEFAULT NULL,
  `npp`            VARCHAR(50)  DEFAULT NULL,
  `status_wa`      ENUM('BLM','SDH') NOT NULL DEFAULT 'BLM',
  `pusat`          ENUM('YES','NO')  NOT NULL DEFAULT 'NO',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pemetaan manual: pegawai mana berperan apa di Vendor Management.
CREATE TABLE `tbl_peran_user` (
  `id_peran_user`  INT NOT NULL AUTO_INCREMENT,
  `id_user`        INT NOT NULL COMMENT 'mengacu tbl_user.id, tanpa foreign key',
  `userid`         VARCHAR(50) NOT NULL,
  `peran`          ENUM('perencanaan','verifikator','berwenang','admin') NOT NULL,
  `aktif`          TINYINT(1) NOT NULL DEFAULT 1,
  `ditetapkan_oleh` INT DEFAULT NULL,
  `ditetapkan_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_peran_user`),
  UNIQUE KEY `uq_peran_user` (`id_user`,`peran`),
  KEY `idx_peran_aktif` (`peran`,`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- AKUN VENDOR (terpisah dari pegawai)
-- ---------------------------------------------------------------------

CREATE TABLE `tbl_akun_vendor` (
  `id_akun_vendor` INT NOT NULL AUTO_INCREMENT,
  `email`          VARCHAR(100) NOT NULL,
  `kata_sandi`     VARCHAR(255) NOT NULL COMMENT 'hash bcrypt (password_hash)',
  `nama_kontak`    VARCHAR(100) NOT NULL,
  `status_aktif`   TINYINT(1) NOT NULL DEFAULT 1,
  `terakhir_login` DATETIME DEFAULT NULL,
  `dibuat_pada`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_akun_vendor`),
  UNIQUE KEY `uq_akun_vendor_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- VENDOR DAN PENGAJUAN
-- ---------------------------------------------------------------------

-- Profil perusahaan. id_akun_vendor boleh kosong untuk vendor yang
-- dicatat manual oleh petugas.
CREATE TABLE `tbl_vendor` (
  `id_vendor`            INT NOT NULL AUTO_INCREMENT,
  `id_akun_vendor`       INT DEFAULT NULL,
  `nama_perusahaan`      VARCHAR(150) NOT NULL,
  `nama_perusahaan_normal` VARCHAR(150) NOT NULL COMMENT 'huruf kecil tanpa tanda baca, untuk cek duplikat',
  `bentuk_badan_usaha`   ENUM('PT','CV','UD','Koperasi','Yayasan','Perorangan','Lainnya') DEFAULT NULL,
  `nib`                  VARCHAR(30) DEFAULT NULL,
  `bidang_usaha`         VARCHAR(100) DEFAULT NULL,
  `alamat`               TEXT,
  `nama_penanggung_jawab` VARCHAR(100) DEFAULT NULL,
  `email_perusahaan`     VARCHAR(100) DEFAULT NULL,
  `no_telepon`           VARCHAR(25) DEFAULT NULL,
  `dibuat_pada`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_vendor`),
  UNIQUE KEY `uq_vendor_nib` (`nib`),
  UNIQUE KEY `uq_vendor_akun` (`id_akun_vendor`),
  KEY `idx_vendor_nama_normal` (`nama_perusahaan_normal`),
  KEY `idx_vendor_email` (`email_perusahaan`),
  CONSTRAINT `fk_vendor_akun` FOREIGN KEY (`id_akun_vendor`)
    REFERENCES `tbl_akun_vendor` (`id_akun_vendor`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_pengajuan_vendor` (
  `id_pengajuan`       INT NOT NULL AUTO_INCREMENT,
  `nomor_pengajuan`    VARCHAR(30) NOT NULL,
  `id_vendor`          INT NOT NULL,
  `sumber_pengajuan`   ENUM('portal_vendor_management','proses_manual') NOT NULL DEFAULT 'portal_vendor_management',
  `status_pengajuan`   ENUM('draf','diajukan','sedang_diperiksa','perlu_perbaikan',
                            'dokumen_terverifikasi','terjadwal','terverifikasi',
                            'ada_rekomendasi','terklasifikasi','tdr_tercatat',
                            'selesai','diarsipkan') NOT NULL DEFAULT 'draf',
  `langkah_formulir`   TINYINT NOT NULL DEFAULT 1,
  `data_tambahan`      JSON DEFAULT NULL COMMENT 'isian field dari tbl_field_formulir',
  `catatan_perbaikan`  TEXT,
  `jumlah_pengembalian` INT NOT NULL DEFAULT 0,
  `dicatat_oleh`       INT DEFAULT NULL COMMENT 'tbl_user.id, untuk proses manual',
  `tanggal_diajukan`   DATETIME DEFAULT NULL,
  `diarsipkan_pada`    DATETIME DEFAULT NULL,
  `diarsipkan_oleh`    INT DEFAULT NULL,
  `dibuat_pada`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pengajuan`),
  UNIQUE KEY `uq_pengajuan_nomor` (`nomor_pengajuan`),
  KEY `idx_pengajuan_status` (`status_pengajuan`),
  KEY `idx_pengajuan_sumber` (`sumber_pengajuan`),
  KEY `idx_pengajuan_vendor` (`id_vendor`),
  KEY `idx_pengajuan_diajukan` (`tanggal_diajukan`),
  CONSTRAINT `fk_pengajuan_vendor` FOREIGN KEY (`id_vendor`)
    REFERENCES `tbl_vendor` (`id_vendor`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Definisi field tambahan formulir yang dapat dikonfigurasi Admin.
CREATE TABLE `tbl_field_formulir` (
  `id_field`         INT NOT NULL AUTO_INCREMENT,
  `kode_field`       VARCHAR(40) NOT NULL,
  `label`            VARCHAR(100) NOT NULL,
  `tipe_input`       ENUM('teks','angka','tanggal','pilihan','area_teks') NOT NULL DEFAULT 'teks',
  `opsi_pilihan`     TEXT COMMENT 'dipisah baris baru, untuk tipe pilihan',
  `wajib`            TINYINT(1) NOT NULL DEFAULT 0,
  `urutan`           INT NOT NULL DEFAULT 0,
  `aktif`            TINYINT(1) NOT NULL DEFAULT 1,
  `contoh_sementara` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = bukan ketentuan resmi PPU',
  PRIMARY KEY (`id_field`),
  UNIQUE KEY `uq_field_kode` (`kode_field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- DOKUMEN
-- ---------------------------------------------------------------------

CREATE TABLE `tbl_syarat_dokumen` (
  `id_syarat`         INT NOT NULL AUTO_INCREMENT,
  `kode_syarat`       VARCHAR(40) NOT NULL,
  `nama_dokumen`      VARCHAR(120) NOT NULL,
  `keterangan`        VARCHAR(255) DEFAULT NULL,
  `wajib`             TINYINT(1) NOT NULL DEFAULT 1,
  `format_diizinkan`  VARCHAR(60) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
  `ukuran_maks_kb`    INT NOT NULL DEFAULT 2048,
  `urutan`            INT NOT NULL DEFAULT 0,
  `aktif`             TINYINT(1) NOT NULL DEFAULT 1,
  `contoh_sementara`  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = bukan ketentuan resmi PPU',
  PRIMARY KEY (`id_syarat`),
  UNIQUE KEY `uq_syarat_kode` (`kode_syarat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satu dokumen per persyaratan per pengajuan. Unggah ulang menimpa
-- berkas lama dan dicatat di tbl_log_aktivitas.
CREATE TABLE `tbl_dokumen_vendor` (
  `id_dokumen`         INT NOT NULL AUTO_INCREMENT,
  `id_pengajuan`       INT NOT NULL,
  `id_syarat`          INT NOT NULL,
  `nama_file_asli`     VARCHAR(255) NOT NULL,
  `nama_file_simpan`   VARCHAR(100) NOT NULL COMMENT 'nama acak di storage/uploads',
  `tipe_mime`          VARCHAR(100) NOT NULL,
  `ukuran_byte`        INT NOT NULL,
  `status_pemeriksaan` ENUM('belum_diperiksa','sesuai','tidak_sesuai') NOT NULL DEFAULT 'belum_diperiksa',
  `catatan_pemeriksa`  VARCHAR(500) DEFAULT NULL,
  `diperiksa_oleh`     INT DEFAULT NULL,
  `diperiksa_pada`     DATETIME DEFAULT NULL,
  `diunggah_pada`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_dokumen`),
  UNIQUE KEY `uq_dokumen_pengajuan_syarat` (`id_pengajuan`,`id_syarat`),
  UNIQUE KEY `uq_dokumen_nama_simpan` (`nama_file_simpan`),
  CONSTRAINT `fk_dokumen_pengajuan` FOREIGN KEY (`id_pengajuan`)
    REFERENCES `tbl_pengajuan_vendor` (`id_pengajuan`) ON DELETE RESTRICT,
  CONSTRAINT `fk_dokumen_syarat` FOREIGN KEY (`id_syarat`)
    REFERENCES `tbl_syarat_dokumen` (`id_syarat`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- VERIFIKASI
-- ---------------------------------------------------------------------

CREATE TABLE `tbl_jadwal_verifikasi` (
  `id_jadwal`         INT NOT NULL AUTO_INCREMENT,
  `id_pengajuan`      INT NOT NULL,
  `tanggal_verifikasi` DATETIME NOT NULL,
  `lokasi`            VARCHAR(200) DEFAULT NULL,
  `metode`            ENUM('kunjungan_lapangan','daring','dokumen') NOT NULL DEFAULT 'kunjungan_lapangan',
  `id_verifikator`    INT DEFAULT NULL COMMENT 'tbl_user.id',
  `catatan`           VARCHAR(500) DEFAULT NULL,
  `status_jadwal`     ENUM('aktif','dijadwalkan_ulang','dibatalkan','selesai') NOT NULL DEFAULT 'aktif',
  `dibuat_oleh`       INT NOT NULL,
  `dibuat_pada`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_jadwal`),
  KEY `idx_jadwal_pengajuan` (`id_pengajuan`,`status_jadwal`),
  KEY `idx_jadwal_tanggal` (`tanggal_verifikasi`),
  CONSTRAINT `fk_jadwal_pengajuan` FOREIGN KEY (`id_pengajuan`)
    REFERENCES `tbl_pengajuan_vendor` (`id_pengajuan`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tiga penilaian di bawah adalah CONTOH SIMULASI, bukan kriteria resmi PPU.
CREATE TABLE `tbl_hasil_verifikasi` (
  `id_hasil`         INT NOT NULL AUTO_INCREMENT,
  `id_pengajuan`     INT NOT NULL,
  `id_jadwal`        INT DEFAULT NULL,
  `skor_contoh_1`    TINYINT UNSIGNED NOT NULL COMMENT 'contoh kriteria: kelengkapan administrasi (0-100)',
  `skor_contoh_2`    TINYINT UNSIGNED NOT NULL COMMENT 'contoh kriteria: kesiapan operasional (0-100)',
  `skor_contoh_3`    TINYINT UNSIGNED NOT NULL COMMENT 'contoh kriteria: kepatuhan (0-100)',
  `temuan`           TEXT,
  `kesimpulan`       ENUM('memenuhi','memenuhi_sebagian','tidak_memenuhi') NOT NULL,
  `dicatat_oleh`     INT NOT NULL,
  `dicatat_pada`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_hasil`),
  KEY `idx_hasil_pengajuan` (`id_pengajuan`),
  CONSTRAINT `fk_hasil_pengajuan` FOREIGN KEY (`id_pengajuan`)
    REFERENCES `tbl_pengajuan_vendor` (`id_pengajuan`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hasil_jadwal` FOREIGN KEY (`id_jadwal`)
    REFERENCES `tbl_jadwal_verifikasi` (`id_jadwal`) ON DELETE SET NULL,
  CONSTRAINT `ck_hasil_skor` CHECK (`skor_contoh_1` <= 100 AND `skor_contoh_2` <= 100 AND `skor_contoh_3` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- KLASIFIKASI (simulasi, aturan dapat dikonfigurasi)
-- ---------------------------------------------------------------------

CREATE TABLE `tbl_aturan_klasifikasi` (
  `id_aturan`        INT NOT NULL AUTO_INCREMENT,
  `label_klasifikasi` VARCHAR(60) NOT NULL,
  `skor_min`         DECIMAL(5,2) NOT NULL,
  `skor_maks`        DECIMAL(5,2) NOT NULL,
  `urutan`           INT NOT NULL DEFAULT 0,
  `aktif`            TINYINT(1) NOT NULL DEFAULT 1,
  `contoh_sementara` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = aturan contoh, bukan aturan resmi PPU',
  PRIMARY KEY (`id_aturan`),
  UNIQUE KEY `uq_aturan_label` (`label_klasifikasi`),
  CONSTRAINT `ck_aturan_rentang` CHECK (`skor_min` <= `skor_maks`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_rekomendasi_klasifikasi` (
  `id_rekomendasi`    INT NOT NULL AUTO_INCREMENT,
  `id_pengajuan`      INT NOT NULL,
  `id_hasil`          INT NOT NULL,
  `id_aturan`         INT DEFAULT NULL,
  `label_klasifikasi` VARCHAR(60) NOT NULL COMMENT 'salinan label saat rekomendasi dibuat',
  `skor_total`        DECIMAL(5,2) NOT NULL,
  `rincian`           JSON DEFAULT NULL COMMENT 'rincian perhitungan per komponen (berkas, pertanyaan, verifikasi)',
  `simulasi`          TINYINT(1) NOT NULL DEFAULT 1,
  `dibuat_pada`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rekomendasi`),
  KEY `idx_rekomendasi_pengajuan` (`id_pengajuan`),
  CONSTRAINT `fk_rekomendasi_pengajuan` FOREIGN KEY (`id_pengajuan`)
    REFERENCES `tbl_pengajuan_vendor` (`id_pengajuan`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rekomendasi_hasil` FOREIGN KEY (`id_hasil`)
    REFERENCES `tbl_hasil_verifikasi` (`id_hasil`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rekomendasi_aturan` FOREIGN KEY (`id_aturan`)
    REFERENCES `tbl_aturan_klasifikasi` (`id_aturan`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hanya ditambah, tidak diubah: kumpulan barisnya = riwayat klasifikasi.
CREATE TABLE `tbl_penetapan_klasifikasi` (
  `id_penetapan`      INT NOT NULL AUTO_INCREMENT,
  `id_pengajuan`      INT NOT NULL,
  `id_rekomendasi`    INT DEFAULT NULL,
  `label_klasifikasi` VARCHAR(60) NOT NULL,
  `berbeda_dari_rekomendasi` TINYINT(1) NOT NULL DEFAULT 0,
  `alasan_perubahan`  TEXT COMMENT 'wajib diisi bila berbeda dari rekomendasi',
  `simulasi`          TINYINT(1) NOT NULL DEFAULT 1,
  `ditetapkan_oleh`   INT NOT NULL,
  `ditetapkan_pada`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_penetapan`),
  KEY `idx_penetapan_pengajuan` (`id_pengajuan`,`ditetapkan_pada`),
  CONSTRAINT `fk_penetapan_pengajuan` FOREIGN KEY (`id_pengajuan`)
    REFERENCES `tbl_pengajuan_vendor` (`id_pengajuan`) ON DELETE RESTRICT,
  CONSTRAINT `fk_penetapan_rekomendasi` FOREIGN KEY (`id_rekomendasi`)
    REFERENCES `tbl_rekomendasi_klasifikasi` (`id_rekomendasi`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TDR (catatan proses simulasi, BUKAN dokumen TDR resmi)
-- ---------------------------------------------------------------------

CREATE TABLE `tbl_catatan_tdr` (
  `id_tdr`          INT NOT NULL AUTO_INCREMENT,
  `id_pengajuan`    INT NOT NULL,
  `tanggal_proses`  DATE NOT NULL,
  `keterangan`      TEXT NOT NULL,
  `simulasi`        TINYINT(1) NOT NULL DEFAULT 1,
  `dicatat_oleh`    INT NOT NULL,
  `dicatat_pada`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_tdr`),
  KEY `idx_tdr_pengajuan` (`id_pengajuan`),
  CONSTRAINT `fk_tdr_pengajuan` FOREIGN KEY (`id_pengajuan`)
    REFERENCES `tbl_pengajuan_vendor` (`id_pengajuan`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- JEJAK
-- ---------------------------------------------------------------------

CREATE TABLE `tbl_riwayat_status` (
  `id_riwayat`     INT NOT NULL AUTO_INCREMENT,
  `id_pengajuan`   INT NOT NULL,
  `status_asal`    VARCHAR(30) DEFAULT NULL,
  `status_tujuan`  VARCHAR(30) NOT NULL,
  `tipe_pelaku`    ENUM('staf','vendor','sistem') NOT NULL,
  `id_pelaku`      INT DEFAULT NULL COMMENT 'tbl_user.id atau tbl_akun_vendor.id_akun_vendor',
  `catatan`        VARCHAR(500) DEFAULT NULL,
  `waktu`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_riwayat`),
  KEY `idx_riwayat_pengajuan` (`id_pengajuan`,`waktu`),
  CONSTRAINT `fk_riwayat_pengajuan` FOREIGN KEY (`id_pengajuan`)
    REFERENCES `tbl_pengajuan_vendor` (`id_pengajuan`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_log_aktivitas` (
  `id_log`        BIGINT NOT NULL AUTO_INCREMENT,
  `tipe_pelaku`   ENUM('staf','vendor','sistem') NOT NULL,
  `id_pelaku`     INT DEFAULT NULL,
  `aksi`          VARCHAR(80) NOT NULL,
  `objek`         VARCHAR(50) DEFAULT NULL,
  `id_objek`      INT DEFAULT NULL,
  `id_pengajuan`  INT DEFAULT NULL,
  `detail`        JSON DEFAULT NULL,
  `alamat_ip`     VARCHAR(45) DEFAULT NULL,
  `waktu`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `idx_log_pengajuan` (`id_pengajuan`),
  KEY `idx_log_pelaku` (`tipe_pelaku`,`id_pelaku`),
  KEY `idx_log_waktu` (`waktu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PENILAIAN KLASIFIKASI OTOMATIS (pertanyaan + kelengkapan berkas)
-- ---------------------------------------------------------------------

CREATE TABLE `tbl_pertanyaan_penilaian` (
  `id_pertanyaan`    INT NOT NULL AUTO_INCREMENT,
  `kode_pertanyaan`  VARCHAR(40) NOT NULL,
  `teks_pertanyaan`  VARCHAR(255) NOT NULL,
  `bobot`            TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'bobot relatif antar pertanyaan (1-100)',
  `wajib`            TINYINT(1) NOT NULL DEFAULT 1,
  `urutan`           INT NOT NULL DEFAULT 0,
  `aktif`            TINYINT(1) NOT NULL DEFAULT 1,
  `contoh_sementara` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pertanyaan`),
  UNIQUE KEY `uq_pertanyaan_kode` (`kode_pertanyaan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_opsi_jawaban` (
  `id_opsi`        INT NOT NULL AUTO_INCREMENT,
  `id_pertanyaan`  INT NOT NULL,
  `teks_opsi`      VARCHAR(200) NOT NULL,
  `poin`           TINYINT UNSIGNED NOT NULL COMMENT '0-100',
  `urutan`         INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_opsi`),
  KEY `idx_opsi_pertanyaan` (`id_pertanyaan`,`urutan`),
  CONSTRAINT `fk_opsi_pertanyaan` FOREIGN KEY (`id_pertanyaan`)
    REFERENCES `tbl_pertanyaan_penilaian` (`id_pertanyaan`) ON DELETE RESTRICT,
  CONSTRAINT `ck_opsi_poin` CHECK (`poin` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_jawaban_vendor` (
  `id_jawaban`           INT NOT NULL AUTO_INCREMENT,
  `id_pengajuan`         INT NOT NULL,
  `id_pertanyaan`        INT NOT NULL,
  `id_opsi`              INT NOT NULL,
  `ditandai_tidak_sesuai` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ditandai verifikator: tidak sesuai kenyataan, poin dihitung 0',
  `catatan_verifikator`  VARCHAR(500) DEFAULT NULL,
  `ditandai_oleh`        INT DEFAULT NULL,
  `ditandai_pada`        DATETIME DEFAULT NULL,
  `dijawab_pada`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_jawaban`),
  UNIQUE KEY `uq_jawaban_pengajuan_pertanyaan` (`id_pengajuan`,`id_pertanyaan`),
  CONSTRAINT `fk_jawaban_pengajuan` FOREIGN KEY (`id_pengajuan`)
    REFERENCES `tbl_pengajuan_vendor` (`id_pengajuan`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jawaban_pertanyaan` FOREIGN KEY (`id_pertanyaan`)
    REFERENCES `tbl_pertanyaan_penilaian` (`id_pertanyaan`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jawaban_opsi` FOREIGN KEY (`id_opsi`)
    REFERENCES `tbl_opsi_jawaban` (`id_opsi`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_pengaturan_penilaian` (
  `kunci`       VARCHAR(40) NOT NULL,
  `nilai`       VARCHAR(100) NOT NULL,
  `keterangan`  VARCHAR(200) DEFAULT NULL,
  PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

