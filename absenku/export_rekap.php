<?php
// export_rekap.php - export CSV for summary/class, per-student summary, and detailed exports.
// Query params:
// - mode: summary | detail | student | detail_student
// - period: daily|weekly|monthly + appropriate date params
// - kelas (for summary/detail), student_id (for student detail), student (filter), status (filter)

if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) require_once __DIR__ . '/db.php';
    else throw new Exception('PDO $pdo not available.');
}

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'summary';
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';

try {
    if ($period === 'weekly') {
        $period_start = $_GET['week_start'] ?? date('Y-m-d');
        $period_end = $_GET['week_end'] ?? $period_start;
    } elseif ($period === 'monthly') {
        $m = $_GET['month'] ?? date('Y-m');
        $s = new DateTime($m . '-01');
        $e = (clone $s)->modify('last day of this month');
        $period_start = $s->format('Y-m-d');
        $period_end = $e->format('Y-m-d');
    } else {
        $period_start = $_GET['date'] ?? date('Y-m-d');
        $period_end = $period_start;
    }
} catch (Exception $e) {
    $period_start = date('Y-m-d');
    $period_end = date('Y-m-d');
}

// helper to output CSV
function output_csv($rows, $headers, $filename = 'export.csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // BOM for Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

try {
    if ($mode === 'student') {
        // per-student summary for period (supports optional student filter & status filter & kelas)
        $student_filter = $_GET['student'] ?? '';
        $status_filter = $_GET['status'] ?? 'all';
        $kelas = $_GET['kelas'] ?? null;

        $where = [];
        $params = [':start' => $period_start, ':end' => $period_end];
        if ($kelas && $kelas !== 'all') {
            if ($kelas === '__empty__') {
                $where[] = "(s.kelas IS NULL OR s.kelas = '')";
            } else {
                $where[] = "s.kelas = :kelas";
                $params[':kelas'] = $kelas;
            }
        }
        if ($student_filter !== '') {
            if (ctype_digit($student_filter)) {
                $where[] = "(s.id = :student_exact OR s.name LIKE :student_like)";
                $params[':student_exact'] = $student_filter;
                $params[':student_like'] = '%' . $student_filter . '%';
            } else {
                $where[] = "s.name LIKE :student_like";
                $params[':student_like'] = '%' . $student_filter . '%';
            }
        }
        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT s.id AS student_id, s.name AS student_name, COALESCE(s.kelas, '(tanpa kelas)') AS kelas,
                SUM(a.status='Hadir') AS hadir,
                SUM(a.status='Terlambat') AS telat,
                SUM(a.status='Izin') AS izin,
                SUM(a.status='Sakit') AS sakit,
                SUM(a.status='Alpha') AS alpha,
                COUNT(a.id) AS total_records
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date BETWEEN :start AND :end
            $whereSql
            GROUP BY s.id
            ORDER BY COALESCE(s.kelas,''), s.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // apply status filter client-side (same approach as rekap.php)
        if ($status_filter !== 'all') {
            $sf = strtolower($status_filter);
            $rows = array_filter($rows, function($r) use ($sf) {
                $cnt = 0;
                switch ($sf) {
                    case 'hadir': $cnt = (int)$r['hadir']; break;
                    case 'terlambat': $cnt = (int)$r['telat']; break;
                    case 'izin': $cnt = (int)$r['izin']; break;
                    case 'sakit': $cnt = (int)$r['sakit']; break;
                    case 'alpha': $cnt = (int)$r['alpha']; break;
                }
                return $cnt > 0;
            });
        }

        $outRows = [];
        foreach ($rows as $r) {
            $outRows[] = [
                $r['student_id'],
                $r['student_name'],
                $r['kelas'],
                $r['hadir'],
                $r['terlambat'],
                $r['izin'],
                $r['sakit'],
                $r['alpha'],
                $r['total_records'],
            ];
        }
        output_csv($outRows, ['Student ID','Nama','Kelas','Hadir','Terlambat','Izin','Sakit','Alpha','Total'], 'rekap_per_siswa.csv');
    } elseif ($mode === 'detail_student') {
        // export detailed attendance rows for a specific student
        $student_id = $_GET['student_id'] ?? '';
        $sql = "SELECT a.date, a.time, a.status, a.note FROM attendance a WHERE a.student_id = :student_id AND a.date BETWEEN :start AND :end ORDER BY a.date, a.time";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':student_id' => $student_id, ':start' => $period_start, ':end' => $period_end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['date'], $r['time'] ?? '', $r['status'], $r['note'] ?? ''];
        }
        output_csv($out, ['Tanggal','Waktu','Status','Catatan'], 'rekap_detail_siswa_' . $student_id . '.csv');
    } elseif ($mode === 'detail') {
        // export detailed attendance rows for a class
        $kelas = $_GET['kelas'] ?? null;
        if ($kelas === null) throw new Exception('kelas parameter required for detail export');
        if ($kelas === '__empty__') {
            $sql = "SELECT s.id AS student_id, s.name AS student_name, a.date, a.time, a.status, a.note
                    FROM attendance a JOIN students s ON s.id = a.student_id
                    WHERE (s.kelas IS NULL OR s.kelas = '') AND a.date BETWEEN :start AND :end
                    ORDER BY s.name, a.date";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':start' => $period_start, ':end' => $period_end]);
        } else {
            $sql = "SELECT s.id AS student_id, s.name AS student_name, a.date, a.time, a.status, a.note
                    FROM attendance a JOIN students s ON s.id = a.student_id
                    WHERE s.kelas = :kelas AND a.date BETWEEN :start AND :end
                    ORDER BY s.name, a.date";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':kelas' => $kelas, ':start' => $period_start, ':end' => $period_end]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['student_id'], $r['student_name'], $r['date'], $r['time'] ?? '', $r['status'], $r['note'] ?? ''];
        }
        output_csv($out, ['Student ID','Nama','Tanggal','Waktu','Status','Catatan'], 'rekap_detail_kelas.csv');
    } else {
        // default summary per class export (similar to existing behavior)
        $kelas = $_GET['kelas'] ?? 'all';
        // attempt to detect class column name
        $class_column = null;
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
        if ($colInfo && isset($colInfo['COLUMN_NAME'])) $class_column = $colInfo['COLUMN_NAME'];

        if (!$class_column) {
            // fallback: no class column, export per student summary
            $sql = "SELECT s.id AS student_id, s.name AS student_name,
                    SUM(a.status='Hadir') AS hadir, SUM(a.status='Terlambat') AS telat,
                    SUM(a.status='Izin') AS izin, SUM(a.status='Sakit') AS sakit,
                    SUM(a.status='Alpha') AS alpha, COUNT(a.id) AS total_records
                    FROM students s
                    LEFT JOIN attendance a ON a.student_id = s.id AND a.date BETWEEN :start AND :end
                    GROUP BY s.id ORDER BY s.name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':start' => $period_start, ':end' => $period_end]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) $out[] = [$r['student_id'],$r['student_name'],$r['hadir'],$r['telat'],$r['izin'],$r['sakit'],$r['alpha'],$r['total_records']];
            output_csv($out, ['Student ID','Nama','Hadir','Terlambat','Izin','Sakit','Alpha','Total'], 'rekap_summary.csv');
        } else {
            // export per-class summary
            $sql = "SELECT COALESCE(s.`$class_column`,'(tanpa kelas)') AS kelas,
                    SUM(a.status='Hadir') AS hadir, SUM(a.status='Terlambat') AS telat,
                    SUM(a.status='Izin') AS izin, SUM(a.status='Sakit') AS sakit,
                    SUM(a.status='Alpha') AS alpha, COUNT(a.id) AS present_count, COUNT(s.id) AS class_size
                    FROM students s
                    LEFT JOIN attendance a ON a.student_id = s.id AND a.date BETWEEN :start AND :end
                    GROUP BY s.`$class_column` ORDER BY s.`$class_column`";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':start' => $period_start, ':end' => $period_end]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) $out[] = [$r['kelas'],$r['hadir'],$r['terlat'] ?? $r['terlambat'],$r['izin'],$r['sakit'],$r['alpha'],$r['present_count'],$r['class_size']];
            output_csv($out, ['Kelas','Hadir','Terlambat','Izin','Sakit','Alpha','Total Hadir','Ukuran Kelas'], 'rekap_summary_kelas.csv');
        }
    }
} catch (Exception $e) {
    // error: return simple CSV with error
    output_csv([[$e->getMessage()]], ['error'], 'export_error.csv');
}
?>