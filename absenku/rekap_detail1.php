<?php
// Detail per kelas untuk tanggal tertentu
// Params: date (YYYY-MM-DD, default today), kelas (value or '__empty__' for no class)
// Shows list of students in that class and their attendance on that date.
// Also provides Export CSV button linking to export_rekap.php?mode=detail&...

if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        throw new Exception('PDO $pdo not found. Please provide db.php or set $pdo before including this file.');
    }
}

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
    echo '<div class="alert alert-info">Kolom kelas tidak ditemukan pada tabel students. Halaman detail tidak tersedia.</div>';
    exit;
}

// Build query to list students for the selected kelas (or all)
$params = [':d' => $day];
if ($kelas === 'all') {
    $sql = "SELECT s.id, s.nis, s.name, s.`$class_column` AS kelas, a.status, a.time_in, a.note
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
            ORDER BY s.`$class_column` ASC, s.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} elseif ($kelas === '__empty__') {
    $sql = "SELECT s.id, s.nis, s.name, s.`$class_column` AS kelas, a.status, a.time_in, a.note
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
            WHERE (s.`$class_column` IS NULL OR s.`$class_column` = '')
            ORDER BY s.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} else {
    $sql = "SELECT s.id, s.nis, s.name, s.`$class_column` AS kelas, a.status, a.time_in, a.note
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :d
            WHERE s.`$class_column` = :kelas
            ORDER BY s.name ASC";
    $params[':kelas'] = $kelas;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid">
  <h4>Detail Kelas - <?= htmlspecialchars($kelas === '__empty__' ? '(Tanpa Kelas)' : $kelas) ?> - <?= htmlspecialchars($day) ?></h4>
  <p>
    <a class="btn btn-outline-success" href="export_rekap.php?mode=detail&date=<?= urlencode($day) ?>&kelas=<?= urlencode($kelas) ?>">Export CSV Detail</a>
    <a class="btn btn-secondary" href="rekap.php?page=rekap&date=<?= urlencode($day) ?>&kelas=<?= urlencode($kelas) ?>">Kembali</a>
  </p>

  <table class="table table-bordered">
    <thead>
      <tr><th>No</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Status</th><th>Jam</th><th>Catatan</th></tr>
    </thead>
    <tbody>
    <?php if (count($list) === 0): ?>
      <tr><td colspan="7">Tidak ada siswa untuk kelas/tanggal yang dipilih.</td></tr>
    <?php else: $i=1; foreach ($list as $r): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($r['nis']) ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['kelas'] ?? '(tanpa kelas)') ?></td>
        <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['time_in'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>