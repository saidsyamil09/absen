-- schema_mysql.sql
-- Skema untuk MySQL 8+ (partitioning by month using generated column)
-- Pastikan menggunakan MySQL 8+ dan backup DB sebelum menjalankan

-- 1) users table (simple)
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id VARCHAR(100) UNIQUE,
  name VARCHAR(255) NOT NULL,
  department VARCHAR(255),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2) attendance_events: store each event (IN/OUT/etc.)
-- We'll add a generated date column for partitioning by month
CREATE TABLE IF NOT EXISTS attendance_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  occurred_at DATETIME NOT NULL,
  occurred_date DATE GENERATED ALWAYS AS (DATE(occurred_at)) STORED,
  event_type ENUM('IN','OUT','PA','MANUAL') NOT NULL DEFAULT 'IN',
  device_id VARCHAR(255),
  location VARCHAR(255),
  metadata JSON,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB
PARTITION BY RANGE COLUMNS(occurred_date) (
  PARTITION p2025_11 VALUES LESS THAN ('2025-12-01'),
  PARTITION p2025_12 VALUES LESS THAN ('2026-01-01'),
  PARTITION pmax VALUES LESS THAN (MAXVALUE)
);

-- Indexes to support queries
CREATE INDEX idx_events_user_time ON attendance_events (user_id, occurred_at);
CREATE INDEX idx_events_time_user ON attendance_events (occurred_at, user_id);
CREATE INDEX idx_events_date ON attendance_events (occurred_date);

-- 3) daily_summary
CREATE TABLE IF NOT EXISTS daily_summary (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  first_in DATETIME,
  last_out DATETIME,
  total_work_seconds BIGINT UNSIGNED DEFAULT 0,
  presence TINYINT(1) DEFAULT 0,
  corrections JSON,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_daily_user_day (user_id, day),
  CONSTRAINT fk_daily_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_daily_user_day ON daily_summary (user_id, day);

-- 4) weekly_summary
CREATE TABLE IF NOT EXISTS weekly_summary (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  week_start DATE NOT NULL,
  week_end DATE NOT NULL,
  days_present SMALLINT UNSIGNED DEFAULT 0,
  total_work_seconds BIGINT UNSIGNED DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_week_user_weekstart (user_id, week_start),
  CONSTRAINT fk_week_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_week_user_weekstart ON weekly_summary (user_id, week_start);

-- 5) monthly_summary
CREATE TABLE IF NOT EXISTS monthly_summary (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  month DATE NOT NULL, -- store first day of month
  days_present SMALLINT UNSIGNED DEFAULT 0,
  total_work_seconds BIGINT UNSIGNED DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_month_user_month (user_id, month),
  CONSTRAINT fk_month_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_month_user_month ON monthly_summary (user_id, month);

-- 6) Notes:
-- - Sesuaikan partisi pada attendance_events: buat partisi untuk bulan-bulan ke depan.
-- - Tambahkan ALTER TABLE ... REORGANIZE PARTITION ... untuk menambah partisi (lihat README).
