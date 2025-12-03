<?php
// record_attendance.php (debug-friendly)
// Endpoint menerima POST JSON { nis: "12345" }
// Mengembalikan JSON; jika $DEBUG = true maka menampilkan pesan error detail (HANYA untuk development).

header('Content-Type: application/json; charset=utf-8');

$DEBUG = true; // set false di production

// terima JSON body
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'Payload harus JSON.', 'raw'=>$raw]);
    exit;
}
if (empty($input['nis'])) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'Parameter nis wajib diisi.']);
    exit;
}

$nis = trim($input['nis']);
$nis = preg_replace('/\s+/', '', $nis);

// pastikan $pdo tersedia (sesuaikan path jika koneksi Anda di file lain)
if (!isset($pdo)) {
    // coba include beberapa kemungkinan file koneksi
    $included = false;
    $possible = [
        __DIR__ . '/db.php',
        __DIR__ . '/config.php',
        __DIR__ . '/includes/db.php'
    ];
    foreach ($possible as $p) {
        if (file_exists($p)) {
            require_once $p;
            $included = true;
            break;
        }
    }
    if (!isset($pdo)) {
        // jika belum ada, kirim response error yang jelas
        http_response_code(500);
        $msg = 'Koneksi database tidak ditemukan. Sesuaikan record_attendance.php untuk include file koneksi Anda.';
        echo json_encode(['success'=>false, 'message'=>$msg, 'debug_suggestions' => $possible]);
        exit;
    }
}

try {
    // cari siswa berdasarkan NIS
    $stmt = $pdo->prepare("SELECT * FROM students WHERE nis = ? LIMIT 1");
    $stmt->execute([$nis]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success'=>false, 'message'=>"Siswa dengan NIS {$nis} tidak ditemukan."]);
        exit;
    }

    $student_id = $student['id'];
    // tanggal hari ini (YYYY-MM-DD)
    $today = (new DateTime())->format('Y-m-d');
    $now = (new DateTime())->format('H:i:s');

    // cek apakah sudah ada record untuk student_id dan date = today
    $check = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ? LIMIT 1");
    $check->execute([$student_id, $today]);
    $att = $check->fetch(PDO::FETCH_ASSOC);

    // definisikan cutoff jam untuk "Terlambat" (ubah sesuai kebijakan)
    $cutoff = '07:30:00';

    if (!$att) {
        // insert time_in
        $status = ($now <= $cutoff) ? 'Hadir' : 'Terlambat';
        $insert = $pdo->prepare("INSERT INTO attendance (student_id, date, time_in, status, note) VALUES (?, ?, ?, ?, ?)");
        $note = 'Dimasukkan melalui scanner USB';
        $insert->execute([$student_id, $today, $now, $status, $note]);

        echo json_encode([
            'success' => true,
            'message' => "Absensi masuk tercatat: {$student['name']} ({$student['nis']}) - status: {$status}",
            'data' => [
                'student_id' => $student_id,
                'date' => $today,
                'time_in' => $now,
                'status' => $status
            ]
        ]);
        exit;
    } else {
        if (empty($att['time_in'])) {
            $status = ($now <= $cutoff) ? 'Hadir' : 'Terlambat';
            $upd = $pdo->prepare("UPDATE attendance SET time_in = ?, status = ?, note = CONCAT(IFNULL(note,''), ?) WHERE id = ?");
            $addnote = " | time_in di-scan: $now";
            $upd->execute([$now, $status, $addnote, $att['id']]);
            echo json_encode(['success'=>true, 'message'=>"Time-in dicatat untuk {$student['name']} pada {$now}", 'data'=>['time_in'=>$now,'status'=>$status]]);
            exit;
        } elseif (empty($att['time_out'])) {
            $upd = $pdo->prepare("UPDATE attendance SET time_out = ?, note = CONCAT(IFNULL(note,''), ?) WHERE id = ?");
            $addnote = " | time_out di-scan: $now";
            $upd->execute([$now, $addnote, $att['id']]);
            echo json_encode(['success'=>true, 'message'=>"Time-out dicatat untuk {$student['name']} pada {$now}", 'data'=>['time_out'=>$now]]);
            exit;
        } else {
            echo json_encode(['success'=>false, 'message'=>"Absensi untuk hari ini sudah lengkap (masuk & pulang tercatat)."]);
            exit;
        }
    }
} catch (Exception $e) {
    // log ke error_log
    error_log("record_attendance error: " . $e->getMessage());
    http_response_code(500);
    if ($DEBUG) {
        echo json_encode(['success'=>false, 'message'=>'Terjadi kesalahan saat memproses data.', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Terjadi kesalahan saat memproses data.']);
    }
    exit;
}
?>