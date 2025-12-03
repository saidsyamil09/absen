<?php
// dashboard.php
// UI + partial JSON endpoint. Manual attendance is handled by a separate endpoint: manual_attendance.php
// Make sure manual_attendance.php exists in the same folder and includes db.php to get $pdo.

// Toggle debug for more verbose error messages (false in production)
$debug = true;

if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        throw new Exception('PDO $pdo not found. Please provide db.php or set $pdo before including this file.');
    }
}

$today = date('Y-m-d');

/* Helper functions */
function compute_counts(PDO $pdo, $today) {
    $totalStmt = $pdo->query("SELECT COUNT(*) as cnt FROM students");
    $total = (int)$totalStmt->fetchColumn();

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

    $presentStmt = $pdo->prepare("
      SELECT COUNT(DISTINCT s.id)
      FROM attendance a
      JOIN students s ON a.student_id = s.id
      WHERE a.date = :d AND a.status IN ('Hadir','Terlambat','Izin')
    ");
    $presentStmt->execute([':d' => $today]);
    $present_count = (int)$presentStmt->fetchColumn();

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

function fetch_recent(PDO $pdo, $today, $limit = 10) {
    $limit = (int)$limit;
    if ($limit <= 0) $limit = 10;
    $sql = "SELECT a.*, s.name AS student_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id WHERE a.date = :d ORDER BY a.date DESC, a.time_in DESC LIMIT " . $limit;
    $recentStmt = $pdo->prepare($sql);
    $recentStmt->execute([':d' => $today]);
    return $recentStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Partial JSON endpoint for refresh (kept here) */
if (isset($_GET['partial'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $counts = compute_counts($pdo, $today);
        $recent = fetch_recent($pdo, $today, 10);

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
        if ($debug) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Server error']);
        }
    }
    exit;
}

/* Normal render */
$errorMessage = null;
try {
    $counts = compute_counts($pdo, $today);
    $recent = fetch_recent($pdo, $today, 10);
} catch (Exception $e) {
    $counts = ['total'=>0,'hadir'=>0,'telat'=>0,'izin'=>0,'alpha'=>0];
    $recent = [];
    $errorMessage = $debug ? $e->getMessage() : 'Terjadi kesalahan server. Silakan periksa log.';
    error_log('dashboard.php error: ' . ($e->getMessage()));
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Dashboard Absensi</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    .modal { position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.5); display: none; }
    .modal-dialog { max-width: 600px; margin: 40px auto; }
    .modal-content { background: #fff; border-radius: 4px; padding: 0; }
    .modal-header, .modal-footer { padding: 12px 16px; border-bottom: 1px solid #eee; }
    .modal-body { padding: 16px; }
    .close { background: none; border: none; font-size: 1.2rem; }
    #scanFeedback, #manualFeedback { margin-top: 8px; min-height: 24px; }
  </style>
</head>
<body>
<div class="container-fluid p-3">
  <div class="row mb-3">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5>Absensi Siswa Hari Ini</h5>
          <p><strong>Tanggal: </strong><?= htmlspecialchars(date('d/m/Y')) ?></p>

          <?php if ($errorMessage): ?>
            <div class="alert alert-danger">Error: <?= htmlspecialchars($errorMessage) ?></div>
          <?php endif; ?>

          <div class="row mb-2">
            <div class="col-md-3 mb-2">
              <button id="openScannerBtn" class="btn btn-outline-primary btn-block">
                <i class="fas fa-barcode"></i> Scan Barcode (USB)
              </button>
            </div>

            <div class="col-md-3 mb-2">
              <button id="openManualBtn" class="btn btn-outline-success btn-block">
                <i class="fas fa-pencil-alt"></i> Absen Manual
              </button>
            </div>

            <div class="col-md-6 text-right mb-2">
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
          <div id="scannerModal" class="modal">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Scanner Barcode (USB)</h5>
                  <button type="button" id="closeScanner" class="close">&times;</button>
                </div>
                <div class="modal-body">
                  <p>Hubungkan scanner USB Anda. Arahkan kursor ke input bawah, lalu scan NIS (barcode).</p>
                  <div class="form-group">
                    <label for="scanInput">Input Scanner</label>
                    <input type="text" id="scanInput" class="form-control" autocomplete="off" placeholder="Arahkan scanner lalu scan barcode">
                  </div>
                  <div id="scanFeedback"></div>
                </div>
                <div class="modal-footer">
                  <button type="button" id="closeScannerFooter" class="btn btn-secondary">Tutup</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Manual attendance modal -->
          <div id="manualModal" class="modal">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Absen Manual</h5>
                  <button type="button" id="closeManual" class="close">&times;</button>
                </div>
                <div class="modal-body">
                  <div id="manualFeedback"></div>
                  <div class="form-group">
                    <label for="manualNis">NIS</label>
                    <input type="text" id="manualNis" class="form-control" placeholder="Masukkan NIS siswa">
                  </div>
                  <div class="form-group">
                    <label for="manualStatus">Status</label>
                    <select id="manualStatus" class="form-control">
                      <option value="Hadir">Hadir</option>
                      <option value="Terlambat">Terlambat</option>
                      <option value="Izin">Izin</option>
                      <option value="Alpha">Alpha</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="manualNote">Catatan (opsional)</label>
                    <input type="text" id="manualNote" class="form-control" placeholder="Catatan singkat (opsional)">
                  </div>
                </div>
                <div class="modal-footer">
                  <button id="manualSubmit" class="btn btn-primary">Simpan</button>
                  <button id="closeManualFooter" class="btn btn-secondary">Batal</button>
                </div>
              </div>
            </div>
          </div>
          <!-- End manual modal -->

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
        <?php $no=1; foreach($recent as $r):
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

<!-- Dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
(function(){
  function showModal(id){ const el=document.getElementById(id); if(el) el.style.display='block'; }
  function hideModal(id){ const el=document.getElementById(id); if(el) el.style.display='none'; }
  function setFeedback(el, msg, type){
    if(!el) return;
    let color = (type==='success') ? 'green' : (type==='error') ? 'red' : 'black';
    el.innerHTML = '<div style="color:'+color+';">' + msg + '</div>';
  }

  const openScannerBtn = document.getElementById('openScannerBtn');
  const scanInput = document.getElementById('scanInput');
  const scanFeedback = document.getElementById('scanFeedback');
  openScannerBtn && openScannerBtn.addEventListener('click', function(){ showModal('scannerModal'); setTimeout(()=>{ if(scanInput){ scanInput.focus(); scanInput.select(); } },100); });
  ['closeScanner','closeScannerFooter'].forEach(id => { const el=document.getElementById(id); if(el) el.addEventListener('click', ()=> hideModal('scannerModal')); });

  const openManualBtn = document.getElementById('openManualBtn');
  const manualNis = document.getElementById('manualNis');
  const manualStatus = document.getElementById('manualStatus');
  const manualNote = document.getElementById('manualNote');
  const manualFeedback = document.getElementById('manualFeedback');
  openManualBtn && openManualBtn.addEventListener('click', function(){ showModal('manualModal'); setTimeout(()=>{ if (manualNis) manualNis.focus(); },100); });
  ['closeManual','closeManualFooter'].forEach(id => { const el=document.getElementById(id); if(el) el.addEventListener('click', ()=> { hideModal('manualModal'); setFeedback(manualFeedback,'',''); if(manualNis) manualNis.value=''; if(manualStatus) manualStatus.value='Hadir'; if(manualNote) manualNote.value=''; }); });

  // refreshPartial - get counts & recent HTML from this same file using ?partial=1
  function refreshPartial(){
    fetch(window.location.pathname + '?partial=1', { method: 'GET', credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
        if (!json || !json.success) return;
        const data = json.data || {};
        if (typeof data.hadir !== 'undefined' && typeof data.total !== 'undefined') {
          document.getElementById('hadirCount').textContent = data.hadir + ' / ' + data.total;
        }
        if (typeof data.telat !== 'undefined') document.getElementById('telatCount').textContent = data.telat;
        if (typeof data.izin !== 'undefined') document.getElementById('izinCount').textContent = data.izin;
        if (typeof data.alpha !== 'undefined') document.getElementById('alphaCount').textContent = data.alpha;
        if (typeof data.recent_html !== 'undefined') document.getElementById('recentTbody').innerHTML = data.recent_html;
      }).catch(err=>console.error('refreshPartial',err));
  }

  // MANUAL SUBMIT: send to manual_attendance.php (separate endpoint returning pure JSON)
  const manualSubmit = document.getElementById('manualSubmit');
  manualSubmit && manualSubmit.addEventListener('click', function(){
    const nis = manualNis.value.trim();
    const status = manualStatus.value;
    const note = manualNote.value.trim();
    if (!nis) { setFeedback(manualFeedback, 'NIS wajib diisi.', 'error'); manualNis.focus(); return; }
    setFeedback(manualFeedback, 'Menyimpan...', 'info');

    fetch('manual_attendance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ action: 'manual', nis: nis, status: status, note: note })
    }).then(response => {
      return response.text().then(text => {
        try { return JSON.parse(text); }
        catch (e) { return { success:false, server_text: text }; }
      });
    }).then(json => {
      if (json.success) {
        setFeedback(manualFeedback, json.message || 'Berhasil', 'success');
        refreshPartial();
        setTimeout(()=>{ hideModal('manualModal'); setFeedback(manualFeedback,'',''); manualNis.value=''; manualStatus.value='Hadir'; manualNote.value=''; }, 900);
      } else {
        if (json.server_text) {
          const snippet = json.server_text.length > 300 ? json.server_text.substring(0,300) + '...' : json.server_text;
          setFeedback(manualFeedback, 'Server response tidak valid: ' + snippet, 'error');
          console.error('manual endpoint non-JSON response:', json.server_text);
        } else {
          setFeedback(manualFeedback, json.message || 'Gagal menyimpan', 'error');
        }
      }
    }).catch(err=>{
      console.error('manual submit error', err);
      setFeedback(manualFeedback, 'Terjadi kesalahan jaringan atau server.', 'error');
    });
  });

  // scanner handling (ke record_attendance.php - unchanged)
  if (scanInput) {
    scanInput.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        const nis = scanInput.value.trim();
        if (!nis) { setFeedback(scanFeedback,'Tidak ada data ter-scan.','error'); scanInput.value=''; scanInput.focus(); return; }
        setFeedback(scanFeedback,'Memproses ...','info');
        fetch('record_attendance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ nis: nis })
        }).then(r => r.json()).then(data => {
          if (data && data.success) {
            setFeedback(scanFeedback,data.message || 'Berhasil tercatat.','success');
            refreshPartial();
            scanInput.value=''; scanInput.focus();
          } else {
            setFeedback(scanFeedback,(data && data.message) ? data.message : 'Gagal mencatat.','error');
            scanInput.value=''; scanInput.focus();
          }
        }).catch(err=>{
          console.error(err);
          setFeedback(scanFeedback,'Terjadi kesalahan server.','error');
          scanInput.value=''; scanInput.focus();
        });
      }
    });
  }

  // Export buttons
  document.getElementById('exportStudentsBtn') && document.getElementById('exportStudentsBtn').addEventListener('click', ()=> { window.location = 'export_csv.php?table=students'; });
  document.getElementById('exportAttendanceBtn') && document.getElementById('exportAttendanceBtn').addEventListener('click', ()=> { window.location = 'export_csv.php?table=attendance'; });

  // initial refresh
  window.addEventListener('load', function(){ setTimeout(refreshPartial, 200); });
})();
</script>
</body>
</html>