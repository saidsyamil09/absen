-- SQL setup untuk demo e_absensi (Diperbarui: tambah users)
CREATE DATABASE IF NOT EXISTS e_absensi DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE e_absensi;

-- Tabel users
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','guru') NOT NULL DEFAULT 'guru',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel students
DROP TABLE IF EXISTS students;
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nis VARCHAR(20) NOT NULL,
  name VARCHAR(150) NOT NULL,
  class VARCHAR(50) DEFAULT 'X A1'
);

-- Tabel attendance
DROP TABLE IF EXISTS attendance;
CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  date DATE NOT NULL,
  time_in TIME NULL,
  time_out TIME NULL,
  status VARCHAR(20) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Tabel letters (surat panggilan)
DROP TABLE IF EXISTS letters;
CREATE TABLE letters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(50),
  date DATE,
  type VARCHAR(20),
  student_id INT,
  class VARCHAR(20),
  alpha_count INT DEFAULT 0,
  status VARCHAR(20) DEFAULT 'Terkirim',
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

-- Sample data students
INSERT INTO students (nis, name, class) VALUES
('2512147', 'ABDI SYAHRUL CAHYONO', 'X A1'),
('2512149', 'AHMAD ILHAM', 'X A1'),
('2512150', 'AINUN MARDIAH', 'X A1');

-- Sample attendance (random)
INSERT INTO attendance (student_id, date, time_in, time_out, status, note) VALUES
(1,'2025-11-21', NULL, NULL, 'Alpha', NULL),
(1,'2025-11-20','10:39:00', NULL, 'Terlambat', NULL),
(1,'2025-11-19','07:25:00', NULL, 'Hadir', NULL),
(1,'2025-11-18','07:26:00','14:16:00','Hadir','Pulang Cepat'),
(2,'2025-11-18','07:15:00','14:10:00','Hadir',NULL),
(3,'2025-11-19', NULL, NULL,'Alpha', NULL),
(2,'2025-11-20','07:30:00', NULL,'Terlambat',NULL);

-- Sample letters
INSERT INTO letters (number, date, type, student_id, class, alpha_count, status) VALUES
('SP1/0047/11/2025','2025-11-17','SP1',1,'X A1',5,'Terkirim');