<?php
// rekap_detail.php - show detailed attendance rows for a class or a student within a period
// Supports query params:
// - period=daily|weekly|monthly
// - date / week_start & week_end / month
// - kelas OR student_id
// - option: for class: kelas=..., for student: student_id=...

// Determine period window (reuse same logic as rekap.php)
$period = isset($_GET['period']) ? trim($_GET['period']) : 'daily';
try {
    if ($period === 'weekly') {
        $start = isset($_GET['week_start']) ? $_GET['week_start'] : date('Y-m-d');
        $end = isset($_GET['week_end']) ? $_GET['week_end'] : $start;
        $period_start = $start;
        $period_end = $end;
        $label = "Mingguan ($period_start s/d $period_end)";
    } elseif ($period === 'monthly') {
        $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        $s = new DateTime($month . '-01');
        $e = (clone $s)->modify('last day of this month');
        $period_start = $s->format('Y-m-d');
        $period_end = $e->format('Y-m-d');
        $label = "Bulanan (" . $s->format('F Y') . ")";
    } else {
        $period_start = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $period_end = $period_start;
        $label = "Harian ($period_start)";
    }
} catch (Exception $e) {
    $period_start = date('Y-m-d');
    $period_end = date('Y-m-d');
    $label = "Harian ($period_start)";
}

if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) require_once __DIR__ . '/db.php';
    else throw new Exception('PDO $pdo not available.');
}

// decide mode: student detail or class detail
$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : null;
$kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : null;

// Fetch attendance rows
try {
    if ($student_id) {
        $sql = "SELECT a.*, s.name AS student_name, s.id AS student_id
                FROM attendance a
                JOIN students s ON s.id = a.student_id
                WHERE a.student_id = :student_id AND a.date BETWEEN :start AND :end
                ORDER BY a.date ASC, a.time ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':student_id' => $student_id, ':start' => $period_start, ':end' => $period_end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($kelas !== null) {
        // map special key for empty class
        if ($kelas === '__empty__') {
            $whereKelas = "(s.kelas IS NULL OR s.kelas = '')";
            $sql = "SELECT a.*, s.name AS student_name, s.id AS student_id, COALESCE(s.kelas, '(tanpa kelas)') AS kelas
                    FROM attendance a
                    JOIN students s ON s.id = a.student_id
                    WHERE ($whereKelas) AND a.date BETWEEN :start AND :end
                    ORDER BY s.name, a.date, a.time";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':start' => $period_start, ':end' => $period_end]);
        } else {
            $sql = "SELECT a.*, s.name AS student_name, s.id AS student_id, COALESCE(s.kelas, '(tanpa kelas)') AS kelas
                    FROM attendance a
                    JOIN students s ON s.id = a.student_id
                    WHERE s.kelas = :kelas AND a.date BETWEEN :start AND :end
                    ORDER BY s.name, a.date, a.time";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':kelas' => $kelas, ':start' => $period_start, ':end' => $period_end]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // fallback: list all attendance in period
        $sql = "SELECT a.*, s.name AS student_name, s.id AS student_id, COALESCE(s.kelas, '(tanpa kelas)') AS kelas
                FROM attendance a
                LEFT JOIN students s ON s.id = a.student_id
                WHERE a.date BETWEEN :start AND :end
                ORDER BY a.date, s.kelas, s.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $period_start, ':end' => $period_end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $rows = [];
}
?>
<div class="container-fluid">
  <h4>Detail Absensi - <?= htmlspecialchars($label) ?></h4>
  <?php if ($student_id): ?>
    <h5>Student ID: <?= htmlspecialchars($student_id) ?></h5>
  <?php elseif ($kelas !== null): ?>
    <h5>Kelas: <?= htmlspecialchars($kelas === '__empty__' ? '(tanpa kelas)' : $kelas) ?></h5>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <?php if (count($rows) === 0): ?>
        <div class="alert alert-info">Tidak ada data detail untuk kriteria yang dipilih.</div>
      <?php else: ?>
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>No</th>
              <th>Tanggal</th>
              <th>Waktu</th>
              <th>Student ID</th>
              <th>Nama</th>
              <th>Kelas</th>
              <th>Status</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; foreach ($rows as $r): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($r['date']) ?></td>
                <td><?= htmlspecialchars($r['time'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['student_id']) ?></td>
                <td><?= htmlspecialchars($r['student_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['kelas'] ?? ($r['kelas'] ?? '(tanpa kelas)')) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
                <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
?>