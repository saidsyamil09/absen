<?php
//require_once __DIR__ . '/db.php';
//dashboard3

// gunakan tanggal hari ini untuk statistik "hari ini"
$today = date('Y-m-d');

// Summary card queries
$totalStmt = $pdo->query("SELECT COUNT(*) as cnt FROM students");
$total = (int)$totalStmt->fetchColumn();

// Hitung jumlah siswa per status untuk hari ini (distinct student)
$hadirStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE date = :d AND status = 'Hadir'");
$hadirStmt->execute([':d' => $today]);
$hadir = (int)$hadirStmt->fetchColumn();

$telatStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE date = :d AND status = 'Terlambat'");
$telatStmt->execute([':d' => $today]);
$telat = (int)$telatStmt->fetchColumn();

$izinStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE date = :d AND status = 'Izin'");
$izinStmt->execute([':d' => $today]);
$izin = (int)$izinStmt->fetchColumn();

// Alpha dihitung berdasarkan permintaan: total - telat - izin
$alpha = $total - $telat - $izin;
if ($alpha < 0) $alpha = 0;

// recent attendance lines (hari ini)
$recentStmt = $pdo->prepare("SELECT a.*, s.name AS student_name FROM attendance a LEFT JOIN students s ON a.student_id = s.id WHERE a.date = :d ORDER BY a.date DESC, a.time_in DESC LIMIT 10");
$recentStmt->execute([':d' => $today]);
$recent = $recentStmt->fetchAll();
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
            </div>
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

          <!-- Import CSV Modal -->
          <div id="importModal" class="modal" style="display:none;">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Import CSV — Preview & Mapping</h5>
                  <button type="button" id="closeImport" class="close">&times;</button>
                </div>
                <div class="modal-body">
                  <div id="importFeedback" style="margin-bottom:8px;"></div>

                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="importTableSelect">Pilih Tabel Tujuan</label>
                      <select id="importTableSelect" class="form-control"></select>
                    </div>
                    <div class="form-group col-md-6">
                      <label>File</label>
                      <input type="text" id="importFileName" class="form-control" readonly>
                    </div>
                  </div>

                  <hr>

                  <div>
                    <h6>Header CSV & Mapping</h6>
                    <div id="mappingContainer" style="max-height:200px; overflow:auto; border:1px solid #eee; padding:8px;"></div>
                  </div>

                  <hr>

                  <div>
                    <h6>Preview 10 baris pertama</h6>
                    <div style="overflow:auto">
                      <table class="table table-sm table-bordered" id="previewTable">
                      </table>
                    </div>
                  </div>

                </div>
                <div class="modal-footer">
                  <button id="doImportBtn" class="btn btn-primary">Import ke Database</button>
                  <button id="closeImportFooter" class="btn btn-secondary">Batal</button>
                </div>
              </div>
            </div>
          </div>
          <!-- End import modal -->

        </div>
      </div>
    </div>
  </div>

  <!-- Cards dan tabel seperti sebelumnya -->
  <div class="row mb-3">
    <div class="col-md-3">
      <div class="card text-white bg-success">
        <div class="card-body">
          <!-- tampilkan hadir / total -->
          <h3 id="hadirCount"><?= (int)$hadir ?> / <?= (int)$total ?></h3>
          <p>Jumlah Siswa Hadir / Total</p>
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
          <h3 id="izinCount"><?= (int)$izin ?: 0 ?></h3>
          <p>Jumlah Siswa Izin</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-danger">
        <div class="card-body">
          <h3 id="alphaCount"><?= (int)$alpha ?: 0 ?></h3>
          <p>Jumlah Siswa Alpha (total - telat - izin)</p>
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
.modal-dialog { max-width: 900px; margin: 40px auto; }
.modal-content { background: #fff; border-radius: 4px; padding: 0; }
.modal-header, .modal-footer { padding: 12px 16px; border-bottom: 1px solid #eee; }
.modal-body { padding: 16px; }
.close { background: none; border: none; font-size: 1.2rem; }
#scanFeedback, #addFeedback, #deleteFeedback { margin-top: 8px; }
</style>

<script>
(function(){
  // helper modal
  function showModal(id){ document.getElementById(id).style.display = 'block'; }
  function hideModal(id){ document.getElementById(id).style.display = 'none'; }

  // small message helper
  function setFeedback(el, msg, type){
    let color = (type==='success') ? 'green' : (type==='error') ? 'red' : 'black';
    if (el) el.innerHTML = '<div style="color:'+color+';">' + msg + '</div>';
  }

  // Existing scanner/add/delete code (unchanged aside from modal helpers)
  const openBtn = document.getElementById('openScannerBtn');
  const scanInput = document.getElementById('scanInput');
  const feedback = document.getElementById('scanFeedback');
  openBtn && openBtn.addEventListener('click', function(){ showModal('scannerModal'); setTimeout(()=>{ scanInput && scanInput.focus(); scanInput && scanInput.select(); },100); });
  ['closeScanner','closeScannerFooter'].forEach(id => { const el=document.getElementById(id); if(el) el.addEventListener('click', ()=> hideModal('scannerModal')); });

  function showMessage(el, msg, type){ setFeedback(el,msg,type); }

  function refreshPartial(){
    fetch('dashboard_partial.php', { method: 'GET', credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
        if (!json || !json.success) return;
        // jika backend mengirim total juga, gunakan total; jika tidak, biarkan tampil awal
        if (typeof json.data.hadir !== 'undefined') {
          const total = (typeof json.data.total !== 'undefined') ? json.data.total : null;
          document.getElementById('hadirCount').textContent = (total !== null) ? (json.data.hadir + ' / ' + total) : json.data.hadir;
        }
        if (typeof json.data.telat !== 'undefined') document.getElementById('telatCount').textContent = json.data.telat;
        if (typeof json.data.izin !== 'undefined') document.getElementById('izinCount').textContent = json.data.izin;
        if (typeof json.data.alpha !== 'undefined') document.getElementById('alphaCount').textContent = json.data.alpha;
        if (typeof json.data.recent_html !== 'undefined') document.getElementById('recentTbody').innerHTML = json.data.recent_html;
      }).catch(err=>console.error('refreshPartial',err));
  }

  // scanner enter handling (same)
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

  // Add / Delete student handlers (unchanged)
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

  // ------------------------
  // Import CSV UI & logic
  // ------------------------
  const importInput = document.getElementById('importCsvFile');
  const importModal = document.getElementById('importModal');
  const importTableSelect = document.getElementById('importTableSelect');
  const importFileName = document.getElementById('importFileName');
  const mappingContainer = document.getElementById('mappingContainer');
  const previewTable = document.getElementById('previewTable');
  const importFeedback = document.getElementById('importFeedback');

  // Utility: parse CSV text into array of rows (basic CSV parser handling quotes)
  function parseCSV(text, delimiter=',') {
    const rows = [];
    let cur = '';
    let field = '';
    let row = [];
    let inQuotes = false;
    for (let i = 0; i < text.length; i++) {
      const ch = text[i];
      const nextCh = text[i+1];
      if (inQuotes) {
        if (ch === '"' && nextCh === '"') {
          field += '"';
          i++; // skip escaped quote
        } else if (ch === '"') {
          inQuotes = false;
        } else {
          field += ch;
        }
      } else {
        if (ch === '"') {
          inQuotes = true;
        } else if (ch === delimiter) {
          row.push(field);
          field = '';
        } else if (ch === '\r') {
          // ignore, wait for \n
        } else if (ch === '\n') {
          row.push(field);
          rows.push(row);
          row = [];
          field = '';
        } else {
          field += ch;
        }
      }
    }
    // last field/row
    if (field !== '' || inQuotes || row.length) {
      row.push(field);
      rows.push(row);
    }
    return rows;
  }

  // fetch table list and populate dropdown
  function loadTableList() {
    fetch('get_tables.php').then(r=>r.json()).then(j=>{
      if (!j || !j.success) return;
      importTableSelect.innerHTML = '';
      j.tables.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t;
        opt.textContent = t;
        importTableSelect.appendChild(opt);
      });
    }).catch(err => {
      console.error('get_tables error', err);
    });
  }

  // open import modal when file selected
  importInput && importInput.addEventListener('change', function(){
    if (!this.files || !this.files[0]) return;
    const f = this.files[0];
    importFileName.value = f.name;
    // load tables
    loadTableList();
    // read file contents
    const reader = new FileReader();
    reader.onload = function(e){
      const text = e.target.result;
      const rows = parseCSV(text);
      if (!rows || rows.length === 0) {
        setFeedback(importFeedback, 'Tidak berhasil membaca CSV atau file kosong.', 'error');
        return;
      }
      // use first row as header
      const header = rows[0].map(h => (h === null ? '' : String(h).trim()));
      const previewRows = rows.slice(1, 11); // up to 10 data rows

      // fetch columns for selected table to build mapping dropdowns
      fetch('get_table_columns.php?table=' + encodeURIComponent(importTableSelect.value))
        .then(r => r.json())
        .then(j => {
          const cols = (j && j.success) ? j.columns : [];
          // build mapping UI
          mappingContainer.innerHTML = '';
          header.forEach((h, idx) => {
            const rowDiv = document.createElement('div');
            rowDiv.style.display = 'flex';
            rowDiv.style.alignItems = 'center';
            rowDiv.style.marginBottom = '6px';

            const lbl = document.createElement('div');
            lbl.style.width = '40%';
            lbl.textContent = '[' + idx + '] ' + h;
            rowDiv.appendChild(lbl);

            const sel = document.createElement('select');
            sel.style.flex = '1';
            const optIgnore = document.createElement('option');
            optIgnore.value = '';
            optIgnore.textContent = '-- ignore --';
            sel.appendChild(optIgnore);

            cols.forEach(c => {
              const o = document.createElement('option');
              o.value = c;
              o.textContent = c;
              // try to auto-select if header matches column name (case-insensitive)
              if (String(h).toLowerCase() === String(c).toLowerCase()) o.selected = true;
              // also try normalized match
              const norm = String(h).toLowerCase().replace(/[^a-z0-9_]+/g, '_');
              if (norm === String(c).toLowerCase()) o.selected = true;
              sel.appendChild(o);
            });

            rowDiv.appendChild(sel);
            mappingContainer.appendChild(rowDiv);
          });

          // build preview table
          previewTable.innerHTML = '';
          const thead = document.createElement('thead');
          const headRow = document.createElement('tr');
          header.forEach(h => {
            const th = document.createElement('th');
            th.textContent = h;
            headRow.appendChild(th);
          });
          thead.appendChild(headRow);
          previewTable.appendChild(thead);

          const tbody = document.createElement('tbody');
          previewRows.forEach(r => {
            const tr = document.createElement('tr');
            header.forEach((_, i) => {
              const td = document.createElement('td');
              td.textContent = (typeof r[i] !== 'undefined') ? r[i] : '';
              tr.appendChild(td);
            });
            tbody.appendChild(tr);
          });
          previewTable.appendChild(tbody);

          setFeedback(importFeedback, 'Preview siap. Sesuaikan mapping lalu klik "Import ke Database".', 'success');
          showModal('importModal');
        }).catch(err => {
          console.error('get_table_columns error', err);
          setFeedback(importFeedback, 'Gagal memuat kolom tabel.', 'error');
        });
    };
    reader.readAsText(f, 'UTF-8');
  });

  // when table select changes, reload mapping options (if file loaded)
  importTableSelect && importTableSelect.addEventListener('change', function(){
    // trigger re-select options: simply simulate file input change to refresh mapping UI if a file already loaded
    if (importInput && importInput.files && importInput.files[0]) {
      // re-trigger by creating a new event: call change handler manually
      const ev = new Event('change');
      importInput.dispatchEvent(ev);
    }
  });

  // close import modal
  ['closeImport','closeImportFooter'].forEach(id => { const el = document.getElementById(id); if (el) el.addEventListener('click', ()=>{ hideModal('importModal'); importInput.value=''; importFileName.value=''; mappingContainer.innerHTML=''; previewTable.innerHTML=''; setFeedback(importFeedback,'',''); }); });

  // perform actual import when user confirms
  document.getElementById('doImportBtn').addEventListener('click', function(){
    if (!importInput || !importInput.files || !importInput.files[0]) {
      alert('Tidak ada file yang dipilih.');
      return;
    }
    const table = importTableSelect.value;
    if (!table) {
      alert('Pilih tabel tujuan terlebih dahulu.');
      return;
    }
    if (!confirm('Import akan memasukkan data ke tabel "' + table + '". Pastikan backup terlebih dahulu. Lanjutkan?')) return;

    // build mapping from mappingContainer selects
    const selects = mappingContainer.querySelectorAll('select');
    const mapping = {};
    selects.forEach((sel, idx) => {
      if (sel.value && sel.value !== '') mapping[idx] = sel.value;
    });

    const form = new FormData();
    form.append('table', table);
    form.append('csvfile', importInput.files[0]);
    form.append('mapping', JSON.stringify(mapping));

    setFeedback(importFeedback, 'Mengupload dan memproses import...', 'info');

    fetch('import_csv.php', { method: 'POST', body: form })
      .then(r => r.json())
      .then(j => {
        if (j && j.success) {
          setFeedback(importFeedback, 'Import selesai. Inserted=' + j.inserted + ', Skipped=' + j.skipped, 'success');
          // close modal after short delay
          setTimeout(()=>{ hideModal('importModal'); importInput.value=''; importFileName.value=''; mappingContainer.innerHTML=''; previewTable.innerHTML=''; setFeedback(importFeedback,'',''); refreshPartial(); }, 1200);
        } else {
          setFeedback(importFeedback, 'Import gagal: ' + (j && j.message ? j.message : 'Unknown'), 'error');
        }
      }).catch(err => {
        console.error('import_csv error', err);
        setFeedback(importFeedback, 'Terjadi kesalahan saat import.', 'error');
      });
  });

  // initial partial refresh
  window.addEventListener('load', function(){ setTimeout(refreshPartial, 200); });
})();
</script>