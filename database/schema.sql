-- ============================================================================
--  TABUNGAN SANTRI — Database schema
--  Engine   : InnoDB, utf8mb4
--  Target   : MariaDB 10.4+ / MySQL 8+ (membutuhkan window function & CHECK)
--  Import   : phpMyAdmin > Import, atau  mysql -u root < database/schema.sql
--  Aman dijalankan ulang (CREATE ... IF NOT EXISTS), tidak menghapus data.
-- ============================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `tabungan_santri`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tabungan_santri`;

-- ----------------------------------------------------------------------------
-- users
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(150) NULL,
  `password`      VARCHAR(255) NOT NULL COMMENT 'password_hash()',
  `role`          ENUM('super_admin','admin','operator') NOT NULL DEFAULT 'operator',
  `status`        ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `last_login_at` DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- students  (master santri)
--   no_urut    : "NO. URUT" pada cetakan rekap
--   dawis_blok : "DAWIS / BLOK" pada cetakan rekap
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_code` VARCHAR(20)  NOT NULL,
  `nis`          VARCHAR(30)  NULL,
  `no_urut`      INT UNSIGNED NULL,
  `name`         VARCHAR(100) NOT NULL,
  `jenjang`      ENUM('TK','SD') NOT NULL,
  `kelas`        VARCHAR(20)  NOT NULL,
  `dawis_blok`   VARCHAR(100) NULL,
  `status`       ENUM('aktif','nonaktif','alumni') NOT NULL DEFAULT 'aktif',
  `graduated_at` DATE NULL,
  `graduation_year` SMALLINT UNSIGNED NULL,
  `graduation_academic_year` VARCHAR(9) NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`   DATETIME NULL COMMENT 'arsip (soft delete)',
  `deleted_by`   INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_code` (`student_code`),
  UNIQUE KEY `uq_students_nis` (`nis`),
  KEY `idx_students_name` (`name`),
  KEY `idx_students_jenjang_kelas` (`jenjang`,`kelas`),
  KEY `idx_students_status` (`status`),
  KEY `idx_students_graduation` (`graduation_year`),
  KEY `idx_students_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- savings_transactions  (ledger — sumber kebenaran saldo)
--   jenjang & kelas  : snapshot saat transaksi dibuat
--   period_*         : periode tabungan (bisa berbeda dari bulan transaction_date)
--   deleted_at       : soft delete. SEMUA query wajib memfilter deleted_at IS NULL
--                      (gunakan Database::STUDENT_BALANCES / model untuk konsistensi)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `savings_transactions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_code` VARCHAR(25) NOT NULL COMMENT 'TAB-YYYYMMDD-NNNN',
  `student_id`       INT UNSIGNED NOT NULL,
  `transaction_date` DATE NOT NULL,
  `period_month`     TINYINT UNSIGNED NOT NULL,
  `period_year`      SMALLINT UNSIGNED NOT NULL,
  `jenjang`          ENUM('TK','SD') NOT NULL,
  `kelas`            VARCHAR(20) NOT NULL,
  `mutation_type`    ENUM('masuk','keluar') NOT NULL,
  `amount`           BIGINT UNSIGNED NOT NULL COMMENT 'rupiah, integer',
  `description`      VARCHAR(255) NOT NULL,
  `created_by`       INT UNSIGNED NOT NULL,
  `updated_by`       INT UNSIGNED NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME NULL,
  `deleted_by`       INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tx_code` (`transaction_code`),
  KEY `idx_tx_student_date` (`student_id`,`transaction_date`,`id`),
  KEY `idx_tx_date` (`transaction_date`),
  KEY `idx_tx_jenjang` (`jenjang`),
  KEY `idx_tx_kelas` (`kelas`),
  KEY `idx_tx_mutation` (`mutation_type`),
  KEY `idx_tx_period` (`period_year`,`period_month`),
  KEY `idx_tx_deleted` (`deleted_at`),
  KEY `idx_tx_created_by` (`created_by`),
  KEY `idx_tx_date_class` (`transaction_date`,`jenjang`,`kelas`),
  CONSTRAINT `chk_tx_amount` CHECK (`amount` > 0),
  CONSTRAINT `chk_tx_month`  CHECK (`period_month` BETWEEN 1 AND 12),
  CONSTRAINT `fk_tx_student`    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_tx_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_tx_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- transaction_sequences  (penomoran TAB-YYYYMMDD-NNNN yang atomik)
--   Dipakai:  INSERT INTO transaction_sequences (seq_date,last_no) VALUES (?,LAST_INSERT_ID(1))
--             ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1);
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaction_sequences` (
  `seq_date` DATE NOT NULL,
  `last_no`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- sync_state  (penghitung revisi untuk polling realtime — 1 baris per scope)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sync_state` (
  `scope`      VARCHAR(30) NOT NULL,
  `revision`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- audit_logs
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NULL,
  `user_name`    VARCHAR(100) NOT NULL COMMENT 'snapshot nama saat aksi terjadi',
  `action`       VARCHAR(100) NOT NULL,
  `module`       VARCHAR(50)  NOT NULL,
  `reference_id` VARCHAR(50)  NULL,
  `description`  VARCHAR(500) NULL,
  `ip_address`   VARCHAR(45)  NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_module_date` (`module`,`created_at`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_reference` (`reference_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- login_attempts  (pembatasan percobaan login)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`   VARCHAR(150) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_identifier` (`identifier`,`attempted_at`),
  KEY `idx_login_ip` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- settings  (key/value)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Kenaikan kelas & riwayat kelas (Revisi 1)
-- ----------------------------------------------------------------------------
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
  KEY `idx_history_student_date` (`student_id`,`processed_at`),
  CONSTRAINT `fk_history_student`   FOREIGN KEY (`student_id`)   REFERENCES `students` (`id`)          ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_history_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `class_promotions` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_history_user`      FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`)             ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- schema_migrations: instalasi baru dari berkas ini sudah mencakup migrasi 001 & 002
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` VARCHAR(100) NOT NULL PRIMARY KEY,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('001_revisi1_kenaikan_alumni'), ('002_revisi2_indeks_dashboard');

-- Saldo per santri: subkueri Database::STUDENT_BALANCES (bukan VIEW; hosting gratis menolak CREATE VIEW)

-- ----------------------------------------------------------------------------
-- Data awal sistem (bukan data dummy)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `sync_state` (`scope`,`revision`) VALUES ('savings',1),('students',1);

INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`) VALUES
  ('school_name','Nama Lembaga'),
  ('allow_negative_balance','0');
