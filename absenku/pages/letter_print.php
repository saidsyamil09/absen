<?php
// Halaman untuk melihat & mencetak surat panggilan
// Gunakan data surat dari tabel letters
$stmt = $pdo->query("SELECT l.*, s.name AS student_name, s.nis FROM letters l LEFT JOIN students s ON s.id = l.student_id ORDER BY l.date DESC");
$letters = $stmt->fetchAll();
?>
<div class="container-fluid">
  <h4>Surat Panggilan</h4>
  <div class="card mb-3">
    <div class="card-body">
      <p>Pilih surat untuk ditampilkan dan dicetak</p>
      <table class="table table-striped">
        <thead><tr><th>No</th><th>Nomor</th><th>Tanggal</th><th>Jenis</th><th>Siswa</th><th>Alpha</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; foreach($letters as $l): ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($l['number']) ?></td>
            <td><?= $l['date'] ?></td>
            <td><?= htmlspecialchars($l['type']) ?></td>
            <td><?= htmlspecialchars($l['student_name']) ?></td>
            <td><?= $l['alpha_count'] ?></td>
            <td>
              <a href="view_letter.php?id=<?= $l['id'] ?>" target="_blank" class="btn btn-sm btn-primary">Lihat & Cetak</a>
              <a href="export_letter_pdf.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-success">Export PDF</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>