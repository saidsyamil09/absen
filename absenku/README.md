```markdown
# E-Absensi (dengan Login & Scan QR / Barcode + Export / Cetak)

Perubahan penting:
- Fitur login: admin & guru
- Fitur absensi dengan scan barcode/QR via webcam (html5-qrcode)
- Export rekap ke CSV (bisa dibuka di Excel)
- Cetak surat panggilan (print) dan export PDF (opsional dengan Dompdf)

Persiapan & pemasangan (XAMPP):
1. Pastikan XAMPP terpasang dan Apache + MySQL jalan.
2. Letakkan folder proyek, mis: C:\xampp\htdocs\e-absensi
3. Import database:
   - Buka http://localhost/phpmyadmin
   - Buat database baru (atau import sql/setup.sql)
   - Di phpMyAdmin: import file sql/setup.sql
4. Buat akun admin/guru:
   - Untuk keamanan password disimpan terhash, jalankan browser: http://localhost/e-absensi/create_admin.php
   - Isi username/password/role lalu submit untuk membuat user
5. Konfigurasi DB:
   - Buka config.php dan sesuaikan DB_HOST, DB_NAME, DB_USER, DB_PASS
6. Composer (opsional, untuk export PDF via Dompdf atau Excel via PhpSpreadsheet):
   - Jika Anda ingin export PDF server-side, jalankan dari terminal (folder proyek):
     composer require dompdf/dompdf
   - Untuk export Excel with PhpSpreadsheet (opsional):
     composer require phpoffice/phpspreadsheet
   - Setelah composer terinstal, file export_letter_pdf.php dan export_excel_phpspreadsheet.php akan berfungsi
7. Akses aplikasi:
   - Buka http://localhost/e-absensi/
   - Login menggunakan akun yang dibuat via create_admin.php
8. Menggunakan scan QR:
   - Masuk sebagai guru atau admin, lalu buka menu "Scan" (Scan QR)
   - Izinkan akses webcam ketika diminta, arahkan ke QR/barcode yang berisi NIS (atau ID siswa) -> sistem akan menyimpan absensi hari ini
9. Export/Cetak:
   - Di halaman Rekap ada tombol export CSV
   - Di halaman Surat dapat menampilkan surat panggilan dan klik Cetak (browser print) atau Export PDF (jika Dompdf terpasang)

Catatan keamanan & pengembangan:
- Sistem autentikasi sederhana, tidak memakai CSRF protection — disarankan menambah proteksi untuk produksi.
- Logika status (Terlambat / Hadir) masih sederhana; bisa dikustomisasi berdasarkan jam masuk sekolah.
- Jika Anda ingin format barcode khusus (mis. isi: student_id|nis), sesuaikan validator di scan_attendance.php.

Jika ingin, saya bisa:
- Menambahkan fitur CRUD User (admin)
- Integrasi dengan reader RFID
- Menambahkan export XLSX (PhpSpreadsheet) contoh
- Menambahkan notifikasi surat via email (SMTP)
```