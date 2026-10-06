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
| 12 | Laporan Tabungan (ringkasan, rekap per kelas/santri, grafik periode, ekspor Excel & PDF) | ✅ |
| 13 | Sistem cetak (Rekap per Nama sesuai contoh Excel, cetak massal per kelas; cetak laporan = format sementara) | ✅ |
| 14 | Audit log (halaman + filter: cari, modul, pengguna, tanggal, sort, paginasi) | ✅ |
| 15 | Manajemen Pengguna (tambah/ubah, peran, aktif/nonaktif, atur ulang kata sandi; khusus Super Admin) | ✅ |
| 16 | Pengaturan (nama lembaga + unggah logo; khusus Super Admin) | ✅ |
| 17 | Finalisasi: menu "Segera" dimatikan, header keamanan, alat produksi (`tools/`), uji regresi lintas peran, checklist produksi | ✅ |

**Semua phase selesai — versi final 1.0.**

| Revisi | Isi | Status |
|---|---|---|
| 1 | Kenaikan Kelas (review → pilih → konfirmasi → proses, riwayat kelas, anti-ganda), Tabungan Alumni (Tarik Data Detail/Rekap), hapus massal (transaksi = soft delete, santri = arsip), tombol Kembali di semua halaman, responsif total (tabel → kartu, tanpa overflow), Panduan Komponen dihapus | ✅ |
| 2 | Dashboard **Saldo Tabungan Setiap Kelas**: filter jenjang + periode (hari/bulan/tahun), Saldo Awal + Masuk − Keluar ± Pindah kelas = Saldo Akhir, total keseluruhan, realtime, responsif | ✅ |

## Instalasi (XAMPP)

1. Letakkan folder di `C:\xampp\htdocs\Tabungan_santri`. Aktifkan Apache dan MySQL.
2. Impor database (phpMyAdmin → Import, atau terminal):
   ```bash
   mysql -u root < database/schema.sql
   mysql -u root < database/seed.sql     # data dummy, hanya untuk testing
   ```
   **Memperbarui database yang sudah berisi data** (tanpa menghapus apa pun): cadangkan dulu, lalu jalankan migrasi.
   ```bash
   php tools/backup.php
   php tools/migrate.php
   ```
   Migrasi (`database/migrations/*.sql`; terakhir `002_revisi2_indeks_dashboard` = indeks saja) hanya menambah kolom/tabel/indeks, tercatat di tabel `schema_migrations`, dan aman dijalankan ulang.
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

## Dashboard — Saldo Tabungan Setiap Kelas

- Section di Dashboard (di bawah kartu ringkasan): filter **Jenjang** (Semua/TK/SD) + **Periode** (Hari / Bulan / Tahun). Hanya field yang relevan tampil: Hari → tanggal; Bulan → bulan + tahun; Tahun → tahun. Bawaan = **bulan berjalan**, semua jenjang. Tombol **Tampilkan** menerapkan, **Reset** mengembalikan ke bawaan; filter yang diterapkan ikut di URL.
- Tampilan: kartu total (Saldo Akhir besar + persamaan periode), lalu per jenjang (TK, SD) kartu kecil tiap kelas: **Saldo Akhir**, Masuk, Keluar, Awal. Kelas tanpa data tetap tampil Rp 0; periode tanpa transaksi menampilkan "Belum ada data transaksi pada periode ini." Skeleton saat memuat; di ponsel satu kolom.
- **Rumus** (per kelas dan total): `Saldo Awal Periode + Mutasi Masuk − Mutasi Keluar ± Pindah kelas = Saldo Akhir Periode`. Saldo Awal = saldo pada akhir hari sebelum periode; Saldo Akhir = saldo pada akhir periode (bukan sekadar jumlah mutasi). "Pindah kelas" hanya muncul bila ada kenaikan/kelulusan pada periode itu, supaya angkanya tidak menyesatkan.
- **Histori kelas aman** (`app/models/ClassBalance.php`, tanpa tabel baru): mutasi dikelompokkan menurut kelas *saat transaksi* (snapshot `jenjang`+`kelas` di baris transaksi), jadi transaksi lama tidak pindah kelas karena master santri berubah. Saldo pada suatu tanggal dikelompokkan menurut kelas santri *pada tanggal itu* (peristiwa terakhir antara transaksi terakhir dan kenaikan kelas terakhir di `student_class_history`). Laporan periode lampau tetap sama setelah kenaikan kelas.
- **Alumni** tidak dihitung sebagai kelas aktif sejak tanggal lulus (`graduated_at`); sebelum itu masih terhitung di kelasnya. Saldo/transaksinya tetap utuh di Tabungan Alumni. Transaksi soft-delete (`deleted_at`) tidak pernah dihitung. Total seluruh kelas pada hari ini = "Total Saldo" kartu dashboard = saldo ledger santri non-alumni.
- **Kelas tidak di-hardcode**: daftar kelas = kelas santri aktif + kelas yang muncul di data + saran bawaan (TK A/B, 1A–6A); jenjang baru otomatis membentuk kelompok baru.
- API `GET /api/dashboard/classes?jenjang=&period=day|month|year&date=&month=&year=` (izin `dashboard.view`). Parameter tak sah diganti nilai bawaan; query berparameter. Agregasi `SUM … GROUP BY` di database; indeks `idx_tx_date_class` dan `idx_history_student_date` (migrasi 002). ±0,3–0,5 dtk pada 40.000 transaksi.
- **Realtime**: dashboard.js memanggil `App.ClassBalances.reload()` tiap polling mendeteksi perubahan (transaksi baru/hapus/kenaikan kelas) dengan filter yang sedang diterapkan — tanpa refresh manual (±5 dtk).

## Kenaikan kelas

- Menu **Santri → Kenaikan Kelas** (`/santri/kenaikan`), izin `promotions.manage` (Admin ke atas). **Tidak pernah otomatis**: Pilih kelas → Tinjau & tentukan status → Pratinjau/konfirmasi → Proses.
- Tahun ajaran (Juli–Juni): "Tahun Ajaran Asal" dapat dipilih (bawaan = tahun ajaran yang baru berakhir/akan berakhir), tujuan = +1 tahun. Satu proses = satu kelas (`jenjang` + `kelas`).
- Tangga kelas (`app/services/ClassLadder.php`): SD `1A → 2A … 5A → 6A` (rombel dipertahankan; kelas tujuan boleh diubah selama tingkatnya benar); TK `TK A → TK B`, `TK B → SD 1A`; format kelas lain tidak dapat dinaikkan otomatis.
- **SD kelas 6 + Naik = LULUS** → status `alumni` (tidak pernah "kelas 7"). **Tidak naik** → kelas tidak berubah. Semua santri di daftar wajib punya status sebelum tombol Proses aktif; kotak centang hanya untuk menandai massal ("Tandai Naik / Tidak Naik").
- Satu transaksi DB: semua santri berubah atau tidak sama sekali. Transaksi tabungan **tidak diubah/dipindah/diduplikasi**; saldo tetap dari ledger yang sama.
- **Anti-ganda** (dijaga di database): `UNIQUE (tahun asal, jenjang, kelas)` pada `class_promotions` dan `UNIQUE (santri, tahun asal)` pada `student_class_history` → kelas yang sama tidak bisa diproses dua kali, dan santri yang baru naik tidak ikut dinaikkan lagi di tahun yang sama. Dua operator bersamaan: satu berhasil, satu menerima "sudah diproses".
- Riwayat: `student_class_history` (tahun asal/tujuan, kelas sebelum/sesudah, status naik/tidak_naik/lulus, pemroses, waktu) — tampil sebagai **Riwayat Kelas** di detail santri/alumni; tabel **Riwayat Proses** di halaman Kenaikan Kelas; audit log modul `Kenaikan Kelas` (jumlah naik/lulus/tidak naik).
- Santri nonaktif tidak ikut proses. Mengubah kelas manual di Data Santri tidak membuat riwayat kenaikan.

## Tabungan alumni

- Alumni = santri berstatus `alumni` (`graduated_at`, `graduation_year`, `graduation_academic_year`; jenjang & kelas terakhir tetap di baris santri). **Tidak ada tabel transaksi baru**: transaksi tetap di ledger yang sama dan saldo tetap dari `v_student_balances` → mustahil hitung ganda.
- Menu **Tabungan → Tabungan Alumni** (`/tabungan/alumni`) dan **Laporan → Rekap Alumni** (`/laporan/alumni`); izin `alumni.view` (semua peran). **Tarik Data**: *Detail* (tabel per alumni → halaman detail dengan seluruh riwayat transaksi + riwayat kelas) atau *Rekap* (ringkasan + tabel per tahun lulus, atau per kelas terakhir bila satu tahun dipilih). Filter: tahun lulus, tahun ajaran, cari.
- Alumni **tidak muncul** di Data Santri, pilihan form tabungan, dan proses kenaikan; transaksi baru untuk alumni ditolak server. Alumni tidak dapat diubah/dinonaktifkan dari Data Santri.
- Dashboard: "Total Saldo" hanya santri (bukan alumni); saldo alumni ditampilkan terpisah di kartu yang sama. Riwayat & Laporan tetap berbasis ledger (transaksi alumni sebelum lulus tetap ikut pada periodenya).

## Hapus massal

- Tombol **Pilih** → kotak centang muncul → **Pilih Semua** (hanya halaman yang tampil) → **Hapus Terpilih (n)** → dialog konfirmasi. Modul bersama `App.Bulk` (`assets/js/bulk.js`). Ada di **Riwayat Tabungan**, **Detail Tabungan/Alumni**, dan **Data Santri**.
- **Transaksi** (`POST /api/savings/bulk-delete`, izin `savings.delete`): *soft delete* (`deleted_at` + `deleted_by`), tidak dihitung dalam saldo, tercatat di audit log per transaksi. **Semua-atau-tidak-sama-sekali**: ditolak seluruhnya bila ada ID yang tidak ada/sudah terhapus, atau bila hasilnya membuat saldo santri negatif. Maks. 200 ID; semua ID divalidasi (bilangan bulat positif), query `WHERE id IN (…)` berparameter.
- **Santri** (`POST /api/students/bulk-delete`, izin `students.manage`): **arsip** (`deleted_at`), data/riwayat kelas/transaksi tetap ada. Santri yang masih bersaldo atau alumni dilewati dan dilaporkan satu per satu.
- Tidak disediakan untuk Audit Log (tidak boleh diubah), Pengguna (nonaktifkan saja; terhubung ke transaksi), dan tabel ringkasan/laporan.

## Tombol Kembali & responsif

- **← Kembali** di semua halaman (kecuali Dashboard): kembali ke halaman asal bila pengunjung datang dari halaman lain di aplikasi (riwayat browser, filter ikut terjaga); bila dibuka langsung, menuju halaman induk (`back_target()` di `app/helpers/functions.php`).
- Desktop: sidebar; < 1024 px: topbar `Logo … ☰` dengan sidebar *off-canvas* yang menutup setelah memilih menu. Form 1 kolom di ponsel, modal maksimal selebar layar dan dapat di-scroll, judul memakai `clamp()`.
- **Tidak ada overflow horizontal halaman.** Audit otomatis: `tools/responsive-audit.js` (tempel di konsol browser yang sudah login) membandingkan `scrollWidth` dengan lebar tampilan untuk 11 lebar (360–1920 px) dan melaporkan elemen yang keluar bingkai: `await __auditPages(['/dashboard','/tabungan','/santri'])`.

## Hosting tanpa SSH (mis. InfinityFree)

`php tools/package-hosting.php` membuat folder `deploy/` (di-ignore Git): isi `htdocs/` siap diunggah lewat FTP, `hosting-schema.sql` (tanpa CREATE DATABASE), `hosting-admin.sql` (Super Admin awal dengan kata sandi sementara acak), dan `PANDUAN-HOSTING.md` (langkah demi langkah). Seed/akun demo tidak ikut; kredensial lokal tidak ikut — `app/config/database.php` berisi placeholder untuk diisi di komputer Anda sebelum unggah.

## Menjalankan di produksi (checklist)

1. Instalasi baru: impor **`database/schema.sql` saja** (jangan `seed.sql`). Memperbarui database lama: `php tools/backup.php` lalu `php tools/migrate.php`. Salin `app/config/database.example.php` → `database.php`, isi kredensial MySQL khusus aplikasi (bukan `root`).
2. Buat Super Admin pertama dari terminal (kata sandi ditanya interaktif, tidak masuk riwayat perintah):
   ```bash
   php tools/create-admin.php superadmin "Nama Admin"
   ```
   Pengguna lain dibuat lewat halaman **Pengguna**. Hapus `AKUN-DEMO.local.md` bila ada.
3. `app/config/app.php`: pastikan `debug => false`, `show_planned_menu => false`, `timezone` benar.
4. Arahkan web server ke folder `public/` (atau biarkan `.htaccess` akar yang mengarahkan). Aktifkan **HTTPS** — cookie sesi otomatis `Secure` dan header HSTS dikirim saat HTTPS.
5. Folder `storage/` (`logs`, `sessions`, `uploads`, `backups`) harus dapat ditulis web server dan **tidak** terbuka ke web.
6. Cek `GET /health` → `{"success":true}`.
7. Jadwalkan cadangan harian (Windows Task Scheduler / cron):
   ```bash
   php tools/backup.php --keep=14
   ```
   Hasil di `storage/backups/` (`.sql` + salinan logo); yang melebihi `--keep` terbaru dihapus otomatis. Salin juga ke media lain di luar server. Pulihkan dengan `mysql -u USER -p tabungan_santri < berkas.sql`.
8. Periksa `storage/logs/` berkala; detail galat hanya ada di sana.

## Keamanan (ringkas)

- Semua POST/PUT/DELETE wajib token CSRF; sesi: `HttpOnly`, `SameSite=Lax`, `Secure` (HTTPS), ID diganti saat login/logout, kedaluwarsa 2 jam.
- Login dibatasi (throttle per akun+IP dan per IP). Kata sandi `password_hash()` (bcrypt); tidak pernah dicatat/dikirim ke browser.
- Izin per peran ditegakkan di server (route middleware) — halaman **dan** API. Query memakai prepared statement; output di view lewat `e()`.
- CSP ketat (tanpa script inline), `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store`.
- Saldo selalu dari ledger dan dijaga di dalam transaksi DB (`SELECT … FOR UPDATE`); transaksi dihapus secara *soft delete*; semua aksi penting masuk Audit Log.

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
database/      schema.sql, seed.sql, migrations/
tools/         create-admin.php, backup.php, migrate.php, package-hosting.php (khusus CLI), responsive-audit.js (uji overflow)
storage/       log & sesi (di luar web)
```

## Design system

- Pakai ulang komponen yang sudah ada di `components.css` (tombol, kartu, badge, tabel, modal, dsb.) dan token di `tokens.css`. Halaman katalog komponen sudah dihapus.
- CSS: `tokens.css` (satu-satunya tempat warna/ukuran) → `base.css` → `layout.css` → `components.css`. Jangan hardcode warna.
- Layout view: `layouts/app` (halaman login-only), `layouts/auth`, `layouts/plain` (error). Di view: `$this->set('heading', ...)`, `$this->set('lead', ...)`, section `actions`/`scripts`.
- Ikon: `icon('nama')` dari `public/assets/icons/sprite.svg` (tambah `<symbol>` baru bila perlu).
- JS (`App.*`): `api()` (CSRF otomatis, 401 → login), `toast()`, `confirm()`, `setLoading()`, `rupiah()`. Atribut: `data-money`, `data-bind-label`, `data-loading-text`, `data-confirm`.
- Tabel data: beri kelas `table table-stack` dan `data-stack="title|full|actions"` pada `<th>`; label sel diisi otomatis dari `<th>` (`App.Table.stack`). Saat kontainer tabel < 800 px tabel tampil sebagai **kartu** (tanpa scroll horizontal).
- Menu sidebar: `app/config/navigation.php` (`ready => true` saat halaman selesai). `app.show_planned_menu` sudah `false` (final); item baru yang belum siap bisa ditandai `ready => false`.
- Logo: placeholder bawaan sampai Super Admin mengunggah logo di **Pengaturan** (`partials/brand.php`).

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

## Pengaturan

- Halaman `/pengaturan` (menu Sistem → Pengaturan); API `PUT /api/settings`. Izin `settings.manage`: **hanya Super Admin**.
- **Nama lembaga** (wajib, maks. 100 karakter) tampil di sidebar dan halaman login. Disimpan di tabel `settings` (`school_name`).
- **Logo**: pilih PNG/JPG/WEBP/SVG (maks. 5 MB). Browser menggambarnya ke canvas, memperkecil hingga **256 px**, lalu mengirim sebagai PNG (metadata hilang; SVG hanya dirasterisasi, tidak pernah disajikan sebagai SVG).
  Server memvalidasi ulang: data URL `image/png`, tanda tangan PNG, 16–512 px, maks. 300 KB, dan berakhir tepat di penutup `IEND` (tanpa data tambahan).
- Berkas disimpan di `storage/uploads/logo.png` (di luar web, folder di-ignore Git) dan disajikan lewat `GET /brand/logo?v=<versi>` (publik, karena dipakai halaman login) dengan `Content-Type: image/png` tetap, `nosniff`, dan CSP `sandbox`. Versi (`logo_version`) menjadi pembatal cache.
- Berkas diganti **setelah** transaksi basis data berhasil; bila penyimpanan gagal, berkas sementara dibuang. Logo dapat dihapus (kembali ke placeholder).
- Perubahan tercatat di Audit Log (modul `Pengaturan`). Folder `storage/uploads/` harus dapat ditulis oleh web server.
- File: `app/services/SettingsService.php`, `app/controllers/SettingsController.php`, `app/views/settings/index.php`, `public/assets/js/settings.js`.

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
- **Ekspor** `GET /laporan/export?dataset=transaksi|santri&format=xlsx|pdf` + filter (izin `reports.export`: Admin ke atas; operator hanya melihat). Maks. 50.000 baris (pesan meminta mempersempit filter). Setiap ekspor tercatat di audit log.
  - `.xlsx` ditulis tanpa pustaka (`app/services/export/XlsxWriter.php`): tanggal & uang berupa angka asli, baris total, filter otomatis, baris beku. Sudah diuji dibuka di Microsoft Excel.
  - `.pdf` ditulis tanpa pustaka (`app/services/export/PdfWriter.php`): A4 lanskap, font Helvetica standar, judul + keterangan filter, header tabel diulang tiap halaman, teks panjang dibungkus (maks. 3 baris), angka rata kanan, baris total, "Halaman x / y". Karakter di luar WinAnsi (mis. huruf Arab, emoji) tampil "?" di PDF; Excel tetap lengkap. Maks. 10.000 baris (lebih dari itu pakai Excel); kolom `'pdf' => false` pada `TableExport` dilewati (mis. ID Santri pada rincian transaksi).
  - Menambah format baru: buat penulis yang menerima `TableExport`, lalu daftarkan di `ReportController::export`.
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
- **Soft delete**: semua query transaksi wajib memfilter `deleted_at IS NULL`; santri yang diarsipkan (`students.deleted_at`) tidak pernah ikut daftar/pilihan. Alumni hanya muncul lewat modul Alumni (`students.status = 'alumni'`).
- **CSRF** otomatis untuk semua POST/PUT/PATCH/DELETE (field `_csrf` atau header `X-CSRF-Token`).
- **CSP**: tidak ada `<script>` inline. Kirim data ke JS lewat atribut `data-*`.
- **Output** di view selalu lewat `e()`.
- **Jangan pakai kode HTTP non-standar** (mis. 419): Apache mengubahnya jadi 500. CSRF gagal pada API = 403.
- **Error**: detail hanya di `storage/logs/`; pengguna melihat pesan generik. `app.debug` harus `false` di production.
- Kode harus kompatibel **PHP 8.0** (tanpa enum, readonly property, dsb.).
