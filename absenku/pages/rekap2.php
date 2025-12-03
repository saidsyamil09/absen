<?php
// Rekap per kelas: harian / mingguan / bulanan (diperbarui: tambah rekap mingguan & bulanan, hilangkan tampilan tabel per-siswa)
// date: 2025-12-03

// selected period: 'daily', 'weekly', 'monthly'
$period = isset($_GET['period']) ? trim($_GET['period']) : 'daily';

// date params for each period
$day = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');              // for daily
$week_ref = isset($_GET['week']) ? trim($_GET['week']) : (isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d')); // for weekly - reference date within week
$month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');          // for monthly (YYYY-MM)

// selected class (optional)
$selected_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : 'all';

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

// compute period start/end
$period_start = null;
$period_end = null;
$period_label = '';

try {
    if ($period === 'weekly') {
        // compute week start (Monday) and week end (Sunday) from $week_ref
        $dt = new DateTime($week_ref);
        // Ensure we're using a date-only string (if time present)
        $dt->setTime(0,0,0);
        // 'Monday this week' works relative to the date
        $weekStart = clone $dt;
        $weekStart->modify('monday this week');
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');
        $period_start = $weekStart->format('Y-m-d');
        $period_end = $weekEnd->format('Y-m-d');
        $period_label = 'Mingguan (' . $period_start . ' s/d ' . $period_end . ')';
    } elseif ($period === 'monthly') {
        // $month expected in YYYY-MM
        // build first day and last day of month
        $start = new DateTime($month . '-01');
        $end = (clone $start)->modify('last day of this month');
        $period_start = $start->format('Y-m-d');
        $period_end = $end->format('Y-m-d');
        $period_label = 'Bulanan (' . $start->format('F Y') . ')';
    } else {
        // default daily
        $period_start = $day;
        $period_end = $day;
        $period_label = 'Harian (' . $day . ')';
    }
} catch (Exception $e) {
    // fallback to today if parsing fails
    $period_start = date('Y-m-d');
    $period_end = date('Y-m-d');
    $period = 'daily';
    $period_label = 'Harian (' . $period_start . ')';
}

// Prepare period summary per class if class column exists
$period_summary = [];
if ($class_column) {
    try {
        // Build base SQL - attendance join limited to period range
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
              LEFT JOIN attendance a ON a.student_id = s.id AND a.date BETWEEN :start AND :end
              WHERE s.`$class_column` = :kelas
              GROUP BY s.`$class_column`
              ORDER BY s.`$class_column` ASC";
            $summaryStmt = $pdo->prepare($summarySql);
            $summaryStmt->execute([':start' => $period_start, ':end' => $period_end, ':kelas' => $selected_kelas]);
        } elseif ($selected_kelas === '__empty__') {
            // special case: students without kelas (NULL or empty)
            $summarySql = "SELECT '(tanpa kelas)' AS kelas,
                SUM(a.status='Hadir') AS hadir,
                SUM(a.status='Terlambat') AS telat,
                SUM(a.status='Izin') AS izin,
                SUM(a.status='Sakit') AS sakit,
                SUM(a.status='Alpha') AS alpha,
                COUNT(a.id) AS present_count,
                COUNT(s.id) AS class_size
              FROM students s
              LEFT JOIN attendance a ON a.student_id = s.id AND a.date BETWEEN :start AND :end
              WHERE (s.`$class_column` IS NULL OR s.`$class_column` = '')
              GROUP BY kelas";
            $summaryStmt = $pdo->prepare($summarySql);
            $summaryStmt->execute([':start' => $period_start, ':end' => $period_end]);
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
              LEFT JOIN attendance a ON a.student_id = s.id AND a.date BETWEEN :start AND :end
              GROUP BY s.`$class_column`
              ORDER BY s.`$class_column` ASC";
            $summaryStmt = $pdo->prepare($summarySql);
            $summaryStmt->execute([':start' => $period_start, ':end' => $period_end]);
        }
        $period_summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $period_summary = [];
    }
}
?>
<div class="container-fluid">
  <h4>Rekap Absensi - <?= htmlspecialchars($period_label) ?></h4>

  <div class="card mb-3">
    <div class="card-body">
      <!-- Period form -->
      <h5>Rekap per Periode</h5>
      <?php if ($class_column): ?>
      <form class="form-inline mb-3" method="get" action="">
        <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? 'rekap') ?>">
        <div class="form-group mr-2">
          <label for="period" class="mr-2">Periode:</label>
          <select id="period" name="period" class="form-control">
            <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Harian</option>
            <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Mingguan</option>
            <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
          </select>
        </div>

        <div id="input-daily" class="form-group mr-2" style="display: <?= $period === 'daily' ? 'inline-block' : 'none' ?>;">
          <label for="date" class="mr-2">Tanggal:</label>
          <input type="date" id="date" name="date" class="form-control" value="<?= htmlspecialchars($day) ?>">
        </div>

        <div id="input-week" class="form-group mr-2" style="display: <?= $period === 'weekly' ? 'inline-block' : 'none' ?>;">
          <label for="week" class="mr-2">Pilih Tanggal (minggu berisi tanggal ini):</label>
          <input type="date" id="week" name="week" class="form-control" value="<?= htmlspecialchars($week_ref) ?>">
        </div>

        <div id="input-month" class="form-group mr-2" style="display: <?= $period === 'monthly' ? 'inline-block' : 'none' ?>;">
          <label for="month" class="mr-2">Bulan:</label>
          <input type="month" id="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>">
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
        <?php
          // Build export URL parameters according to period
          $exportParams = [
            'mode' => 'summary',
            'period' => $period,
            'kelas' => $selected_kelas,
          ];
          if ($period === 'daily') {
            $exportParams['date'] = $period_start;
          } elseif ($period === 'weekly') {
            $exportParams['week_start'] = $period_start;
            $exportParams['week_end'] = $period_end;
          } else { // monthly
            $exportParams['month'] = $month;
          }
          $exportQuery = http_build_query($exportParams);
        ?>
        <a class="btn btn-outline-success" href="export_rekap.php?<?= $exportQuery ?>">Export CSV Rekap <?= htmlspecialchars(ucfirst($period)) ?></a>
      </form>

      <table class="table table-bordered mb-4">
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
        <?php if (count($period_summary) === 0): ?>
          <tr><td colspan="11">Tidak ada data untuk periode/kelas yang dipilih.</td></tr>
        <?php else: $i=1; foreach ($period_summary as $ds):
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

            // build detail/export links for this class (pass the same period params)
            $detailParams = ['page' => 'rekap_detail', 'kelas' => $kelas_key, 'period' => $period];
            $exportDetailParams = ['mode' => 'detail', 'kelas' => $kelas_key, 'period' => $period];

            if ($period === 'daily') {
              $detailParams['date'] = $period_start;
              $exportDetailParams['date'] = $period_start;
            } elseif ($period === 'weekly') {
              $detailParams['week_start'] = $period_start;
              $detailParams['week_end'] = $period_end;
              $exportDetailParams['week_start'] = $period_start;
              $exportDetailParams['week_end'] = $period_end;
            } else {
              $detailParams['month'] = $month;
              $exportDetailParams['month'] = $month;
            }

            $detailUrl = 'rekap_detail.php?' . http_build_query($detailParams);
            $exportUrl = 'export_rekap.php?' . http_build_query($exportDetailParams);
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
              <a class="btn btn-sm btn-primary" href="<?= $detailUrl ?>">Detail</a>
              <a class="btn btn-sm btn-outline-success" href="<?= $exportUrl ?>">Export CSV</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="alert alert-info">Kolom kelas tidak ditemukan pada tabel students. Fitur rekap per kelas tidak tersedia. Untuk mengaktifkannya, tambahkan kolom 'kelas' atau 'class' pada tabel students.</div>
      <?php endif; ?>

      <!-- NOTE: tampilan tabel per-siswa dihilangkan sesuai permintaan -->
    </div>
  </div>
</div>

<script>
// Simple client-side toggling of period inputs
(function(){
  var periodSelect = document.getElementById('period');
  var inputDaily = document.getElementById('input-daily');
  var inputWeek = document.getElementById('input-week');
  var inputMonth = document.getElementById('input-month');

  function toggleInputs() {
    var val = periodSelect.value;
    inputDaily.style.display = val === 'daily' ? 'inline-block' : 'none';
    inputWeek.style.display = val === 'weekly' ? 'inline-block' : 'none';
    inputMonth.style.display = val === 'monthly' ? 'inline-block' : 'none';
  }

  if (periodSelect) {
    periodSelect.addEventListener('change', toggleInputs);
  }
})();
</script>
?>