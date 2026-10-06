-- ============================================================================
-- Revisi 1: kenaikan kelas, alumni, arsip santri, jejak penghapus transaksi
-- Non-destruktif: hanya menambah kolom/tabel. Tidak ada data yang dihapus/diubah
-- (kecuali mengisi deleted_by dari updated_by untuk transaksi yang SUDAH terhapus).
-- Dijalankan lewat:  php tools/migrate.php
-- ============================================================================

-- santri: status alumni, data kelulusan, arsip (soft delete)
ALTER TABLE `students`
  MODIFY COLUMN `status` ENUM('aktif','nonaktif','alumni') NOT NULL DEFAULT 'aktif',
  ADD COLUMN `graduated_at` DATE NULL AFTER `status`,
  ADD COLUMN `graduation_year` SMALLINT UNSIGNED NULL AFTER `graduated_at`,
  ADD COLUMN `graduation_academic_year` VARCHAR(9) NULL AFTER `graduation_year`,
  ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`,
  ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`,
  ADD KEY `idx_students_graduation` (`graduation_year`),
  ADD KEY `idx_students_deleted` (`deleted_at`);

-- transaksi: siapa yang menghapus (soft delete)
ALTER TABLE `savings_transactions`
  ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`;

UPDATE `savings_transactions` SET `deleted_by` = `updated_by` WHERE `deleted_at` IS NOT NULL AND `deleted_by` IS NULL;

-- satu baris per proses kenaikan satu kelas pada satu tahun ajaran
CREATE TABLE IF NOT EXISTS `class_promotions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_year`     VARCHAR(9)  NOT NULL COMMENT 'tahun ajaran asal, mis. 2025/2026',
  `to_year`       VARCHAR(9)  NOT NULL COMMENT 'tahun ajaran tujuan, mis. 2026/2027',
  `jenjang_asal`  ENUM('TK','SD') NOT NULL,
  `kelas_asal`    VARCHAR(20) NOT NULL,
  `jenjang_tujuan` ENUM('TK','SD') NULL COMMENT 'NULL = lulus',
  `kelas_tujuan`  VARCHAR(20) NULL COMMENT 'NULL = lulus',
  `count_naik`    INT UNSIGNED NOT NULL DEFAULT 0,
  `count_tinggal` INT UNSIGNED NOT NULL DEFAULT 0,
  `count_lulus`   INT UNSIGNED NOT NULL DEFAULT 0,
  `processed_by`  INT UNSIGNED NOT NULL,
  `processed_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promotion_class_year` (`from_year`,`jenjang_asal`,`kelas_asal`),
  KEY `idx_promotion_processed` (`processed_at`),
  CONSTRAINT `fk_promotion_user` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- riwayat kelas per santri (tidak pernah diubah/dihapus aplikasi)
CREATE TABLE IF NOT EXISTS `student_class_history` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       INT UNSIGNED NOT NULL,
  `promotion_id`     INT UNSIGNED NOT NULL,
  `from_year`        VARCHAR(9)  NOT NULL,
  `academic_year`    VARCHAR(9)  NOT NULL COMMENT 'tahun ajaran tujuan',
  `previous_jenjang` ENUM('TK','SD') NOT NULL,
  `previous_class`   VARCHAR(20) NOT NULL,
  `new_jenjang`      ENUM('TK','SD') NULL COMMENT 'NULL = lulus',
  `new_class`        VARCHAR(20) NULL COMMENT 'NULL = lulus',
  `promotion_status` ENUM('naik','tidak_naik','lulus') NOT NULL,
  `processed_by`     INT UNSIGNED NOT NULL,
  `processed_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_history_student_year` (`student_id`,`from_year`),
  KEY `idx_history_promotion` (`promotion_id`),
  CONSTRAINT `fk_history_student`   FOREIGN KEY (`student_id`)   REFERENCES `students` (`id`)          ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_history_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `class_promotions` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_history_user`      FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`)             ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
