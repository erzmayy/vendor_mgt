-- =====================================================================
-- MIGRASI: penilaian klasifikasi otomatis (pertanyaan + kelengkapan berkas)
-- Untuk database yang SUDAH dibuat dari schema.sql versi sebelumnya.
-- Instalasi baru tidak perlu file ini (sudah ada di schema.sql dan seed.sql).
-- Aman dijalankan ulang.
-- Semua isi awal adalah CONTOH SIMULASI, bukan ketentuan resmi PPU.
-- =====================================================================

USE `vendor_management_prototype`;

CREATE TABLE IF NOT EXISTS `tbl_pertanyaan_penilaian` (
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

CREATE TABLE IF NOT EXISTS `tbl_opsi_jawaban` (
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

CREATE TABLE IF NOT EXISTS `tbl_jawaban_vendor` (
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

CREATE TABLE IF NOT EXISTS `tbl_pengaturan_penilaian` (
  `kunci`       VARCHAR(40) NOT NULL,
  `nilai`       VARCHAR(100) NOT NULL,
  `keterangan`  VARCHAR(200) DEFAULT NULL,
  PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kolom rincian perhitungan pada rekomendasi (abaikan galat "Duplicate column" bila sudah ada)
SET @ada := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'tbl_rekomendasi_klasifikasi' AND column_name = 'rincian');
SET @sql := IF(@ada = 0,
  'ALTER TABLE tbl_rekomendasi_klasifikasi ADD COLUMN rincian JSON DEFAULT NULL AFTER skor_total',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

INSERT IGNORE INTO `tbl_pengaturan_penilaian` (`kunci`,`nilai`,`keterangan`) VALUES
  ('bobot_berkas','40','Bobot komponen kelengkapan berkas (contoh)'),
  ('bobot_pertanyaan','40','Bobot komponen pertanyaan penilaian (contoh)'),
  ('bobot_verifikasi','20','Bobot komponen hasil verifikasi petugas (contoh)');

INSERT IGNORE INTO `tbl_pertanyaan_penilaian` (`kode_pertanyaan`,`teks_pertanyaan`,`bobot`,`wajib`,`urutan`) VALUES
  ('lama_usaha','Berapa lama perusahaan Anda telah beroperasi?',3,1,1),
  ('pengalaman','Apakah perusahaan memiliki pengalaman menangani pekerjaan sejenis?',3,1,2),
  ('tenaga_kerja','Berapa jumlah tenaga kerja tetap perusahaan?',2,1,3),
  ('mutu','Apakah perusahaan memiliki pengendalian mutu atau sertifikasi?',2,1,4);

INSERT INTO `tbl_opsi_jawaban` (`id_pertanyaan`,`teks_opsi`,`poin`,`urutan`)
SELECT p.id_pertanyaan, o.teks, o.poin, o.urutan FROM tbl_pertanyaan_penilaian p
JOIN (
  SELECT 'lama_usaha' k, 'Kurang dari 1 tahun' teks, 20 poin, 1 urutan UNION ALL
  SELECT 'lama_usaha', '1 sampai 3 tahun', 50, 2 UNION ALL
  SELECT 'lama_usaha', 'Lebih dari 3 sampai 5 tahun', 75, 3 UNION ALL
  SELECT 'lama_usaha', 'Lebih dari 5 tahun', 100, 4 UNION ALL
  SELECT 'pengalaman', 'Belum pernah', 20, 1 UNION ALL
  SELECT 'pengalaman', '1 sampai 2 pekerjaan', 60, 2 UNION ALL
  SELECT 'pengalaman', 'Lebih dari 2 pekerjaan', 100, 3 UNION ALL
  SELECT 'tenaga_kerja', '1 sampai 5 orang', 30, 1 UNION ALL
  SELECT 'tenaga_kerja', '6 sampai 20 orang', 60, 2 UNION ALL
  SELECT 'tenaga_kerja', 'Lebih dari 20 orang', 100, 3 UNION ALL
  SELECT 'mutu', 'Tidak ada', 20, 1 UNION ALL
  SELECT 'mutu', 'Memiliki prosedur internal', 60, 2 UNION ALL
  SELECT 'mutu', 'Memiliki sertifikasi', 100, 3
) o ON o.k = p.kode_pertanyaan
WHERE NOT EXISTS (SELECT 1 FROM tbl_opsi_jawaban x WHERE x.id_pertanyaan = p.id_pertanyaan);
