<?php
// Export rekap harian per kelas (summary) or detail per class to CSV.
// Params:
// - mode=summary | detail
// - date=YYYY-MM-DD (required, default today)
// - kelas=class_value | all | __empty__
//
// This script outputs CSV with appropriate headers.

if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        throw new Exception('PDO $pdo not found. Please provide db.php or set $pdo before including this file.');
    }
}

$mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'summary';
$day = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : 'all';

// detect class column
$class_column = null;
try {
    $colStmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'students'
          AND COLUMN_NAME IN ('kelas','class')
        LIMIT 1
    ");
    $colStmt->execute();
    $colInfo = $colStmt->fetch(PDO::FETCH_ASSOC);
    if ($colInfo && isset($colInfo['COLUMN_NAME'])) {
        $class_column = $colInfo['COLUMN_NAME'];
    }
} catch (Exception $e) {
    $class_column = null;
}

if (!$class_column) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Kolom kelas tidak ditemukan. Tidak dapat mengekspor rekap per kelas.";
    exit;
}

if ($mode === 'summary') {
    // Build summary query similar to rekap.php
    if ($kelas !== '' && $kelas !== 'all' && $kelas !== '__empty__') {
        $sql = "SELECT s.`$class_column` AS kelas,
                SUM(a.status='Hadir') AS hadir,
                SUM(a.status='Terlambat') AS telat,
                SUM(a.status='Izin') AS izin,
                SUM(a.status='Sakit') AS sakit,
                SUM(a.status='Alpha') AS alpha,
                COUNT(a.id) AS present_count,
                COUNT(s.id) AS class_size
              FROM students s
              LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
              WHERE s.`$class_column` = :kelas
              GROUP BY s.`$class_column`
              ORDER BY s.`$class_column` ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':d' => $day, ':kelas' => $kelas]);
    } elseif ($kelas === '__empty__') {
        $sql = "SELECT '(tanpa kelas)' AS kelas,
                SUM(a.status='Hadir') AS hadir,
                SUM(a.status='Terlambat') AS telat,
                SUM(a.status='Izin') AS izin,
                SUM(a.status='Sakit') AS sakit,
                SUM(a.status='Alpha') AS alpha,
                COUNT(a.id) AS present_count,
                COUNT(s.id) AS class_size
              FROM students s
              LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
              WHERE (s.`$class_column` IS NULL OR s.`$class_column` = '')
              GROUP BY kelas";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':d' => $day]);
    } else {
        $sql = "SELECT s.`$class_column` AS kelas,
                SUM(a.status='Hadir') AS hadir,
                SUM(a.status='Terlambat') AS telat,
                SUM(a.status='Izin') AS izin,
                SUM(a.status='Sakit') AS sakit,
                SUM(a.status='Alpha') AS alpha,
                COUNT(a.id) AS present_count,
                COUNT(s.id) AS class_size
              FROM students s
              LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
              GROUP BY s.`$class_column`
              ORDER BY s.`$class_column` ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':d' => $day]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare CSV
    $filename = "rekap_harian_summary_{$day}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // header row
    fputcsv($out, ['Kelas','Hadir','Terlambat','Izin','Sakit','Alpha','Total Hadir','Ukuran Kelas','% Hadir']);
    foreach ($rows as $r) {
        $present = (int)($r['present_count'] ?? 0);
        $size = (int)($r['class_size'] ?? 0);
        $percent = $size > 0 ? round(($present / $size) * 100, 1) : 0;
        fputcsv($out, [
            $r['kelas'] ?? '(tanpa kelas)',
            (int)($r['hadir'] ?? 0),
            (int)($r['telat'] ?? 0),
            (int)($r['izin'] ?? 0),
            (int)($r['sakit'] ?? 0),
            (int)($r['alpha'] ?? 0),
            $present,
            $size,
            $percent . '%'
        ]);
    }
    fclose($out);
    exit;
} elseif ($mode === 'detail') {
    // detailed list for class
    $params = [':d' => $day];
    if ($kelas === 'all') {
        $sql = "SELECT s.nis, s.name, s.`$class_column` AS kelas, a.status, a.time_in, a.note
                FROM students s
                LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
                ORDER BY s.`$class_column` ASC, s.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } elseif ($kelas === '__empty__') {
        $sql = "SELECT s.nis, s.name, '(tanpa kelas)' AS kelas, a.status, a.time_in, a.note
                FROM students s
                LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
                WHERE (s.`$class_column` IS NULL OR s.`$class_column` = '')
                ORDER BY s.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "SELECT s.nis, s.name, s.`$class_column` AS kelas, a.status, a.time_in, a.note
                FROM students s
                LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
                WHERE s.`$class_column` = :kelas
                ORDER BY s.name ASC";
        $params[':kelas'] = $kelas;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "rekap_harian_detail_{$day}_" . ($kelas === '__empty__' ? 'tanpa_kelas' : preg_replace('/[^a-zA-Z0-9_-]/', '_', $kelas)) . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['NIS','Nama','Kelas','Status','Jam Masuk','Catatan']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['nis'],
            $r['name'],
            $r['kelas'] ?? '(tanpa kelas)',
            $r['status'] ?? '-',
            $r['time_in'] ?? '-',
            $r['note'] ?? ''
        ]);
    }
    fclose($out);
    exit;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Mode tidak dikenali. Gunakan mode=summary atau mode=detail.";
    exit;
}