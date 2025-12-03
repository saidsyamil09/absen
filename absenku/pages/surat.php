<?php
// Daftar surat panggilan sample
$stmt = $pdo->query("SELECT l.*, s.name AS student_name FROM letters l LEFT JOIN students s ON s.id = l.student_id ORDER BY l.date DESC");
$letters = $stmt->fetchAll();
?>
<div class="container-fluid">
  <h4>Surat Panggilan</h4>
  <div class="card">
    <div class="card-body">
      <!-- Filter sederhana -->
      <form class="form-inline mb-3" method="get">
        <input type="hidden" name="page" value="surat">
        <select name="jenis" class="form-control mr-2">
          <option value="">Semua Jenis</option>
          <option value="SP1">SP1</option>
          <option value="SP2">SP2</option>
        </select>
        <button class="btn btn-primary">Filter</button>
      </form>

      <table class="table table-striped">
        <thead><tr><th>No</th><th>Nomor Surat</th><th>Tanggal</th><th>Jenis</th><th>Siswa</th><th>Kelas</th><th>Jumlah Alpha</th><th>Status</th></tr></thead>
        <tbody>
        <?php $no=1; foreach($letters as $l): ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($l['number']) ?></td>
            <td><?= $l['date'] ?></td>
            <td><span class="badge badge-warning"><?= htmlspecialchars($l['type']) ?></span></td>
            <td><?= htmlspecialchars($l['student_name']) ?></td>
            <td><?= htmlspecialchars($l['class']) ?></td>
            <td><span class="badge badge-danger"><?= $l['alpha_count'] ?></span></td>
            <td><span class="badge badge-success"><?= htmlspecialchars($l['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

    </div>
  </div>
</div>