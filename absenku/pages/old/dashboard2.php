<?php
// dashboard.php
// Single-file dashboard with stable Alpha calculation and inline AJAX "partial" endpoint.
// Usage: place in your app where $pdo (PDO connection) is available or use the included db.php below.

// Uncomment or adjust the following line if you need to load the DB connection here:
// require_once __DIR__ . '/db.php';

// If this file is included from another script that already defines $pdo, the require is optional.
// Ensure $pdo is a PDO instance connected to your database.

if (!isset($pdo)) {
    // Try to require db.php if not provided by the including script.
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        // If there's still no $pdo, throw a helpful error for debugging.
        // In production, you might want to handle this more gracefully.
        throw new Exception('PDO $pdo not found. Please configure DB connection or include db.php.');
    }
}

// Use today's date (Y-m-d) for "hari ini" statistics
$today = date('Y-m-d');

// Function to compute summary counts (so we can reuse for both full page and partial JSON)
function compute_counts(PDO $pdo, $today) {
    // total students
    $totalStmt = $pdo->query("SELECT COUNT(*) as cnt FROM students");
    $total = (int)$totalStmt->fetchColumn();

    // Count only valid students by joining attendance -> students to avoid invalid student_id entries
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

    // present_count = number of distinct valid students who have Hadir/Terlambat/Izin today
    $presentStmt = $pdo->prepare("
      SELECT COUNT(DISTINCT s.id)
      FROM attendance a
      JOIN students s ON a.student_id = s.id
      WHERE a.date = :d AND a.status IN ('Hadir','Terlambat','Izin')
    ");
    $presentStmt->execute([':d' => $today]);
    $present_count = (int)$presentStmt->fetchColumn();

    // Alpha defined as: total students in DB - number of students who are recorded (hadir/telat/izin) today.
    $alpha = $total - $present_count;
    if ($alpha < 0) $alpha = 0;

    return [
        'total' => $total,
        'hadir' => $hadir,
        'telat' => $telat,
        'izin' => $izin,
        'present_count' => $present_count,
        'alpha' => $alpha,
    ];
}

// Function to fetch recent attendance rows (for today)
function fetch_recent(PDO $pdo, $today, $limit = 10) {
    $recentStmt = $pdo->prepare("SELECT a.*, s.name AS student_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id WHERE a.date = :d ORDER BY a.date DESC, a.time_in DESC LIMIT :lim");
    // PDO doesn't accept LIMIT as bound param in some drivers, ensure integer binding via PDO::PARAM_INT
    $recentStmt->bindValue(':d', $today);
    $recentStmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $recentStmt->execute();
    return $recentStmt->fetchAll(PDO::FETCH_ASSOC);
}

// If called as partial (AJAX refresh), return JSON with counts and HTML for recent rows
if (isset($_GET['partial'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $counts = compute_counts($pdo, $today);
        $recent = fetch_recent($pdo, $today, 10);

        // build recent_html
        $recent_html = '';
        $no = 1;
        foreach ($recent as $r) {
            $status = htmlspecialchars($r['status'] ?? '');
            $badgeClass = 'secondary';
            if ($status === 'Hadir') $badgeClass = 'success';
            elseif ($status === 'Terlambat') $badgeClass = 'warning';
            elseif ($status === 'Alpha') $badgeClass = 'danger';
            $recent_html .= '<tr>';
            $recent_html .= '<td>' . $no++ . '</td>';
            $recent_html .= '<td>' . htmlspecialchars($r['date'] ?? '') . '</td>';
            $recent_html .= '<td>' . htmlspecialchars($r['student_name'] ?? '') . '</td>';
            $recent_html .= '<td>' . htmlspecialchars($r['time_in'] ?? '-') . '</td>';
            $recent_html .= '<td><span class="badge badge-' . $badgeClass . '">' . $status . '</span></td>';
            $recent_html .= '</tr>';
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'total' => $counts['total'],
                'hadir' => $counts['hadir'],
                'telat' => $counts['telat'],
                'izin' => $counts['izin'],
                'alpha' => $counts['alpha'],
                'recent_html' => $recent_html,
            ],
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Normal page render path:
$counts = compute_counts($pdo, $today);
$recent = fetch_recent($pdo, $today, 10);
?>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5>Absensi Siswa Hari Ini</h5>
          <p><strong>Tanggal: </strong><?= htmlspecialchars(date('d/m/Y')) ?></p>
          <div class="row mb-2">
            <div class="col-md-4">
              <button id="openScannerBtn" class="btn btn-outline-primary">
                <i class="fas fa-barcode"></i> Scan Barcode (USB)
              </button>
            </div>
            <div class="col-md-4"></div>
            <div class="col-md-4 text-right">
              <div class="btn-group" role="group" aria-label="export">
                <button id="exportStudentsBtn" class="btn btn-secondary"><i class="fas fa-download"></i> Export Students CSV</button>
                <button id="exportAttendanceBtn" class="btn btn-secondary"><i class="fas fa-download"></i> Export Attendance CSV</button>
              </div>
              <label class="btn btn-secondary mb-0 ml-2" style="cursor:pointer;">
                <i class="fas fa-upload"></i> Import CSV
                <input type="file" id="importCsvFile" accept=".csv" style="display:none;">
              </label>
            </div>
          </div>

          <!-- Scanner modal -->
          <div id="scannerModal" class="modal" style="display:none;">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Scanner Barcode (USB)</h5>
                  <button type="button" id="closeScanner" class="close">&times;</button>
                </div>
                <div class="modal-body">
                  <p>Hubungkan scanner USB Anda. Arahkan kursor ke input bawah, lalu scan NIS (barcode). Scanner USB berperilaku seperti keyboard — biasanya mengirimkan karakter diikuti Enter.</p>
                  <div class="form-group">
                    <label for="scanInput">Input Scanner (otomatis terisi saat scan)</label>
                    <input type="text" id="scanInput" class="form-control" autocomplete="off" autofocus placeholder="Arahkan scanner lalu scan barcode">
                  </div>
                  <div id="scanFeedback" style="min-height:40px;"></div>
                  <hr>
                  <p>Jika ingin, Anda dapat juga memasukkan NIS secara manual lalu tekan Enter.</p>
                </div>
                <div class="modal-footer">
                  <button type="button" id="closeScannerFooter" class="btn btn-secondary">Tutup</button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Cards -->
  <div class="row mb-3">
    <div class="col-md-3">
      <div class="card text-white bg-success">
        <div class="card-body">
          <h3 id="hadirCount"><?= (int)$counts['hadir'] ?> / <?= (int)$counts['total'] ?></h3>
          <p>Jumlah Siswa Hadir / Total</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-dark bg-warning">
        <div class="card-body">
          <h3 id="telatCount"><?= (int)$counts['telat'] ?></h3>
          <p>Jumlah Siswa Terlambat</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-info">
        <div class="card-body">
          <h3 id="izinCount"><?= (int)$counts['izin'] ?></h3>
          <p>Jumlah Siswa Izin</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-danger">
        <div class="card-body">
          <h3 id="alphaCount"><?= (int)$counts['alpha'] ?></h3>
          <p>Jumlah Siswa Alpha (total - hadir - telat - izin)</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent attendance table -->
  <div class="card">
    <div class="card-body">
      <h5>Riwayat Absensi Hari ini</h5>
      <table class="table table-striped">
        <thead>
          <tr><th>No</th><th>Tanggal</th><th>Nama</th><th>Jam Masuk</th><th>Status</th></tr>
        </thead>
        <tbody id="recentTbody">
        <?php $no=1; foreach($recent as $r): ?>
          <?php
            $status = htmlspecialchars($r['status'] ?? '');
            $badgeClass = 'secondary';
            if ($status === 'Hadir') $badgeClass = 'success';
            elseif ($status === 'Terlambat') $badgeClass = 'warning';
            elseif ($status === 'Alpha') $badgeClass = 'danger';
          ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($r['date'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['student_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['time_in'] ?? '-') ?></td>
            <td><span class="badge badge-<?= $badgeClass ?>"><?= $status ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
/* modal basic */
.modal { position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.5); }
.modal-dialog { max-width: 900px; margin: 40px auto; }
.modal-content { background: #fff; border-radius: 4px; padding: 0; }
.modal-header, .modal-footer { padding: 12px 16px; border-bottom: 1px solid #eee; }
.modal-body { padding: 16px; }
.close { background: none; border: none; font-size: 1.2rem; }
#scanFeedback { margin-top: 8px; }
</style>

<script>
(function(){
  // helper modal
  function showModal(id){ document.getElementById(id).style.display = 'block'; }
  function hideModal(id){ document.getElementById(id).style.display = 'none'; }

  function setFeedback(el, msg, type){
    let color = (type==='success') ? 'green' : (type==='error') ? 'red' : 'black';
    if (el) el.innerHTML = '<div style="color:'+color+';">' + msg + '</div>';
  }

  // Scanner modal handlers
  const openBtn = document.getElementById('openScannerBtn');
  const scanInput = document.getElementById('scanInput');
  const feedback = document.getElementById('scanFeedback');
  openBtn && openBtn.addEventListener('click', function(){ showModal('scannerModal'); setTimeout(()=>{ scanInput && scanInput.focus(); scanInput && scanInput.select(); },100); });
  ['closeScanner','closeScannerFooter'].forEach(id => { const el=document.getElementById(id); if(el) el.addEventListener('click', ()=> hideModal('scannerModal')); });

  function showMessage(el, msg, type){ setFeedback(el,msg,type); }

  // refreshPartial will call this same file with ?partial=1 so everything stays consistent in one file
  function refreshPartial(){
    fetch(window.location.pathname + '?partial=1', { method: 'GET', credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
        if (!json || !json.success) return;
        const data = json.data || {};
        if (typeof data.hadir !== 'undefined' && typeof data.total !== 'undefined') {
          document.getElementById('hadirCount').textContent = data.hadir + ' / ' + data.total;
        } else if (typeof data.hadir !== 'undefined') {
          document.getElementById('hadirCount').textContent = data.hadir;
        }
        if (typeof data.telat !== 'undefined') document.getElementById('telatCount').textContent = data.telat;
        if (typeof data.izin !== 'undefined') document.getElementById('izinCount').textContent = data.izin;
        if (typeof data.alpha !== 'undefined') document.getElementById('alphaCount').textContent = data.alpha;
        if (typeof data.recent_html !== 'undefined') document.getElementById('recentTbody').innerHTML = data.recent_html;
      }).catch(err=>console.error('refreshPartial',err));
  }

  // scanner enter handling
  if (scanInput) {
    scanInput.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        const nis = scanInput.value.trim();
        if (!nis) { showMessage(feedback,'Tidak ada data ter-scan.','error'); scanInput.value=''; scanInput.focus(); return; }
        showMessage(feedback,'Memproses ...','info');
        fetch('record_attendance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ nis: nis })
        }).then(r => r.json()).then(data => {
          if (data && data.success) {
            showMessage(feedback,data.message || 'Berhasil tercatat.','success');
            refreshPartial();
            scanInput.value=''; scanInput.focus();
          } else {
            showMessage(feedback,(data && data.message) ? data.message : 'Gagal mencatat.','error');
            scanInput.value=''; scanInput.focus();
          }
        }).catch(err=>{
          console.error(err);
          showMessage(feedback,'Terjadi kesalahan server.','error');
          scanInput.value=''; scanInput.focus();
        });
      }
    });
  }

  // Export buttons
  document.getElementById('exportStudentsBtn') && document.getElementById('exportStudentsBtn').addEventListener('click', ()=> {
    window.location = 'export_csv.php?table=students';
  });
  document.getElementById('exportAttendanceBtn') && document.getElementById('exportAttendanceBtn').addEventListener('click', ()=> {
    window.location = 'export_csv.php?table=attendance';
  });

  // Import CSV flow and other JS from previous implementation can be copied here if needed.
  // For now only keep refreshPartial integration so counters and recent list update after actions.

  // initial partial refresh to ensure numbers are current shortly after load
  window.addEventListener('load', function(){ setTimeout(refreshPartial, 200); });
})();
</script>