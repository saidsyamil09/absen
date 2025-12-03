<?php
// manual_attendance.php
// Endpoint JSON untuk Absen Manual.
// Pastikan file ini diletakkan di folder web root yang sama dengan index/dashboard,
// dan file db.php (koneksi PDO) tersedia di lokasi yang sama atau sesuaikan path.

// Include DB (sesuaikan jika db.php di folder lain)
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['success' => false, 'message' => 'db.php tidak ditemukan.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Debug (false di production)
$debug = false;

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Payload JSON tidak valid.']);
    exit;
}

if (!isset($input['action']) || $input['action'] !== 'manual') {
    echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
    exit;
}

$nis = isset($input['nis']) ? trim($input['nis']) : '';
$status = isset($input['status']) ? trim($input['status']) : '';
$note = isset($input['note']) ? trim($input['note']) : '';

$allowed = ['Hadir','Terlambat','Izin','Alpha'];
if ($nis === '') {
    echo json_encode(['success' => false, 'message' => 'NIS wajib diisi.']);
    exit;
}
if ($status === '' || !in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid.']);
    exit;
}

$today = date('Y-m-d');

try {
    // cari siswa berdasarkan NIS
    $stmt = $pdo->prepare("SELECT id, name FROM students WHERE nis = :nis LIMIT 1");
    $stmt->execute([':nis' => $nis]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Siswa dengan NIS tersebut tidak ditemukan.']);
        exit;
    }
    $student_id = (int)$student['id'];

    $time_in = null;
    if ($status === 'Hadir' || $status === 'Terlambat') $time_in = date('H:i:s');

    // upsert attendance record untuk hari ini
    $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = :sid AND date = :d LIMIT 1");
    $check->execute([':sid' => $student_id, ':d' => $today]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $update = $pdo->prepare("UPDATE attendance SET status = :status, time_in = :time_in, note = :note WHERE id = :id");
        $update->execute([
            ':status' => $status,
            ':time_in' => $time_in,
            ':note' => $note ?: null,
            ':id' => $existing['id'],
        ]);
        $msg = 'Absensi berhasil diperbarui untuk ' . $student['name'] . '.';
    } else {
        $insert = $pdo->prepare("INSERT INTO attendance (student_id, date, time_in, status, note) VALUES (:sid, :d, :time_in, :status, :note)");
        $insert->execute([
            ':sid' => $student_id,
            ':d' => $today,
            ':time_in' => $time_in,
            ':status' => $status,
            ':note' => $note ?: null,
        ]);
        $msg = 'Absensi berhasil dicatat untuk ' . $student['name'] . '.';
    }

    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
} catch (Exception $e) {
    if ($debug) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server.']);
    }
    exit;
}