<?php
// Endpoint untuk menyimpan absensi setelah scan
require_once __DIR__ . '/db.php';
if (!current_user()) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false, 'message'=>'Harus login']);
    exit;
}
// Baca input JSON
$input = json_decode(file_get_contents('php://input'), true);
$code = isset($input['code']) ? trim($input['code']) : '';
if (!$code) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false, 'message'=>'Kode kosong']);
    exit;
}

// Asumsi: kode berisi NIS atau student ID. Kita coba cari siswa berdasarkan nis terlebih dahulu, lalu id.
$student = null;
$stmt = $pdo->prepare("SELECT * FROM students WHERE nis = ? LIMIT 1");
$stmt->execute([$code]);
$student = $stmt->fetch();
if (!$student) {
    // coba id numeric
    if (ctype_digit($code)) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([intval($code)]);
        $student = $stmt->fetch();
    }
}

if (!$student) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false, 'message'=>'Siswa tidak ditemukan untuk kode: '.$code]);
    exit;
}

// Cek apakah sudah ada absensi untuk student hari ini (time_in)
$today = date('Y-m-d');
$ch = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
$ch->execute([$student['id'], $today]);
$exists = $ch->fetch();
if ($exists) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false, 'message'=>'Absensi hari ini sudah tercatat ('.$student['name'].')']);
    exit;
}

// Simpan absensi: tentukan status sederhana berdasarkan jam (contoh: laten after 07:15)
$now = date('H:i:s');
$cutoff = '07:15:00';
$status = ($now > $cutoff) ? 'Terlambat' : 'Hadir';

$ins = $pdo->prepare("INSERT INTO attendance (student_id, date, time_in, status) VALUES (?,?,?,?)");
$ins->execute([$student['id'], $today, $now, $status]);

header('Content-Type: application/json');
echo json_encode(['success'=>true, 'message'=>'Absensi tersimpan: '.$student['name'].' ('.$status.')']);
exit;