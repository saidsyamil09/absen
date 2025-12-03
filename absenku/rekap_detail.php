<?php
// rekap_detail.php
// Menampilkan detail absensi untuk kelas atau siswa dalam periode (daily/weekly/monthly)
// Usage examples:
//  - Per kelas:     rekap_detail.php?period=daily&date=2025-12-03&kelas=10A
//  - Per kelas kosong: rekap_detail.php?period=daily&date=2025-12-03&kelas=__empty__
//  - Per siswa:     rekap_detail.php?period=weekly&week_start=2025-11-24&week_end=2025-11-30&student_id=123
// Untuk debugging tambahkan &debug=1
// Updated: 2025-12-03

// --- Bootstrap / DB connection ---
if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        // Minimal fallback message (tidak throw agar file bisa di-copy/paste dengan mudah)
        echo "<div style='padding:16px;font-family:Arial,Helvetica,sans-serif'>";
        echo "<strong>Error:</strong> PDO \$pdo tidak tersedia dan db.php tidak ditemukan. Silakan buat file db.php yang menginisialisasi \$pdo.";
        echo "</div>";
        exit;
    }
}

$debug = isset($_GET['debug']) && in_array($_GET['debug'], ['1','true','yes'], true);

// --- Period normalization ---
$period = isset($_GET['period']) ? trim($_GET['period']) : 'daily';
try {
    if ($period === 'weekly') {
        $period_start = $_GET['week_start'] ?? ($_GET['date'] ?? date('Y-m-d'));
        $period_end   = $_GET['week_end']   ?? $period_start;
        $label = "Mingguan ($period_start s/d $period_end)";
    } elseif ($period === 'monthly') {
        $month = $_GET['month'] ?? date('Y-m');
        $s = new DateTime($month . '-01');
        $e = (clone $s)->modify('last day of this month');
        $period_start = $s->format('Y-m-d');
        $period_end   = $e->format('Y-m-d');
        $label = "Bulanan (" . $s->format('F Y') . ")";
    } else {
        $period_start = $_GET['date'] ?? date('Y-m-d');
        $period_end   = $period_start;
        $label = "Harian ($period_start)";
    }
} catch (Exception $ex) {
    $period_start = date('Y-m-d');
    $period_end = date('Y-m-d');
    $label = "Harian ($period_start)";
}

// --- Inputs ---
$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : null;
$kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : null;

// --- Detect class column ('kelas' or 'class') if exists ---
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
    // ignore, class_column tetap null
    $class_column = null;
}

// --- Build & Execute Query ---
$rows = [];
$sql = '';
$params = [':start' => $period_start, ':end' => $period_end];
$errorMsg = '';

try {
    if ($student_id) {
        // Detail per siswa
        $sql = "SELECT a.date, a.time, a.status, a.note,
                       s.id AS student_id, s.name AS student_name" .
               ($class_column ? ", COALESCE(s.`$class_column`, '(tanpa kelas)') AS kelas" : "") .
               " FROM attendance a
                 JOIN students s ON s.id = a.student_id
                 WHERE a.student_id = :student_id AND DATE(a.date) BETWEEN :start AND :end
                 ORDER BY a.date ASC, a.time ASC";
        $params[':student_id'] = $student_id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($kelas !== null) {
        // Detail per kelas
        if (!$class_column) {
            $rows = [];
            $notice = "Kolom kelas tidak ditemukan pada tabel students. Tidak dapat menampilkan detail berdasarkan kelas.";
        } else {
            if ($kelas === '__empty__') {
                $whereKelas = " (s.`$class_column` IS NULL OR s.`$class_column` = '') ";
                $sql = "SELECT a.date, a.time, a.status, a.note,
                               s.id AS student_id, s.name AS student_name,
                               COALESCE(s.`$class_column`, '(tanpa kelas)') AS kelas
                        FROM attendance a
                        JOIN students s ON s.id = a.student_id
                        WHERE $whereKelas AND DATE(a.date) BETWEEN :start AND :end
                        ORDER BY s.name ASC, a.date ASC, a.time ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql = "SELECT a.date, a.time, a.status, a.note,
                               s.id AS student_id, s.name AS student_name,
                               COALESCE(s.`$class_column`, '(tanpa kelas)') AS kelas
                        FROM attendance a
                        JOIN students s ON s.id = a.student_id
                        WHERE s.`$class_column` = :kelas AND DATE(a.date) BETWEEN :start AND :end
                        ORDER BY s.name ASC, a.date ASC, a.time ASC";
                $params[':kelas'] = $kelas;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } else {
        // Semua attendance dalam periode
        $sql = "SELECT a.date, a.time, a.status, a.note,
                       s.id AS student_id, s.name AS student_name" .
               ($class_column ? ", COALESCE(s.`$class_column`, '(tanpa kelas)') AS kelas" : "") .
               " FROM attendance a
                 LEFT JOIN students s ON s.id = a.student_id
                 WHERE DATE(a.date) BETWEEN :start AND :end
                 ORDER BY a.date ASC, s.name ASC, a.time ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $rows = [];
    $errorMsg = $e->getMessage();
}

// --- Debug output (optional) ---
if ($debug) {
    echo "<pre style='background:#f8f9fa;padding:12px;border:1px solid #ddd;margin:12px 0'>";
    echo "SQL:\n" . ($sql ?: "(no SQL)") . "\n\n";
    echo "Params:\n"; var_export($params); echo "\n\n";
    echo "Rows fetched: " . count($rows) . "\n";
    if ($errorMsg) echo "Error: " . htmlspecialchars($errorMsg) . "\n";
    echo "</pre>";
}

// --- Render HTML ---
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Detail Absensi</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container-fluid py-3">
  <h4>Detail Absensi - <?= htmlspecialchars($label) ?></h4>

  <?php if ($student_id): ?>
    <h5>Student ID: <?= htmlspecialchars($student_id) ?></h5>
  <?php elseif ($kelas !== null): ?>
    <h5>Kelas: <?= htmlspecialchars($kelas === '__empty__' ? '(tanpa kelas)' : $kelas) ?></h5>
  <?php endif; ?>

  <div class="card mt-3">
    <div class="card-body">
      <?php if (!empty($notice)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($notice) ?></div>
      <?php elseif (count($rows) === 0): ?>
        <div class="alert alert-info">Tidak ada data detail untuk kriteria yang dipilih.</div>
        <?php if (!$debug): ?>
          <div class="small text-muted">
            Tips debug:
            <ul>
              <li>Pastikan parameter period + date/week_start+week_end/month benar.</li>
              <li>Untuk kelas, pastikan tabel students memiliki kolom 'kelas' atau 'class'.</li>
              <li>Jika kolom nama bukan 'name', ubah reference s.name pada query atau beritahu skema DB.</li>
              <li>Tambahkan &amp;debug=1 pada URL untuk melihat SQL yang dieksekusi.</li>
            </ul>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Waktu</th>
                <th>Student ID</th>
                <th>Nama</th>
                <?php if ($class_column): ?><th>Kelas</th><?php endif; ?>
                <th>Status</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; foreach ($rows as $r): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($r['date']) ?></td>
                  <td><?= htmlspecialchars($r['time'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['student_id'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['student_name'] ?? '') ?></td>
                  <?php if ($class_column): ?><td><?= htmlspecialchars($r['kelas'] ?? '(tanpa kelas)') ?></td><?php endif; ?>
                  <td><?= htmlspecialchars($r['status'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($errorMsg): ?>
        <div class="mt-3 alert alert-danger"><strong>Query error:</strong> <?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
?>