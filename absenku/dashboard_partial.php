<?php
// dashboard_partial.php
// Mengembalikan JSON berisi statistik ringkas (untuk "hari ini") dan HTML untuk recent attendance rows.
// Output:
// { success: true, data: { total: int, hadir: int, telat: int, izin: int, present_count: int, alpha: int, recent_html: "<tr>...</tr>" } }

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
    // Gunakan tanggal hari ini untuk statistik "hari ini"
    $today = date('Y-m-d');

    // total siswa
    $total = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    // Hitung jumlah siswa unik per status hari ini (hanya siswa valid via JOIN students)
    $hadirStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id)
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.date = :d AND a.status = 'Hadir'
    ");
    $hadirStmt->execute([':d' => $today]);
    $hadir = (int)$hadirStmt->fetchColumn();

    $telatStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id)
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.date = :d AND a.status = 'Terlambat'
    ");
    $telatStmt->execute([':d' => $today]);
    $telat = (int)$telatStmt->fetchColumn();

    $izinStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id)
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.date = :d AND a.status = 'Izin'
    ");
    $izinStmt->execute([':d' => $today]);
    $izin = (int)$izinStmt->fetchColumn();

    // present_count = siswa yang tercatat (Hadir/Terlambat/Izin) hari ini (distinct valid students)
    $presentStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id)
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.date = :d AND a.status IN ('Hadir','Terlambat','Izin')
    ");
    $presentStmt->execute([':d' => $today]);
    $present_count = (int)$presentStmt->fetchColumn();

    // Alpha = total siswa - present_count (minimal 0)
    $alpha = $total - $present_count;
    if ($alpha < 0) $alpha = 0;

    // Ambil recent attendance untuk hari ini (10 terbaru). Jika ingin seluruh history, hapus WHERE a.date = :d
    $recentSql = "
        SELECT a.*, s.name AS student_name
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        WHERE a.date = :d
        ORDER BY a.time_in DESC, a.id DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($recentSql);
    $stmt->execute([':d' => $today]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buat HTML untuk tbody recent (escape aman)
    $html = '';
    $no = 1;
    foreach ($rows as $r) {
        $date = htmlspecialchars($r['date'] ?? '');
        $name = htmlspecialchars($r['student_name'] ?? ($r['student_id'] ?? ''));
        $time_in = htmlspecialchars(!empty($r['time_in']) ? $r['time_in'] : '-');
        $statusRaw = $r['status'] ?? '';
        $status = htmlspecialchars($statusRaw);

        $badgeClass = 'secondary';
        if ($statusRaw === 'Hadir') $badgeClass = 'success';
        elseif ($statusRaw === 'Terlambat') $badgeClass = 'warning';
        elseif ($statusRaw === 'Izin') $badgeClass = 'info';
        elseif ($statusRaw === 'Alpha') $badgeClass = 'danger';

        $html .= '<tr>';
        $html .= '<td>' . $no . '</td>';
        $html .= '<td>' . $date . '</td>';
        $html .= '<td>' . $name . '</td>';
        $html .= '<td>' . $time_in . '</td>';
        $html .= '<td><span class="badge badge-' . $badgeClass . '">' . $status . '</span></td>';
        $html .= '</tr>';
        $no++;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $total,
            'hadir' => $hadir,
            'telat' => $telat,
            'izin' => $izin,
            'present_count' => $present_count,
            'alpha' => $alpha,
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