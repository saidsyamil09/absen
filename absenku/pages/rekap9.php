<?php
// Rekap per siswa + rekap harian per kelas (diperbarui: tombol Export CSV & link Detail per kelas)
// get search query from GET, trim whitespace
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// date for daily summary (default today)
$day = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

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

// build SQL and use prepared statement if searching, otherwise simple query (per-student overall rekap)
try {
    if ($q !== '') {
        $sql = "SELECT s.id, s.nis, s.name,
            SUM(a.status='Hadir') AS hadir,
            SUM(a.status='Terlambat') AS telat,
            SUM(a.status='Izin') AS izin,
            SUM(a.status='Sakit') AS sakit,
            SUM(a.status='Alpha') AS alpha,
            COUNT(a.id) AS total
          FROM students s
          LEFT JOIN attendance a ON a.student_id = s.id
          WHERE s.nis LIKE :q OR s.name LIKE :q
          GROUP BY s.id
          ORDER BY s.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q' => '%' . $q . '%']);
    } else {
        $stmt = $pdo->query("SELECT s.id, s.nis, s.name,
            SUM(a.status='Hadir') AS hadir,
            SUM(a.status='Terlambat') AS telat,
            SUM(a.status='Izin') AS izin,
            SUM(a.status='Sakit') AS sakit,
            SUM(a.status='Alpha') AS alpha,
            COUNT(a.id) AS total
          FROM students s
          LEFT JOIN attendance a ON a.student_id = s.id
          GROUP BY s.id
          ORDER BY s.name ASC");
    }
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    // fallback to empty results on error
    $rows = [];
}

// Prepare daily class summary if class column exists
$daily_summary = [];
if ($class_column) {
    try {
        // Build base SQL - attendance join limited to selected date
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
}
?>
<div class="container-fluid">
  <h4>Rekap Absensi</h4>

  <div class="card mb-3">
    <div class="card-body">
      <!-- Search form -->
      <form class="form-inline mb-3" method="get" action="">
        <!-- keep current page param so navigation stays on this page -->
        <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? 'rekap') ?>">
        <div class="form-group mr-2">
          <input type="text" name="q" class="form-control" placeholder="Cari NIS atau Nama..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <button type="submit" class="btn btn-primary mr-2">Cari</button>
        <a class="btn btn-secondary" href="?page=<?= urlencode($_GET['page'] ?? 'rekap') ?>">Reset</a>
      </form>

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
      </table>
      <?php else: ?>
        <div class="alert alert-info">Kolom kelas tidak ditemukan pada tabel students. Fitur rekap harian per kelas tidak tersedia. Untuk mengaktifkannya, tambahkan kolom 'kelas' atau 'class' pada tabel students.</div>
      <?php endif; ?>

      <!-- Existing per-student rekap table -->
      <h5>Rekap per Siswa</h5>
      <table class="table table-bordered">
        <thead>
          <tr><th>No</th><th>NIS</th><th>Nama</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpha</th><th>Total</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php $no=1; foreach($rows as $r): ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($r['nis']) ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><span class="badge badge-success"><?= $r['hadir'] ?: 0 ?></span></td>
            <td><span class="badge badge-warning"><?= $r['telat'] ?: 0 ?></span></td>
            <td><span class="badge badge-info"><?= $r['izin'] ?: 0 ?></span></td>
            <td><span class="badge badge-secondary"><?= $r['sakit'] ?: 0 ?></span></td>
            <td><span class="badge badge-danger"><?= $r['alpha'] ?: 0 ?></span></td>
            <td><?= $r['total'] ?: 0 ?></td>
            <!-- preserve search query when navigating to detail -->
            <td><a class="btn btn-sm btn-primary" href="?page=detail&id=<?= $r['id'] ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>