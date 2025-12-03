<?php
//require_once __DIR__ . '/db.php';
//dashboard1
// Summary card queries
$totalStmt = $pdo->query("SELECT COUNT(*) as cnt FROM students");
$total = $totalStmt->fetchColumn();

$hadirStmt = $pdo->query("SELECT COUNT(*) FROM attendance WHERE status='Hadir'");
$hadir = $hadirStmt->fetchColumn();

$telatStmt = $pdo->query("SELECT COUNT(*) FROM attendance WHERE status='Terlambat'");
$telat = $telatStmt->fetchColumn();

$alphaStmt = $pdo->query("SELECT COUNT(*) FROM attendance WHERE status='Alpha'");
$alpha = $alphaStmt->fetchColumn();

// recent attendance lines (hari ini)
$recent = $pdo->query("SELECT a.*, s.name AS student_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id ORDER BY a.date DESC, a.time_in DESC LIMIT 10")->fetchAll();
?>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5>Absensi Siswa Hari Ini</h5>
          <p><strong>Tanggal: </strong><?= date('d/m/Y') ?></p>
          <div class="row mb-2">
            <div class="col-md-4">
              <!-- Tombol untuk membuka modal scanner -->
              <button id="openScannerBtn" class="btn btn-outline-primary">
                <i class="fas fa-barcode"></i> Scan Barcode (USB)
              </button>
            </div>
            <div class="col-md-4">
              <!-- Tombol tambah siswa -->
              <button id="openAddBtn" class="btn btn-outline-success">
                <i class="fas fa-user-plus"></i> Tambah Siswa
              </button>
              <!-- Tombol hapus siswa -->
              <button id="openDeleteBtn" class="btn btn-outline-danger">
                <i class="fas fa-user-times"></i> Hapus Siswa
              </button>
            </div>
            <div class="col-md-4 text-right">
              <div class="btn-group" role="group" aria-label="export">
                <button id="exportStudentsBtn" class="btn btn-secondary"><i class="fas fa-download"></i> Export Students CSV</button>
                <button id="exportAttendanceBtn" class="btn btn-secondary"><i class="fas fa-download"></i> Export Attendance CSV</button>
                <button id="exportAllBtn" class="btn btn-secondary"><i class="fas fa-download"></i> Export All (ZIP)</button>
              </div>
              <label class="btn btn-secondary mb-0 ml-2" style="cursor:pointer;">
                <i class="fas fa-upload"></i> Import CSV
                <input type="file" id="importCsvFile" accept=".csv" style="display:none;">
              </label>
            </div>
          </div>

          <!-- Scanner modal (sama seperti sebelumnya) -->
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

          <!-- Add student modal -->
          <div id="addModal" class="modal" style="display:none;">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Tambah Siswa</h5>
                  <button type="button" id="closeAdd" class="close">&times;</button>
                </div>
                <div class="modal-body">
                  <div id="addFeedback"></div>
                  <div class="form-group">
                    <label for="addNis">NIS</label>
                    <input id="addNis" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label for="addName">Nama</label>
                    <input id="addName" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label for="addClass">Kelas (opsional)</label>
                    <input id="addClass" class="form-control" />
                  </div>
                </div>
                <div class="modal-footer">
                  <button id="addSubmit" class="btn btn-primary">Simpan</button>
                  <button id="closeAddFooter" class="btn btn-secondary">Tutup</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Delete student modal -->
          <div id="deleteModal" class="modal" style="display:none;">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Hapus Siswa</h5>
                  <button type="button" id="closeDelete" class="close">&times;</button>
                </div>
                <div class="modal-body">
                  <div id="deleteFeedback"></div>
                  <p>Masukkan NIS atau ID siswa yang akan dihapus.</p>
                  <div class="form-group">
                    <label for="deleteNis">NIS</label>
                    <input id="deleteNis" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label for="deleteId">Atau ID (opsional)</label>
                    <input id="deleteId" class="form-control" />
                  </div>
                  <div class="form-text text-danger">Perhatian: penghapusan bersifat permanen. Pastikan backup terlebih dahulu.</div>
                </div>
                <div class="modal-footer">
                  <button id="deleteSubmit" class="btn btn-danger">Hapus</button>
                  <button id="closeDeleteFooter" class="btn btn-secondary">Tutup</button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Cards dan tabel seperti sebelumnya -->
  <div class="row mb-3">
    <div class="col-md-3">
      <div class="card text-white bg-success">
        <div class="card-body">
          <h3 id="hadirCount"><?= (int)$hadir ?: 0 ?></h3>
          <p>Jumlah Siswa Hadir</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-dark bg-warning">
        <div class="card-body">
          <h3 id="telatCount"><?= (int)$telat ?: 0 ?></h3>
          <p>Jumlah Siswa Terlambat</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-info">
        <div class="card-body">
          <h3 id="izinCount">0</h3>
          <p>Jumlah Siswa Izin</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-danger">
        <div class="card-body">
          <h3 id="alphaCount"><?= (int)$alpha ?: 0 ?></h3>
          <p>Jumlah Siswa Alpha</p>
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
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($r['date']) ?></td>
            <td><?= htmlspecialchars($r['student_name']) ?></td>
            <td><?= htmlspecialchars($r['time_in'] ?: '-') ?></td>
            <td><span class="badge badge-<?= $r['status'] == 'Hadir' ? 'success' : ($r['status']=='Terlambat' ? 'warning' : ($r['status']=='Alpha' ? 'danger':'secondary')) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
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
.modal-dialog { max-width: 600px; margin: 60px auto; }
.modal-content { background: #fff; border-radius: 4px; padding: 0; }
.modal-header, .modal-footer { padding: 12px 16px; border-bottom: 1px solid #eee; }
.modal-body { padding: 16px; }
.close { background: none; border: none; font-size: 1.2rem; }
#scanFeedback, #addFeedback, #deleteFeedback { margin-top: 8px; }
</style>

<script>
(function(){
  // Common modal helpers
  function showModal(id){ document.getElementById(id).style.display = 'block'; }
  function hideModal(id){ document.getElementById(id).style.display = 'none'; }

  // Scanner modal
  const openBtn = document.getElementById('openScannerBtn');
  const scanInput = document.getElementById('scanInput');
  const feedback = document.getElementById('scanFeedback');
  openBtn && openBtn.addEventListener('click', function(){ showModal('scannerModal'); setTimeout(()=>{ scanInput && scanInput.focus(); scanInput && scanInput.select(); },100); });
  ['closeScanner','closeScannerFooter'].forEach(id => { const el=document.getElementById(id); if(el) el.addEventListener('click', ()=> hideModal('scannerModal')); });

  function showMessage(el, msg, type){
    let color = (type==='success') ? 'green' : (type==='error') ? 'red' : 'black';
    if (el) el.innerHTML = '<div style="color:'+color+';">' + msg + '</div>';
  }

  // Partial refresh
  function refreshPartial(){
    fetch('dashboard_partial.php', { method: 'GET', credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
        if (!json || !json.success) return;
        if (typeof json.data.hadir !== 'undefined') document.getElementById('hadirCount').textContent = json.data.hadir;
        if (typeof json.data.telat !== 'undefined') document.getElementById('telatCount').textContent = json.data.telat;
        if (typeof json.data.alpha !== 'undefined') document.getElementById('alphaCount').textContent = json.data.alpha;
        if (typeof json.data.izin !== 'undefined') document.getElementById('izinCount').textContent = json.data.izin;
        if (typeof json.data.recent_html !== 'undefined') document.getElementById('recentTbody').innerHTML = json.data.recent_html;
      }).catch(err=>console.error('refreshPartial',err));
  }

  // Handle scanner Enter
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

  // Add student modal behavior
  document.getElementById('openAddBtn') && document.getElementById('openAddBtn').addEventListener('click', ()=> showModal('addModal'));
  ['closeAdd','closeAddFooter'].forEach(id=>{ const el=document.getElementById(id); if(el) el.addEventListener('click', ()=> hideModal('addModal')); });

  document.getElementById('addSubmit') && document.getElementById('addSubmit').addEventListener('click', function(){
    const nis = document.getElementById('addNis').value.trim();
    const name = document.getElementById('addName').value.trim();
    const cls = document.getElementById('addClass').value.trim();
    const fb = document.getElementById('addFeedback');
    if (!nis || !name) { showMessage(fb,'NIS dan Nama wajib diisi.','error'); return; }
    showMessage(fb,'Menyimpan...','info');

    fetch('add_student.php', {
      method:'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nis: nis, name: name, class: cls })
    }).then(r=>r.json()).then(j=>{
      if (j && j.success) {
        showMessage(fb,j.message || 'Berhasil','success');
        document.getElementById('addNis').value=''; document.getElementById('addName').value=''; document.getElementById('addClass').value='';
        refreshPartial();
      } else {
        showMessage(fb,(j && j.message) ? j.message : 'Gagal menambah','error');
      }
    }).catch(err=>{ console.error(err); showMessage(fb,'Terjadi kesalahan server','error'); });
  });

  // Delete student modal behavior
  document.getElementById('openDeleteBtn') && document.getElementById('openDeleteBtn').addEventListener('click', ()=> showModal('deleteModal'));
  ['closeDelete','closeDeleteFooter'].forEach(id=>{ const el=document.getElementById(id); if(el) el.addEventListener('click', ()=> hideModal('deleteModal')); });

  document.getElementById('deleteSubmit') && document.getElementById('deleteSubmit').addEventListener('click', function(){
    const nis = document.getElementById('deleteNis').value.trim();
    const id = document.getElementById('deleteId').value.trim();
    const fb = document.getElementById('deleteFeedback');
    if (!nis && !id) { showMessage(fb,'Masukkan NIS atau ID.','error'); return; }
    if (!confirm('Yakin ingin menghapus siswa ini? Tindakan ini permanen.')) return;
    showMessage(fb,'Menghapus...','info');

    fetch('delete_student.php', {
      method:'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nis: nis || null, id: id || null })
    }).then(r=>r.json()).then(j=>{
      if (j && j.success) {
        showMessage(fb,j.message || 'Terhapus','success');
        document.getElementById('deleteNis').value=''; document.getElementById('deleteId').value='';
        refreshPartial();
      } else {
        showMessage(fb,(j && j.message) ? j.message : 'Gagal hapus','error');
      }
    }).catch(err=>{ console.error(err); showMessage(fb,'Terjadi kesalahan server','error'); });
  });

  // Export buttons
  document.getElementById('exportStudentsBtn') && document.getElementById('exportStudentsBtn').addEventListener('click', ()=> {
    window.location = 'export_csv.php?table=students';
  });
  document.getElementById('exportAttendanceBtn') && document.getElementById('exportAttendanceBtn').addEventListener('click', ()=> {
    window.location = 'export_csv.php?table=attendance';
  });
  document.getElementById('exportAllBtn') && document.getElementById('exportAllBtn').addEventListener('click', ()=> {
    window.location = 'export_csv.php?table=all';
  });

  // Import CSV
  const importCsv = document.getElementById('importCsvFile');
  if (importCsv) {
    importCsv.addEventListener('change', function(){
      if (!this.files || !this.files[0]) return;
      // ask which table -> simple prompt (you can replace with nicer UI)
      let table = prompt('Masukkan nama tabel tujuan (contoh: students atau attendance):', 'students');
      if (!table) { this.value=''; return; }
      if (!confirm('Import akan memasukkan data ke tabel "'+table+'". Pastikan backup. Lanjutkan?')) { this.value=''; return; }

      const form = new FormData();
      form.append('table', table);
      form.append('csvfile', this.files[0]);

      fetch('import_csv.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(j => {
          if (j && j.success) {
            alert('Import berhasil. Inserted=' + j.inserted + ', Skipped=' + j.skipped);
            refreshPartial();
          } else {
            alert('Import gagal: ' + (j && j.message ? j.message : 'Unknown'));
          }
        }).catch(err => {
          console.error(err);
          alert('Terjadi kesalahan saat import.');
        }).finally(()=>{ this.value=''; });
    });
  }

  // initial partial refresh
  window.addEventListener('load', function(){ setTimeout(refreshPartial, 200); });
})();
</script>