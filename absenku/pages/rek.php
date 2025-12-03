<?php
// Rekap per siswa: count statuses grouped by student
$stmt = $pdo->query("SELECT s.id, s.nis, s.name,
 SUM(status='Hadir') AS hadir,
 SUM(status='Terlambat') AS telat,
 SUM(status='Izin') AS izin,
 SUM(status='Sakit') AS sakit,
 SUM(status='Alpha') AS alpha,
 COUNT(a.id) AS total
 FROM students s
 LEFT JOIN attendance a ON a.student_id = s.id
 GROUP BY s.id ORDER BY s.name ASC");
$rows = $stmt->fetchAll();
?>
<div class="container-fluid">
  <h4>Rekap Absensi</h4>
  <div class="card mb-3">
    <div class="card-body">
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
            <td><a class="btn btn-sm btn-primary" href="?page=detail&id=<?= $r['id'] ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>