# Pet Shop — Setup di Laragon

## 1. Siapkan Laragon
1. Install **Laragon Full** (sudah termasuk Apache, MySQL/MariaDB, PHP).
2. Buka Laragon → klik **Start All**. Pastikan Apache dan MySQL berstatus hijau.

## 2. Taruh project
Copy folder `petshop` ke `C:\laragon\www\petshop`
(file `index.php`, `config.php`, dll harus langsung di dalam folder itu).

## 3. Buat database (pilih salah satu)

### A. Lewat HeidiSQL (paling mudah)
1. Di Laragon klik tombol **Database** → HeidiSQL terbuka.
2. Buat sesi baru (jika belum ada): Network type *MySQL (TCP/IP)*, Host `127.0.0.1`,
   User `root`, Password **kosong**, Port `3306` → **Open**.
3. Menu **File → Run SQL file…** → pilih `C:\laragon\www\petshop\database\petshop_db.sql`.
4. Klik kanan sesi di panel kiri → **Refresh**. Database `petshop_db` + 4 tabel akan muncul.

### B. Lewat terminal Laragon
Klik kanan area Laragon → **Terminal**, lalu:
```
mysql -u root < C:\laragon\www\petshop\database\petshop_db.sql
```

### C. Buat database manual, lalu import
HeidiSQL → klik kanan sesi → **Create new → Database** → nama `petshop_db`,
collation `utf8mb4_unicode_ci` → pilih database itu → **File → Run SQL file…** (file yang sama).

## 4. Jalankan
Buka `http://localhost/petshop/` (atau `http://petshop.test` jika Auto Virtual Host Laragon aktif —
klik **Menu → Apache → Reload** setelah folder ditambahkan).

| Role  | Login                       |
|-------|-----------------------------|
| Admin | admin@petshop.com / admin123 |
| Kasir | kasir / kasir123             |

**Segera ganti password.** Kasir: Admin Panel → Kelola Kasir → Reset PW.
Admin (via terminal Laragon): `php tools\reset_admin.php PasswordBaru`.

## 5. Pengaturan koneksi
Semua ada di `config.php` (bagian atas): `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`.
Jika MySQL Laragon kamu diberi password / port lain, ubah di situ.

## Troubleshooting
- **"Koneksi database gagal"** → MySQL Laragon belum Start, atau port/password di `config.php` tidak cocok.
- **"Unknown database petshop_db"** → langkah 3 belum dijalankan.
- **Login gagal** → cek `logs/login.log` dan `logs/php_error.log`.
- **Grafik admin kosong** → Chart.js dimuat dari CDN, butuh internet.

## File lama yang SUDAH DIHAPUS (jangan dipakai lagi)
`fix_admin.php`, `setup.php`, `install.php`, `debug_login.php`, `test.php`, `test_connection.php`,
`test_system.php`, `login_errors.log` — file-file ini bisa dibuka siapa saja & mereset password admin tanpa login.
Gantinya: `database/petshop_db.sql` dan `tools/reset_admin.php` (CLI saja).
