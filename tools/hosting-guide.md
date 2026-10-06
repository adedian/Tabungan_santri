# Panduan memasang Tabungan Santri di hosting gratis (InfinityFree)

Paket ini dibuat oleh `php tools/package-hosting.php`. Hosting gratis tidak punya SSH/cron, jadi pemasangan memakai **pemasang web** (`install.php`) — Anda tidak perlu mengimpor SQL atau mengedit berkas konfigurasi.
Nama menu di panel dapat sedikit berbeda dari tulisan di bawah.

## Isi folder ini
| Berkas | Fungsi |
|---|---|
| `htdocs/` | Seluruh isi yang diunggah ke folder **htdocs** hosting |
| `AKUN-AWAL.local.txt` | **Kode pemasangan** untuk `install.php` (jangan dibagikan) |
| `hosting-schema.sql`, `hosting-admin.sql` | Hanya untuk jalur manual lewat phpMyAdmin (lihat bagian bawah) |
| `tabungan-santri-htdocs.zip` | Arsip isi `htdocs/` (cadangan) |

## Langkah (jalur utama)
1. **Buat database** — panel hosting → *MySQL Databases* → buat database baru (mis. akhiran `tabungan`).
   Catat: **host MySQL** (mis. `sql123.infinityfree.com`, bukan `localhost`), **nama database** (mis. `if0_12345678_tabungan`), **pengguna** (mis. `if0_12345678`); kata sandinya = kata sandi akun hosting.
2. **Unggah lewat FTP** (FileZilla): host `ftpupload.net`, port 21. Di panel kanan masuk ke folder **`htdocs`** (hapus berkas bawaan seperti `index2.html`). Di komputer buka folder `deploy\htdocs\`, tekan **Ctrl+A** (pilih SEMUA isinya, termasuk `.htaccess`) lalu seret ke `htdocs` di panel kanan.
   Hasil yang benar — folder-folder ini **langsung** di dalam `htdocs`, tanpa folder pembungkus:
   ```
   htdocs/
   ├── .htaccess
   ├── app/
   ├── database/
   ├── public/        ← berisi index.php, install.php, assets/
   ├── routes/
   └── storage/
   ```
   Bila berkas bertitik tidak tampak: FileZilla → *Server → Force showing hidden files*.
3. **PHP**: pilih PHP **8.0 atau lebih baru** (panel → *PHP Config* / pemilih versi PHP).
4. **Pasang**: buka `https://DOMAIN-ANDA/install.php`. Isi **kode pemasangan** (dari `AKUN-AWAL.local.txt`), data database (langkah 1), dan akun admin pilihan Anda (kata sandi min. 8 karakter). Tekan **Pasang sekarang**. Tabel dibuat dan koneksi tersimpan otomatis.
5. **Hapus `public/install.php`** dari hosting (FTP). Halaman itu sudah terkunci setelah berhasil, tetapi berkasnya tidak diperlukan lagi.
6. **HTTPS**: aktifkan *Free SSL Certificate* di area klien, lalu buka situs lewat `https://`.
7. Masuk dengan akun admin yang Anda buat → menu *Pengaturan* untuk nama lembaga & logo → menu *Pengguna* untuk akun lain.

Keamanan pemasang: wajib kode pemasangan; 8 kode salah mengunci (hapus `storage/install-fails.json` lewat FTP untuk membuka); menolak bila database sudah berisi pengguna; tidak menimpa data.

## Catatan hosting gratis
- **Cadangan manual**: tidak ada cron. Rutin ekspor database lewat phpMyAdmin → *Export* (format SQL) dan simpan di luar hosting.
- Ada batas pemakaian harian (hits/CPU). Bila terlampaui, situs tertahan sementara.
- Folder `storage/` dan `app/config/` harus dapat ditulis PHP. Bila pemasang meminta, atur izin folder (755/775) lewat FTP.
- Memerlukan MariaDB 10.2+/MySQL 8 (fungsi window & view). Bila pemasang gagal pada langkah pembuatan tabel dengan pesan soal `ROW_NUMBER`/`VIEW`, versi MySQL hosting terlalu lama.
- Jangan pernah mengunggah `database/seed.sql` (akun demo) ke hosting.

## Jalur manual (tanpa pemasang)
Impor `hosting-schema.sql` lalu `hosting-admin.sql` lewat phpMyAdmin, ubah `app/config/database.php` (isi empat nilai `ISI_...`) sebelum diunggah, dan masuk dengan username `admin` + kata sandi sementara di `AKUN-AWAL.local.txt`. Hapus `public/install.php` karena tidak dipakai.

## Jalur satu berkas (bila seluruh proyek sudah terunggah ke `htdocs/Tabungan_santri/`)
Bila folder proyek terlanjur terunggah utuh dan aplikasinya sudah hidup di `https://DOMAIN/Tabungan_santri/`, tidak perlu memindahkan apa pun:
1. Unggah **satu berkas** `satu-berkas/install.php` ke `htdocs/Tabungan_santri/public/`.
2. Buka `https://DOMAIN/Tabungan_santri/install.php` dan isi formulir (kode pemasangan, data database, akun admin). Skema dibaca dari `database/schema.sql` di folder itu.
3. Hapus `public/install.php` setelah berhasil.
4. Opsional: simpan `satu-berkas/ALIHKAN-AKAR.htaccess` sebagai `.htaccess` di `htdocs/` agar alamat utama menuju aplikasi.
Tanpa pembersihan, folder itu masih membawa berkas pengembang (`AKUN-DEMO.local.md`, `tools/`, `deploy/`, `database/seed.sql`); semuanya terkunci dari web, tetapi sebaiknya dihapus lewat FTP.
