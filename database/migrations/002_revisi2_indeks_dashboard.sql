-- ============================================================================
-- Revisi 2: indeks untuk Dashboard "Saldo Tabungan Setiap Kelas"
-- Hanya menambah indeks (tidak mengubah/menghapus data).
-- ============================================================================

-- agregasi mutasi per periode & kelas (WHERE transaction_date BETWEEN ... GROUP BY jenjang, kelas)
ALTER TABLE `savings_transactions`
  ADD KEY `idx_tx_date_class` (`transaction_date`, `jenjang`, `kelas`);

-- kelas santri pada tanggal tertentu (peristiwa kenaikan terakhir sebelum tanggal itu)
ALTER TABLE `student_class_history`
  ADD KEY `idx_history_student_date` (`student_id`, `processed_at`);
