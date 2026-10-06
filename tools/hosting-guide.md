# Panduan memasang Tabungan Santri di hosting gratis (InfinityFree)

Paket ini dibuat oleh `php tools/package-hosting.php`. Hosting gratis tidak punya SSH/cron, jadi semua langkah memakai panel, phpMyAdmin, dan FTP.
Nama menu di panel dapat sedikit berbeda dari tulisan di bawah.

## Isi folder ini
| Berkas | Fungsi |
|---|---|
| `htdocs/` | Seluruh isi yang diunggah ke folder **htdocs** hosting |
| `hosting-schema.sql` | Struktur database (diimpor lewat phpMyAdmin) |
| `hosting-admin.sql` | Membuat satu akun Super Admin (`admin`) dengan kata sandi sementara — lihat `AKUN-AWAL.local.txt` |
| `tabungan-santri-htdocs.zip` | Arsip isi `htdocs/` (cadangan) |

## Langkah
1. **Buat database** — panel hosting → *MySQL Databases* → buat database baru (mis. akhiran `tabungan`).
   Catat: **host MySQL** (mis. `sql123.infinityfree.com`, bukan `localhost`), **nama database** (mis. `if0_12345678_tabungan`), **pengguna** (mis. `if0_12345678`), dan **kata sandi** (kata sandi akun hosting).
2. **Impor tabel** — buka *phpMyAdmin* untuk database itu → tab **Import** → pilih `hosting-schema.sql` → *Go*. Lalu impor `hosting-admin.sql` dengan cara yang sama.
3. **Isi koneksi database** — buka `htdocs/app/config/database.php` di komputer Anda dengan Notepad, ganti keempat nilai `ISI_...` dengan data langkah 1, simpan.
4. **Unggah lewat FTP** (mis. FileZilla): host `ftpupload.net`, port 21, pengguna dan kata sandi dari halaman akun hosting. Masuk ke folder **htdocs** (hapus berkas bawaan seperti `index2.html`), lalu unggah **isi** folder `htdocs/` dari paket ini (termasuk `.htaccess`; aktifkan "tampilkan berkas tersembunyi" bila perlu). Pastikan folder `storage/` ikut terunggah.
5. **PHP**: pilih PHP **8.0 atau lebih baru** (panel → *PHP Config* / pemilih versi PHP).
6. **HTTPS**: aktifkan *Free SSL Certificate* di area klien, lalu buka situs lewat `https://`.
7. **Masuk**: buka alamat situs → username `admin`, kata sandi sementara dari `AKUN-AWAL.local.txt`. **Segera** buka menu *Pengguna* → atur ulang kata sandi, lalu buat akun pengguna lain.
8. Menu *Pengaturan* → isi nama lembaga & logo.

## Catatan hosting gratis
- **Cadangan manual**: tidak ada cron. Rutin ekspor database lewat phpMyAdmin → *Export* (format SQL) dan simpan di luar hosting.
- Ada batas pemakaian harian (hits/CPU). Bila terlampaui, situs tertahan sementara.
- Folder `storage/` harus dapat ditulis PHP (sesi, log, logo). Bila muncul "Terjadi kesalahan" saat login, cek izin folder tersebut (755/775) dan log di `storage/logs/`.
- Memerlukan MariaDB 10.2+/MySQL 8 (fungsi window & view). Bila impor `hosting-schema.sql` gagal di baris `ROW_NUMBER`/`CREATE VIEW`, versi MySQL hosting terlalu lama.
- Jangan pernah mengunggah `database/seed.sql` (akun demo) ke hosting.
