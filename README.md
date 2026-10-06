# Tabungan Santri

Sistem tabungan santri berbasis web — PHP Native (8.0+), MySQL/MariaDB, PDO, tanpa framework.

## Status

| Phase | Isi | Status |
|---|---|---|
| 1 | Analisis & arsitektur | ✅ |
| 2 | Database schema + seed | ✅ |
| 3 | Fondasi MVC (router, sesi, CSRF, DB, view, validator, error handler) | ✅ |
| 4 | Autentikasi (login, logout, throttle, role/izin, audit login) | ✅ |
| 5 | Design system (token, shell sidebar/topbar, komponen, login, panduan komponen, font self-host) | ✅ |
| 6 | Dashboard (ringkasan, grafik aktivitas, transaksi terbaru, polling realtime) | ✅ |
| 7 | Master Santri (daftar, cari, filter, sort, tambah/ubah/nonaktifkan, API pencarian) | ✅ |
| 8 | Form Tabungan (simpan transaksi masuk/keluar, aturan saldo, konfirmasi, panel "Baru dicatat") | ✅ |
| 9–10 | Realtime + Riwayat Tabungan (cari, filter, sort, paginasi, saldo berjalan, ringkasan, ubah/hapus) | ✅ |
| 11 | Detail Tabungan santri (profil mini, saldo, riwayat per santri, ubah/hapus, tautan dari nama) | ✅ |
| 12 | Laporan Tabungan (ringkasan, rekap per kelas/santri, grafik periode, ekspor Excel/CSV) | ✅ |
| 13 | Sistem cetak (Rekap per Nama sesuai contoh Excel, cetak massal per kelas; cetak laporan = format sementara) | ✅ |
| 14 | Audit log (halaman + filter: cari, modul, pengguna, tanggal, sort, paginasi) | ✅ |
| 15 | Manajemen Pengguna (tambah/ubah, peran, aktif/nonaktif, atur ulang kata sandi; khusus Super Admin) | ✅ |

## Instalasi (XAMPP)

1. Letakkan folder di `C:\xampp\htdocs\Tabungan_santri`. Aktifkan Apache dan MySQL.
2. Impor database (phpMyAdmin → Import, atau terminal):
   ```bash
   mysql -u root < database/schema.sql
   mysql -u root < database/seed.sql     # data dummy, hanya untuk testing
   ```
3. Cek `app/config/database.php` (default XAMPP: user `root`, tanpa password).
4. Buka <http://localhost/Tabungan_santri/> — cek <http://localhost/Tabungan_santri/health>.

Aplikasi juga dapat dibuka lewat `/Tabungan_santri/public/` atau virtual host yang menunjuk ke `public/`
(base path terdeteksi otomatis).

## Akun demo (hanya dari `seed.sql`)

| Username | Role |
|---|---|
| `superadmin` | Super Admin |
| `admin` | Admin |
| `operator` | Operator |

Ketiganya memakai satu kata sandi demo yang **sengaja tidak ditulis di repo publik**. Di komputer pengembang kata sandi itu ada di
`AKUN-DEMO.local.md` (berkas lokal, di-ignore Git). Bila berkas itu tidak ada, buat akun/kata sandi sendiri (lihat catatan di bawah).

> ⚠️ **Jangan impor `database/seed.sql` ke server sungguhan.** Berkas itu hanya untuk pengujian dan memuat akun demo dengan kata sandi bawaan.
> Di production: impor `schema.sql` saja, lalu buat akun Super Admin pertama dengan `password_hash()` (satu `INSERT` ke tabel `users`); pengguna lain dibuat lewat halaman **Pengguna**.

Login dapat memakai username **atau** email (tidak peka huruf besar/kecil).

## Autentikasi & izin

- Login di `/login`; 5 kegagalan per (akun + IP) atau 20 per IP dalam 15 menit mengunci sementara (`login_attempts`).
- ID sesi diganti saat login/logout (anti session fixation); sesi berakhir setelah 2 jam tanpa aktivitas (`app.session.idle_timeout`).
- Akun yang dinonaktifkan langsung terlempar keluar pada request berikutnya.
- Middleware route: `auth`, `guest`, `can:<izin>` — contoh: `$router->get('/x', [...], ['auth', 'can:savings.edit']);`
- Peta izin per role ada di `app/config/permissions.php`. Di view/controller: `can('savings.edit')`.
- Login berhasil/gagal/logout dicatat di `audit_logs` (modul `Auth`).

## Data uji

Santri **Ahmad Fauzan (SD 4A)** sengaja tanpa transaksi, untuk skenario uji:
Masuk 50.000 → 25.000 → Keluar 20.000 (saldo 55.000) → Keluar 100.000 ditolak.

## Struktur

```
public/        front controller + assets (satu-satunya folder yang dapat diakses web)
app/core/      Router, Request, Response, Session, Csrf, Database, View, Validator, ErrorHandler
app/controllers  app/models  app/services  app/middleware  app/views  app/helpers  app/config
routes/web.php daftar route
database/      schema.sql, seed.sql
storage/       log & sesi (di luar web)
```

## Design system

- Katalog komponen hidup: `/styleguide` (khusus Super Admin). **Lihat dulu di sana sebelum membuat UI baru.**
- CSS: `tokens.css` (satu-satunya tempat warna/ukuran) → `base.css` → `layout.css` → `components.css`. Jangan hardcode warna.
- Layout view: `layouts/app` (halaman login-only), `layouts/auth`, `layouts/plain` (error). Di view: `$this->set('heading', ...)`, `$this->set('lead', ...)`, section `actions`/`scripts`.
- Ikon: `icon('nama')` dari `public/assets/icons/sprite.svg` (tambah `<symbol>` baru bila perlu).
- JS (`App.*`): `api()` (CSRF otomatis, 401 → login), `toast()`, `confirm()`, `setLoading()`, `rupiah()`. Atribut: `data-money`, `data-bind-label`, `data-loading-text`, `data-confirm`.
- Menu sidebar: `app/config/navigation.php` (`ready => true` saat halaman selesai). Matikan `app.show_planned_menu` di Phase 17.
- Logo masih **placeholder** (`partials/brand.php`).

## Realtime (polling ringan)

- Setiap perubahan data memanggil `SyncState::bump('savings'|'students')` **di dalam transaksi DB yang sama** dengan perubahannya.
- Browser memanggil `GET /api/sync?scopes=...` tiap `app.sync_interval` ms (default 5 dtk) — hanya membaca 1 baris `sync_state`.
  Bila revisi berubah, baru data halaman diambil ulang. Polling berhenti saat tab tersembunyi, backoff saat error.
  Pakai di halaman baru: `App.watch(['savings'], function () { return refresh(); }, revisiAwal)`.
- Endpoint polling memanggil `Session::close()` (menghindari session lock) dan tidak memperpanjang sesi (header `X-Background-Poll`).

## Transaksi tabungan

- Halaman `/tabungan/tambah` (dapat diprasetel: `?student_id=4`). API: `POST /api/savings/create`, `GET /api/savings/balance?student_id=`, `GET /api/savings/recent`.
- **Aturan saldo** (`SavingsService::create`, satu transaksi DB): kunci baris santri (`SELECT … FOR UPDATE`) → keluar ditolak bila saldo < nominal
  ("Saldo santri tidak mencukupi.") → insert → periksa **saldo berjalan terendah ≥ 0** (menangkap transaksi bertanggal mundur) → bump revisi → audit.
  Dua penarikan serentak: tepat satu berhasil. Yang ditolak tidak meninggalkan baris maupun celah nomor transaksi.
- Nomor transaksi `TAB-YYYYMMDD-NNNN` (per **tanggal transaksi**), penghitung atomik di `transaction_sequences`.
- Nominal: diterima `50000`, `50.000`, `Rp 50.000`; ditolak huruf, minus, desimal, 0, dan > Rp 1 miliar. Disimpan integer.
- Tanggal tidak boleh melewati hari ini. **Tahun periode** diturunkan otomatis (tahun terdekat dengan tanggal: Des dibayar Jan 2026 → periode 2025).
- Jenjang & kelas disimpan sebagai snapshot di transaksi; boleh berbeda dari master (form menampilkan peringatan, tidak memblokir).
- Santri nonaktif tidak dapat menerima transaksi. Setelah simpan: tanggal, bulan, dan jenis mutasi dipertahankan untuk input beruntun.
- Combobox reusable: `App.Combobox` (`assets/js/combobox.js`) — dipakai lagi untuk filter santri di Riwayat.

## Sistem cetak

- **Rekap Tabungan per Nama** — `/print/rekap/{id}` (tombol "Cetak Rekap" di Detail Santri). Mengikuti contoh Excel: judul bergaris bawah (**TK → "Rekap Tabungan TK / KB"**, SD → "Rekap Tabungan SD"), blok NO. URUT / NAMA / DAWIS-BLOK, tabel NO · TGL · KETERANGAN · MASUK · KELUAR · TOTAL TABUNGAN (header peach, TOTAL = saldo berjalan), baris "Total Tabungan".
  - Default 12 baris/halaman (11 bernomor + 1 kosong seperti contoh); pilihan 10–40. Baris kosong untuk tulisan tangan dibuat sampai halaman penuh.
  - Lebih dari satu halaman: identitas & header diulang, halaman lanjutan diawali **"Saldo pindahan"**, ada "Halaman x / y".
  - Periode opsional (`from`/`to`): halaman pertama diawali **"Saldo awal per …"**.
  - Keterangan panjang dipotong **maks. 3 baris** di cetakan (tetap utuh di sistem); kapasitas halaman memperhitungkan panjang keterangan agar **satu lembar = satu halaman A4** (diverifikasi lewat PDF untuk semua pilihan baris).
- **Cetak massal satu kelas** — `/print/rekap?jenjang=&kelas=` (tombol "Cetak Rekap Kelas" di Data Santri saat filter jenjang/kelas aktif). Urut no. urut; santri tanpa transaksi ikut sebagai formulir kosong (bisa dilewati); nonaktif opsional; maks. 80 santri. Tanpa jenjang/kelas ditolak (422).
- **Cetak Laporan** — `/print/laporan` (tombol "Cetak" di Laporan): **format SEMENTARA**, menunggu contoh cetakan. Strukturnya siap: ubah `views/print/laporan.php`.
- Pratinjau di layar (kertas A4 potret, margin 15 mm) lalu tombol **Cetak**; bilah alat disembunyikan saat dicetak (`@media print`). Di dialog cetak: skala 100%, matikan "Header dan footer".
- Setiap cetak tercatat di audit log (modul `Cetak`) sekali per halaman. File: `public/assets/css/print.css`, `app/services/PrintService.php`, `app/views/print/`.

## Audit log

- Halaman `/audit` (menu Sistem → Audit Log); API `GET /api/audit/list`. Izin `audit.view`: **Admin ke atas** (operator ditolak 403, baik halaman maupun API).
- **Hanya baca**: aplikasi tidak menyediakan ubah/hapus catatan. Pencatatan lewat `AuditLog::record()` (kegagalan mencatat tidak menggagalkan aksi utama).
- Filter: cari (pengguna, aksi, keterangan, nomor referensi, IP — tiap kata harus cocok), modul, pengguna, dari/sampai tanggal (+ rentang cepat). Nilai tak sah **diabaikan**, bukan error; rentang terbalik ditukar. Rentang tanggal memakai `created_at >= dari AND created_at < sampai+1 hari` agar indeks terpakai.
- Sort: waktu (default terbaru), pengguna, modul, aksi. Paginasi 25/50/100 (default 50). Ringkasan (catatan, pengguna, modul, aktivitas terakhir) dihitung dari seluruh hasil filter.
- Nama pengguna disimpan sebagai snapshot di tiap catatan, jadi tetap terbaca walau akun diubah/dihapus. Tanpa polling realtime: tombol **Muat ulang**.
- Modul yang tercatat saat ini: `Auth`, `Tabungan`, `Santri`, `Laporan`, `Cetak`. Modul baru otomatis muncul di dropdown filter.
- File: `app/models/AuditLog.php`, `app/services/AuditService.php`, `app/controllers/AuditController.php`, `app/views/audit/index.php`, `public/assets/js/audit.js`.

## Manajemen pengguna

- Halaman `/pengguna` (menu Sistem → Pengguna); API `GET/POST /api/users`, `PUT /api/users/{id}`, `PUT /api/users/{id}/status`, `PUT /api/users/{id}/password`. Izin `users.manage`: **hanya Super Admin** (403 untuk yang lain).
- Daftar: cari (nama/username/email), filter peran & status, sort (nama, username, peran, login terakhir, status), paginasi 10/25/50. Hash kata sandi tidak pernah dikirim ke browser.
- **Username**: 3–50 karakter, huruf kecil/angka/`.`/`_`/`-` (disimpan huruf kecil; login tidak peka huruf besar/kecil). Email opsional, unik, dapat dipakai login. Duplikat ditolak sebagai galat kolom.
- **Kata sandi**: minimal 8 karakter, maksimal 72 byte (batas bcrypt), tidak boleh sama dengan username. Tidak pernah dicatat di audit log. Atur ulang kata sandi tidak mengakhiri sesi yang sedang berjalan milik pengguna itu.
- **Pengaman** (ditegakkan di server): akun sendiri tidak dapat dinonaktifkan atau diubah perannya; Super Admin aktif **terakhir** tidak dapat dinonaktifkan/diturunkan (dicek di dalam transaksi dengan `SELECT … FOR UPDATE`, jadi aman terhadap dua perubahan serentak).
- Pengguna yang dinonaktifkan terlempar pada request berikutnya; perubahan peran berlaku seketika (peran dimuat ulang dari DB tiap request).
- Semua aksi tercatat di Audit Log (modul `Pengguna`).
- File: `app/models/User.php`, `app/services/UserService.php`, `app/controllers/UserController.php`, `app/views/users/index.php`, `public/assets/js/users.js`.

## Laporan & ekspor

- Halaman `/laporan`; API `GET /api/reports/summary` (ringkasan + rekap per kelas + grafik) dan `GET /api/reports/students` (rekap per santri, berhalaman). Filter = aturan yang sama dengan Riwayat (`Savings::listWhere`), jadi angka di Riwayat, Laporan, dan berkas ekspor selalu sama.
- **Saldo** pada ringkasan = masuk − keluar pada filter. Kolom **Saldo per tanggal** di rekap per santri = saldo ledger santri sampai tanggal "Sampai" (atau saat ini); tidak terpengaruh filter mutasi/bulan.
- Rekap **per kelas** memakai kelas pada transaksi (snapshot); rekap **per santri** memakai kelas master.
- Grafik otomatis: harian (≤ 62 hari), mingguan (≤ 30 minggu), selain itu bulanan.
- **Ekspor** `GET /laporan/export?dataset=transaksi|santri&format=xlsx|csv` + filter (izin `reports.export`: Admin ke atas; operator hanya melihat). Maks. 50.000 baris (pesan meminta mempersempit filter). Setiap ekspor tercatat di audit log.
  - `.xlsx` ditulis tanpa pustaka (`app/services/export/XlsxWriter.php`): tanggal & uang berupa angka asli, baris total, filter otomatis, baris beku. Sudah diuji dibuka di Microsoft Excel.
  - `.csv`: UTF-8 + BOM, pemisah koma. Teks yang diawali `= + - @` diberi apostrof di depan (cegah formula injection).
  - Menambah format baru (mis. PDF): buat penulis yang menerima `TableExport`, lalu daftarkan di `ReportController::export`.
- Tombol **Cetak** ditambahkan di Phase 13 (format cetak laporan menunggu contoh dari pengguna).

## Detail tabungan santri

- Halaman `/tabungan/santri/{id}` (nama santri di Data Santri, Riwayat, dan Dashboard menautkan ke sini). API profil: `GET /api/savings/student/{id}`.
- Memuat profil mini (nama, jenjang, kelas, ID, NIS, no. urut, dawis/blok, status), **Saldo Saat Ini**, total masuk/keluar, dan riwayat transaksi santri dengan saldo berjalan.
- Daftar memakai `GET /api/savings/list` dengan `student_id` **dipaksa dari URL** (query `?student_id=` tidak bisa membelokkan ke santri lain).
- Santri nonaktif tetap dapat dilihat (riwayat & saldo utuh); tombol "Tambah Tabungan" disembunyikan dan muncul catatan.
- Ubah/hapus memakai modul bersama `App.SavingsActions` (`assets/js/savings-actions.js` + `partials/savings_edit_dialog.php`), sama dengan halaman Riwayat.
- Halaman terhubung ke realtime (profil + daftar). Tombol **Cetak Rekap** ditambahkan di Phase 13.

## Riwayat tabungan

- Halaman `/tabungan`; API `GET /api/savings/list`, `PUT /api/savings/{id}`, `DELETE /api/savings/{id}`.
- Filter: cari (nama, keterangan, nomor transaksi, kelas), santri (combobox, termasuk nonaktif), jenjang, kelas (mengikuti jenjang), bulan, tahun, mutasi, dari/sampai (+ rentang cepat). Nilai filter tak sah **diabaikan**, bukan error; rentang terbalik ditukar.
- **Ringkasan** (jumlah, masuk, keluar, selisih) dihitung dari SELURUH hasil filter, bukan hanya halaman yang tampil.
- **Saldo per baris** = saldo santri setelah transaksi itu, dihitung dari seluruh ledger santri (window function) — tetap benar walau daftar difilter. Hanya santri yang tampil di halaman yang dihitung.
- Urutan jenjang/mutasi mengikuti urutan `ENUM` (TK < SD, masuk < keluar), bukan abjad.
- **Edit/hapus** (izin `savings.edit`/`savings.delete`, Admin ke atas): santri tidak bisa dipindah; kode transaksi tetap; hapus = soft delete.
  Keduanya DITOLAK bila saldo berjalan santri menjadi negatif pada tanggal mana pun (pesan menyebut tanggalnya). Semua tercatat di audit log dengan ringkasan perubahan.
- Tampilan ponsel: filter sekunder dilipat di balik tombol "Filter lainnya" (lencana = jumlah filter aktif).

## Master santri

- Halaman `/santri`; API: `GET /api/students` (daftar), `GET /api/students/search` (pilihan santri), `POST/PUT /api/students`, `PUT /api/students/{id}/status`.
- Pencarian: tiap kata harus cocok dengan salah satu dari nama, ID, NIS, kelas, jenjang, dawis/blok (`ahmad 4a` → Ahmad Fauzan). Karakter `%` `_` di-escape.
- Santri **tidak dihapus** (ada transaksi terkait); gunakan **Nonaktifkan**. Santri nonaktif tidak muncul di `search` (kecuali `status=semua`) dan tidak di form transaksi baru.
- Duplikat (nama + jenjang + kelas sama) ditolak. ID otomatis berurutan (`001`, `002`, …) atau diisi manual saat tambah; tidak bisa diubah setelahnya.
- Mengubah jenjang/kelas **tidak** mengubah transaksi lama (snapshot di `savings_transactions`).
- Helper tabel reusable: `App.Table.pager/sortHeaders/skeleton` (`assets/js/table.js`).

## Grafik

- `App.Chart.columns(host, {series, ...})` di `assets/js/chart.js` (kolom berkelompok SVG): legenda, tooltip hover/fokus, label puncak, tabel pendamping (`App.Chart.table`).
- Warna seri `--chart-1` (hijau) & `--chart-2` (amber) divalidasi buta warna. Jangan ganti tanpa menjalankan `validate_palette`.
- Font: Plus Jakarta Sans & Fraunces (subset Latin, lisensi SIL OFL) di `public/assets/fonts/`.

## Konvensi penting

- **Saldo** selalu dihitung dari ledger (`SUM masuk − SUM keluar`), lihat view `v_student_balances`. Tidak ada kolom saldo.
- **Soft delete**: semua query transaksi wajib memfilter `deleted_at IS NULL`.
- **CSRF** otomatis untuk semua POST/PUT/PATCH/DELETE (field `_csrf` atau header `X-CSRF-Token`).
- **CSP**: tidak ada `<script>` inline. Kirim data ke JS lewat atribut `data-*`.
- **Output** di view selalu lewat `e()`.
- **Jangan pakai kode HTTP non-standar** (mis. 419): Apache mengubahnya jadi 500. CSRF gagal pada API = 403.
- **Error**: detail hanya di `storage/logs/`; pengguna melihat pesan generik. `app.debug` harus `false` di production.
- Kode harus kompatibel **PHP 8.0** (tanpa enum, readonly property, dsb.).
