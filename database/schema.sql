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
  `status`       ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_code` (`student_code`),
  UNIQUE KEY `uq_students_nis` (`nis`),
  KEY `idx_students_name` (`name`),
  KEY `idx_students_jenjang_kelas` (`jenjang`,`kelas`),
  KEY `idx_students_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- savings_transactions  (ledger — sumber kebenaran saldo)
--   jenjang & kelas  : snapshot saat transaksi dibuat
--   period_*         : periode tabungan (bisa berbeda dari bulan transaction_date)
--   deleted_at       : soft delete. SEMUA query wajib memfilter deleted_at IS NULL
--                      (gunakan view v_student_balances / model untuk konsistensi)
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
-- View: saldo per santri (satu tempat untuk aturan soft delete)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_student_balances` AS
SELECT
  s.`id` AS `student_id`,
  COALESCE(SUM(CASE WHEN t.`mutation_type` = 'masuk'  THEN t.`amount` END), 0) AS `total_masuk`,
  COALESCE(SUM(CASE WHEN t.`mutation_type` = 'keluar' THEN t.`amount` END), 0) AS `total_keluar`,
  COALESCE(SUM(CASE WHEN t.`mutation_type` = 'masuk' THEN t.`amount` ELSE -t.`amount` END), 0) AS `saldo`,
  COUNT(t.`id`) AS `jumlah_transaksi`
FROM `students` s
LEFT JOIN `savings_transactions` t
  ON t.`student_id` = s.`id` AND t.`deleted_at` IS NULL
GROUP BY s.`id`;

-- ----------------------------------------------------------------------------
-- Data awal sistem (bukan data dummy)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `sync_state` (`scope`,`revision`) VALUES ('savings',1),('students',1);

INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`) VALUES
  ('school_name','Nama Lembaga'),
  ('allow_negative_balance','0');
