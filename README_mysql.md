# Panduan migrasi & operational untuk MySQL (rekap mingguan & bulanan)

Apa yang saya siapkan:
- schema_mysql.sql: skema tables (attendance_events dengan partition by month, daily_summary, weekly_summary, monthly_summary).
- aggregate_worker_mysql.js: contoh worker Node.js yang menghitung daily -> weekly -> monthly.

Langkah implementasi (singkat):
1. Backup DB saat ini!
2. Terapkan migration:
   - Jalankan schema_mysql.sql di MySQL 8+ (contoh: mysql -u root -p yourdb < schema_mysql.sql)
   - Perhatikan: PARTITION di schema contoh menggunakan partisi p2025_11, p2025_12 dan pmax. Sesuaikan dengan bulan saat Anda menjalankan.
3. Buat partisi bulanan:
   - Tambah partisi untuk bulan-bulan ke depan menggunakan ALTER TABLE ... REORGANIZE PARTITION atau ALTER TABLE ... ADD PARTITION.
   - Contoh menambah partisi untuk Jan 2026:
     ALTER TABLE attendance_events REORGANIZE PARTITION pmax INTO (
       PARTITION p2026_01 VALUES LESS THAN ('2026-02-01'),
       PARTITION pmax VALUES LESS THAN (MAXVALUE)
     );
4. Jalankan worker:
   - Set environment variable DATABASE_URL (contoh: mysql://user:pass@host:3306/dbname)
   - Jadwalkan worker: contoh cron 5 0 * * * /usr/bin/node /path/to/aggregate_worker_mysql.js
   - Worker ini menghitung untuk hari kemarin; jika hari kemarin menyelesaikan minggu/bulan akan memanggil agregat mingguan/bulanan.
5. Backfill historis:
   - Gunakan worker atau skrip untuk memanggil computeDailyFor untuk rentang hari historis (batched).
6. Integrasi ke aplikasi:
   - Ubah endpoint rekap untuk membaca daily_summary / weekly_summary / monthly_summary (bukan langsung attendance_events untuk rentang besar).
7. Monitoring:
   - Log job runtime / row counts / errors, tambahkan alert bila gagal.

Catatan:
- Jika aplikasi Anda Laravel/PHP dan Anda mau migration dalam format Laravel, saya bisa buat file migration sesuai struktur Laravel setelah Anda konfirmasi.
- Jika Anda ingin worker ditulis dalam bahasa backend Anda (mis. PHP artisan command), sebutkan stack.
