<?php
// Rekap per siswa + rekap harian per kelas + rekap minggu & bulan
// (diperbarui: tombol Export CSV & link Detail per kelas + weekly/monthly)
// get search query from GET, trim whitespace
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// date for daily summary (default today)
$day = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

// selected class (optional)
$selected_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : 'all';

// period UI: 'day'|'week'|'month'
$period = isset($_GET['period']) ? $_GET['period'] : 'day';

// week (HTML input type="week" format: YYYY-Www)
$week = isset($_GET['week']) ? $_GET['week'] : (new DateTime())->format('o-\WW');

// month (HTML input type="month" format: YYYY-MM)
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// ensure $pdo available (if this file is included from a central loader it may already be set)
if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        throw new Exception('PDO $pdo not found. Please provide db.php or set $pdo before including this file.');
    }
}

// detect if students table has a class column ('kelas' or 'class')
$class_column = null;
$classes = [];
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
        // fetch distinct class values for select box (excluding null/empty)
        $cStmt = $pdo->prepare("SELECT DISTINCT `$class_column` AS kelas FROM students WHERE `$class_column` IS NOT NULL AND `$class_column` != '' ORDER BY `$class_column` ASC");
        $cStmt->execute();
        $classes = array_map(function($r){ return $r['kelas']; }, $cStmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (Exception $e) {
    // If information_schema is not accessible or other error, treat as no class column
    $class_column = null;
    $classes = [];
}

// ---------- Helper: detect if aggregate summary table exists ----------
function tableExists(PDO $pdo, $table) {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
    $stmt->execute([':t' => $table]);
    return (bool)$stmt->fetchColumn();
}

$has_student_summary = tableExists($pdo, 'attendance_summary_student_period');
$has_class_summary = tableExists($pdo, 'attendance_summary_class_period');

// NOTE: Per-user requested to hide the raw database view / per-student listing.
// The per-student query and the "Rekap per Siswa" HTML table have been removed
// so only the class/day/week/month summaries (and their export/detail links) remain.

// ---------- Per-kelas / per-period summaries ----------
// We'll prepare three summary modes: day (existing), week, month
$daily_summary = [];
$weekly_summary = [];
$monthly_summary = [];

if ($class_column) {
    // DAILY: existing (kept)
    try {
        if ($selected_kelas !== '' && $selected_kelas !== 'all' && $selected_kelas !== '__empty__') {
            $summarySql = "SELECT s.`$class_column` AS kelas,
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
            $summaryStmt = $pdo->prepare($summarySql);
            $summaryStmt->execute([':d' => $day, ':kelas' => $selected_kelas]);
        } elseif ($selected_kelas === '__empty__') {
            $summarySql = "SELECT '(tanpa kelas)' AS kelas,
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
            $summaryStmt = $pdo->prepare($summarySql);
            $summaryStmt->execute([':d' => $day]);
        } else {
            $summarySql = "SELECT s.`$class_column` AS kelas,
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
            $summaryStmt = $pdo->prepare($summarySql);
            $summaryStmt->execute([':d' => $day]);
        }
        $daily_summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $daily_summary = [];
    }

    // WEEKLY
    try {
        // compute ISO week number key and start date
        // input $week expected like "2025-W49"
        $wk_year = null; $wk_num = null;
        if (preg_match('/^(\d{4})-W?(\d{1,2})$/', $week, $m)) {
            $wk_year = (int)$m[1];
            $wk_num = (int)$m[2];
        } else {
            // fallback to current week
            $d = new DateTime();
            $wk_year = (int)$d->format('o');
            $wk_num = (int)$d->format('W');
        }
        // get iso week start date (Monday)
        $dt = new DateTime();
        $dt->setISODate($wk_year, $wk_num);
        $week_start = $dt->format('Y-m-d');
        // compute YEARWEEK value (iso mode) to compare in SQL (mode 3 = ISO)
        $yw = (int)date('oW', strtotime($week_start)); // like 202549

        // Prefer using class_summary aggregate table if exists
        if ($has_class_summary) {
            // use attendance_summary_class_period where period_type='week' and period_key like 'YYYY-Www'
            $period_key = sprintf('%04d-W%02d', $wk_year, $wk_num);
            if ($selected_kelas !== '' && $selected_kelas !== 'all' && $selected_kelas !== '__empty__') {
                $s = $pdo->prepare("SELECT kelas, hadir, telat, izin, sakit, alpha, total_present AS present_count, class_size
                                     FROM attendance_summary_class_period
                                     WHERE period_type='week' AND period_key = :pk AND kelas = :kelas");
                $s->execute([':pk' => $period_key, ':kelas' => $selected_kelas]);
            } elseif ($selected_kelas === '__empty__') {
                $s = $pdo->prepare("SELECT '(tanpa kelas)' AS kelas, hadir, telat, izin, sakit, alpha, total_present AS present_count, class_size
                                     FROM attendance_summary_class_period
                                     WHERE period_type='week' AND period_key = :pk AND (kelas IS NULL OR kelas = '')");
                $s->execute([':pk' => $period_key]);
            } else {
                $s = $pdo->prepare("SELECT kelas, hadir, telat, izin, sakit, alpha, total_present AS present_count, class_size
                                     FROM attendance_summary_class_period
                                     WHERE period_type='week' AND period_key = :pk
                                     ORDER BY kelas ASC");
                $s->execute([':pk' => $period_key]);
            }
            $weekly_summary = $s->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // compute on the fly using YEARWEEK
            if ($selected_kelas !== '' && $selected_kelas !== 'all' && $selected_kelas !== '__empty__') {
                $summarySql = "SELECT s.`$class_column` AS kelas,
                    SUM(a.status='Hadir') AS hadir,
                    SUM(a.status='Terlambat') AS telat,
                    SUM(a.status='Izin') AS izin,
                    SUM(a.status='Sakit') AS sakit,
                    SUM(a.status='Alpha') AS alpha,
                    COUNT(a.id) AS present_count,
                    COUNT(s.id) AS class_size
                  FROM students s
                  LEFT JOIN attendance a ON a.student_id = s.id AND YEARWEEK(a.date,3) = :yw
                  WHERE s.`$class_column` = :kelas
                  GROUP BY s.`$class_column`
                  ORDER BY s.`$class_column` ASC";
                $summaryStmt = $pdo->prepare($summarySql);
                $summaryStmt->execute([':yw' => $yw, ':kelas' => $selected_kelas]);
            } elseif ($selected_kelas === '__empty__') {
                $summarySql = "SELECT '(tanpa kelas)' AS kelas,
                    SUM(a.status='Hadir') AS hadir,
                    SUM(a.status='Terlambat') AS telat,
                    SUM(a.status='Izin') AS izin,
                    SUM(a.status='Sakit') AS sakit,
                    SUM(a.status='Alpha') AS alpha,
                    COUNT(a.id) AS present_count,
                    COUNT(s.id) AS class_size
                  FROM students s
                  LEFT JOIN attendance a ON a.student_id = s.id AND YEARWEEK(a.date,3) = :yw
                  WHERE (s.`$class_column` IS NULL OR s.`$class_column` = '')
                  GROUP BY kelas";
                $summaryStmt = $pdo->prepare($summarySql);
                $summaryStmt->execute([':yw' => $yw]);
            } else {
                $summarySql = "SELECT s.`$class_column` AS kelas,
                    SUM(a.status='Hadir') AS hadir,
                    SUM(a.status='Terlambat') AS telat,
                    SUM(a.status='Izin') AS izin,
                    SUM(a.status='Sakit') AS sakit,
                    SUM(a.status='Alpha') AS alpha,
                    COUNT(a.id) AS present_count,
                    COUNT(s.id) AS class_size
                  FROM students s
                  LEFT JOIN attendance a ON a.student_id = s.id AND YEARWEEK(a.date,3) = :yw
                  GROUP BY s.`$class_column`
                  ORDER BY s.`$class_column` ASC";
                $summaryStmt = $pdo->prepare($summarySql);
                $summaryStmt->execute([':yw' => $yw]);
            }
            $weekly_summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $weekly_summary = [];
    }

    // MONTHLY
    try {
        // period key like YYYY-MM
        $ym = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');

        if ($has_class_summary) {
            if ($selected_kelas !== '' && $selected_kelas !== 'all' && $selected_kelas !== '__empty__') {
                $s = $pdo->prepare("SELECT kelas, hadir, telat, izin, sakit, alpha, total_present AS present_count, class_size
                                     FROM attendance_summary_class_period
                                     WHERE period_type='month' AND period_key = :pk AND kelas = :kelas");
                $s->execute([':pk' => $ym, ':kelas' => $selected_kelas]);
            } elseif ($selected_kelas === '__empty__') {
                $s = $pdo->prepare("SELECT '(tanpa kelas)' AS kelas, hadir, telat, izin, sakit, alpha, total_present AS present_count, class_size
                                     FROM attendance_summary_class_period
                                     WHERE period_type='month' AND period_key = :pk AND (kelas IS NULL OR kelas = '')");
                $s->execute([':pk' => $ym]);
            } else {
                $s = $pdo->prepare("SELECT kelas, hadir, telat, izin, sakit, alpha, total_present AS present_count, class_size
                                     FROM attendance_summary_class_period
                                     WHERE period_type='month' AND period_key = :pk
                                     ORDER BY kelas ASC");
                $s->execute([':pk' => $ym]);
            }
            $monthly_summary = $s->fetchAll(PDO::FETCH_ASSOC);
        } else {
            if ($selected_kelas !== '' && $selected_kelas !== 'all' && $selected_kelas !== '__empty__') {
                $summarySql = "SELECT s.`$class_column` AS kelas,
                    SUM(a.status='Hadir') AS hadir,
                    SUM(a.status='Terlambat') AS telat,
                    SUM(a.status='Izin') AS izin,
                    SUM(a.status='Sakit') AS sakit,
                    SUM(a.status='Alpha') AS alpha,
                    COUNT(a.id) AS present_count,
                    COUNT(s.id) AS class_size
                  FROM students s
                  LEFT JOIN attendance a ON a.student_id = s.id AND DATE_FORMAT(a.date,'%Y-%m') = :ym
                  WHERE s.`$class_column` = :kelas
                  GROUP BY s.`$class_column`
                  ORDER BY s.`$class_column` ASC";
                $summaryStmt = $pdo->prepare($summarySql);
                $summaryStmt->execute([':ym' => $ym, ':kelas' => $selected_kelas]);
            } elseif ($selected_kelas === '__empty__') {
                $summarySql = "SELECT '(tanpa kelas)' AS kelas,
                    SUM(a.status='Hadir') AS hadir,
                    SUM(a.status='Terlambat') AS telat,
                    SUM(a.status='Izin') AS izin,
                    SUM(a.status='Sakit') AS sakit,
                    SUM(a.status='Alpha') AS alpha,
                    COUNT(a.id) AS present_count,
                    COUNT(s.id) AS class_size
                  FROM students s
                  LEFT JOIN attendance a ON a.student_id = s.id AND DATE_FORMAT(a.date,'%Y-%m') = :ym
                  WHERE (s.`$class_column` IS NULL OR s.`$class_column` = '')
                  GROUP BY kelas";
                $summaryStmt = $pdo->prepare($summarySql);
                $summaryStmt->execute([':ym' => $ym]);
            } else {
                $summarySql = "SELECT s.`$class_column` AS kelas,
                    SUM(a.status='Hadir') AS hadir,
                    SUM(a.status='Terlambat') AS telat,
                    SUM(a.status='Izin') AS izin,
                    SUM(a.status='Sakit') AS sakit,
                    SUM(a.status='Alpha') AS alpha,
                    COUNT(a.id) AS present_count,
                    COUNT(s.id) AS class_size
                  FROM students s
                  LEFT JOIN attendance a ON a.student_id = s.id AND DATE_FORMAT(a.date,'%Y-%m') = :ym
                  GROUP BY s.`$class_column`
                  ORDER BY s.`$class_column` ASC";
                $summaryStmt = $pdo->prepare($summarySql);
                $summaryStmt->execute([':ym' => $ym]);
            }
            $monthly_summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $monthly_summary = [];
    }
}
?>
<div class="container-fluid">
  <h4>Rekap Absensi</h4>

  <div class="card mb-3">
    <div class="card-body">
      <!-- Removed raw database view (per-student listing) as requested.
           The page now only shows per-class daily/weekly/monthly summaries. -->

      <!-- Daily by class form -->
      <h5>Rekap Harian per Kelas</h5>
      <?php if ($class_column): ?>
      <form class="form-inline mb-3" method="get" action="">
        <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? 'rekap') ?>">
        <div class="form-group mr-2">
          <label for="date" class="mr-2">Tanggal:</label>
          <input type="date" id="date" name="date" class="form-control" value="<?= htmlspecialchars($day) ?>">
        </div>
        <div class="form-group mr-2">
          <label for="kelas" class="mr-2">Kelas:</label>
          <select id="kelas" name="kelas" class="form-control">
            <option value="all" <?= $selected_kelas === 'all' ? 'selected' : '' ?>>Semua Kelas</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= $c === $selected_kelas ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
            <option value="__empty__" <?= $selected_kelas === '__empty__' ? 'selected' : '' ?>>(Tanpa Kelas)</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary mr-2">Tampilkan</button>

        <!-- Export button for current selection -->
        <a class="btn btn-outline-success" href="export_rekap.php?mode=summary&date=<?= urlencode($day) ?>&kelas=<?= urlencode($selected_kelas) ?>">Export CSV Rekap Harian</a>
      </form>

     <!-- <table class="table table-bordered mb-4">
        <thead>
          <tr>
            <th>No</th>
            <th>Kelas</th>
            <th>Hadir</th>
            <th>Terlambat</th>
            <th>Izin</th>
            <th>Sakit</th>
            <th>Alpha</th>
            <th>Total Hadir</th>
            <th>Ukuran Kelas</th>
            <th>% Hadir</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($daily_summary) === 0): ?>
          <tr><td colspan="11">Tidak ada data untuk tanggal/kelas yang dipilih.</td></tr>
        <?php else: $i=1; foreach ($daily_summary as $ds):
            $hadir = (int)($ds['hadir'] ?? 0);
            $telat = (int)($ds['telat'] ?? 0);
            $izin = (int)($ds['izin'] ?? 0);
            $sakit = (int)($ds['sakit'] ?? 0);
            $alpha = (int)($ds['alpha'] ?? 0);
            $present = (int)($ds['present_count'] ?? 0);
            $size = (int)($ds['class_size'] ?? 0);
            $percent = $size > 0 ? round(($present / $size) * 100, 1) : 0;

            // kelas key for links: use '__empty__' for (tanpa kelas)
            $kelas_key = ($ds['kelas'] === null || $ds['kelas'] === '') ? '__empty__' : $ds['kelas'];
        ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($ds['kelas'] ?? '(tanpa kelas)') ?></td>
            <td><span class="badge badge-success"><?= $hadir ?></span></td>
            <td><span class="badge badge-warning"><?= $telat ?></span></td>
            <td><span class="badge badge-info"><?= $izin ?></span></td>
            <td><span class="badge badge-secondary"><?= $sakit ?></span></td>
            <td><span class="badge badge-danger"><?= $alpha ?></span></td>
            <td><?= $present ?></td>
            <td><?= $size ?></td>
            <td><?= $percent ?>%</td>
            <td>
              <a class="btn btn-sm btn-primary" href="rekap_detail.php?date=<?= urlencode($day) ?>&kelas=<?= urlencode($kelas_key) ?>">Detail</a>
              <a class="btn btn-sm btn-outline-success" href="export_rekap.php?mode=detail&date=<?= urlencode($day) ?>&kelas=<?= urlencode($kelas_key) ?>">Export CSV</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table> -->

      <!-- WEEKLY -->
      <h5>Rekap Mingguan per Kelas</h5>
      <form class="form-inline mb-3" method="get" action="">
        <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? 'rekap') ?>">
        <input type="hidden" name="period" value="week">
        <div class="form-group mr-2">
          <label for="week" class="mr-2">Minggu (ISO):</label>
          <input type="week" id="week" name="week" class="form-control" value="<?= htmlspecialchars($week) ?>">
        </div>
        <div class="form-group mr-2">
          <label for="kelas_w" class="mr-2">Kelas:</label>
          <select id="kelas_w" name="kelas" class="form-control">
            <option value="all" <?= $selected_kelas === 'all' ? 'selected' : '' ?>>Semua Kelas</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= $c === $selected_kelas ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
            <option value="__empty__" <?= $selected_kelas === '__empty__' ? 'selected' : '' ?>>(Tanpa Kelas)</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary mr-2">Tampilkan</button>
        <a class="btn btn-outline-success" href="export_rekap.php?mode=summary_period&period=week&week=<?= urlencode($week) ?>&kelas=<?= urlencode($selected_kelas) ?>">Export CSV Rekap Mingguan</a>
      </form>

     <!-- <table class="table table-bordered mb-4">
        <thead>
          <tr>
            <th>No</th><th>Kelas</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpha</th><th>Total Hadir</th><th>Ukuran Kelas</th><th>% Hadir</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($weekly_summary) === 0): ?>
          <tr><td colspan="11">Tidak ada data untuk minggu/kelas yang dipilih.</td></tr>
        <?php else: $i=1; foreach ($weekly_summary as $ds):
            $hadir = (int)($ds['hadir'] ?? 0);
            $telat = (int)($ds['telat'] ?? 0);
            $izin = (int)($ds['izin'] ?? 0);
            $sakit = (int)($ds['sakit'] ?? 0);
            $alpha = (int)($ds['alpha'] ?? 0);
            $present = (int)($ds['present_count'] ?? 0);
            $size = (int)($ds['class_size'] ?? 0);
            $percent = $size > 0 ? round(($present / $size) * 100, 1) : 0;
            $kelas_key = ($ds['kelas'] === null || $ds['kelas'] === '') ? '__empty__' : $ds['kelas'];
        ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($ds['kelas'] ?? '(tanpa kelas)') ?></td>
            <td><span class="badge badge-success"><?= $hadir ?></span></td>
            <td><span class="badge badge-warning"><?= $telat ?></span></td>
            <td><span class="badge badge-info"><?= $izin ?></span></td>
            <td><span class="badge badge-secondary"><?= $sakit ?></span></td>
            <td><span class="badge badge-danger"><?= $alpha ?></span></td>
            <td><?= $present ?></td>
            <td><?= $size ?></td>
            <td><?= $percent ?>%</td>
            <td>
              <a class="btn btn-sm btn-primary" href="rekap_detail.php?period=week&week=<?= urlencode($week) ?>&kelas=<?= urlencode($kelas_key) ?>">Detail</a>
              <a class="btn btn-sm btn-outline-success" href="export_rekap.php?mode=detail_period&period=week&week=<?= urlencode($week) ?>&kelas=<?= urlencode($kelas_key) ?>">Export CSV</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table> -->

      <!-- MONTHLY -->
      <h5>Rekap Bulanan per Kelas</h5>
      <form class="form-inline mb-3" method="get" action="">
        <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? 'rekap') ?>">
        <input type="hidden" name="period" value="month">
        <div class="form-group mr-2">
          <label for="month" class="mr-2">Bulan:</label>
          <input type="month" id="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>">
        </div>
        <div class="form-group mr-2">
          <label for="kelas_m" class="mr-2">Kelas:</label>
          <select id="kelas_m" name="kelas" class="form-control">
            <option value="all" <?= $selected_kelas === 'all' ? 'selected' : '' ?>>Semua Kelas</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= $c === $selected_kelas ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
            <option value="__empty__" <?= $selected_kelas === '__empty__' ? 'selected' : '' ?>>(Tanpa Kelas)</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary mr-2">Tampilkan</button>
        <a class="btn btn-outline-success" href="export_rekap.php?mode=summary_period&period=month&month=<?= urlencode($month) ?>&kelas=<?= urlencode($selected_kelas) ?>">Export CSV Rekap Bulanan</a>
      </form>

      <!-- <table class="table table-bordered mb-4">
        <thead>
          <tr>
            <th>No</th><th>Kelas</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpha</th><th>Total Hadir</th><th>Ukuran Kelas</th><th>% Hadir</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($monthly_summary) === 0): ?>
          <tr><td colspan="11">Tidak ada data untuk bulan/kelas yang dipilih.</td></tr>
        <?php else: $i=1; foreach ($monthly_summary as $ds):
            $hadir = (int)($ds['hadir'] ?? 0);
            $telat = (int)($ds['telat'] ?? 0);
            $izin = (int)($ds['izin'] ?? 0);
            $sakit = (int)($ds['sakit'] ?? 0);
            $alpha = (int)($ds['alpha'] ?? 0);
            $present = (int)($ds['present_count'] ?? 0);
            $size = (int)($ds['class_size'] ?? 0);
            $percent = $size > 0 ? round(($present / $size) * 100, 1) : 0;
            $kelas_key = ($ds['kelas'] === null || $ds['kelas'] === '') ? '__empty__' : $ds['kelas'];
        ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($ds['kelas'] ?? '(tanpa kelas)') ?></td>
            <td><span class="badge badge-success"><?= $hadir ?></span></td>
            <td><span class="badge badge-warning"><?= $telat ?></span></td>
            <td><span class="badge badge-info"><?= $izin ?></span></td>
            <td><span class="badge badge-secondary"><?= $sakit ?></span></td>
            <td><span class="badge badge-danger"><?= $alpha ?></span></td>
            <td><?= $present ?></td>
            <td><?= $size ?></td>
            <td><?= $percent ?>%</td>
            <td>
              <a class="btn btn-sm btn-primary" href="rekap_detail.php?period=month&month=<?= urlencode($month) ?>&kelas=<?= urlencode($kelas_key) ?>">Detail</a>
              <a class="btn btn-sm btn-outline-success" href="export_rekap.php?mode=detail_period&period=month&month=<?= urlencode($month) ?>&kelas=<?= urlencode($kelas_key) ?>">Export CSV</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table> -->

      <?php else: ?>
        <div class="alert alert-info">Kolom kelas tidak ditemukan pada tabel students. Fitur rekap harian per kelas tidak tersedia. Untuk mengaktifkannya, tambahkan kolom 'kelas' atau 'class' pada tabel students.</div>
      <?php endif; ?>

    </div>
  </div>
</div>