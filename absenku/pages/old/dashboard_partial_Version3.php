<?php
// dashboard_partial.php
// Mengembalikan JSON berisi statistik ringkas dan HTML untuk recent attendance rows.
// Output:
// { success: true, data: { total: int, hadir: int, telat: int, alpha: int, izin: int, recent_html: "<tr>...</tr>" } }

// Pastikan header JSON
header('Content-Type: application/json; charset=utf-8');

// Pastikan $pdo tersedia; jika tidak, include koneksi DB Anda
if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Koneksi database tidak ditemukan.']);
        exit;
    }
}

try {
    // ambil statistik
    $total = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $hadir = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE status='Hadir'")->fetchColumn();
    $telat = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE status='Terlambat'")->fetchColumn();
    $alpha = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE status='Alpha'")->fetchColumn();
    // izin: jika Anda punya kolom status 'Izin' atau sejenis, sesuaikan
    $izin = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE status='Izin'")->fetchColumn();

    // ambil recent 10 attendance (hari ini atau seluruh? disini menggunakan seluruh tabel recent)
    $stmt = $pdo->query("SELECT a.*, s.name AS student_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id ORDER BY a.date DESC, a.time_in DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // buat HTML untuk tbody recent (server-side escaping)
    $html = '';
    $no = 1;
    foreach ($rows as $r) {
        $date = htmlspecialchars($r['date']);
        $name = htmlspecialchars($r['student_name']);
        $time_in = htmlspecialchars($r['time_in'] ?: '-');
        $status = htmlspecialchars($r['status']);
        $badgeClass = 'secondary';
        if ($r['status'] === 'Hadir') $badgeClass = 'success';
        elseif ($r['status'] === 'Terlambat') $badgeClass = 'warning';
        elseif ($r['status'] === 'Alpha') $badgeClass = 'danger';
        $html .= "<tr>";
        $html .= "<td>{$no}</td>";
        $html .= "<td>{$date}</td>";
        $html .= "<td>{$name}</td>";
        $html .= "<td>{$time_in}</td>";
        $html .= "<td><span class=\"badge badge-{$badgeClass}\">{$status}</span></td>";
        $html .= "</tr>";
        $no++;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $total,
            'hadir' => $hadir,
            'telat' => $telat,
            'alpha' => $alpha,
            'izin' => $izin,
            'recent_html' => $html
        ]
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("dashboard_partial error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat mengambil data.']);
    exit;
}
?>