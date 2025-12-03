<?php
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo "<div class='alert alert-danger'>ID siswa tidak diberikan.</div>";
    return;
}
// pastikan $pdo sudah tersedia (sama seperti sebelumnya)
// jika koneksi DB ada di file lain, include/require sesuai project Anda
// contoh: require_once __DIR__ . '/db.php';

$s = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$s->execute([$id]);
$student = $s->fetch();
if (!$student) {
    echo "<div class='alert alert-danger'>Siswa tidak ditemukan.</div>";
    return;
}

// ambil presensi (tanpa filter - ditampilkan semua, export bisa menggunakan filter dari form)
$att = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC");
$att->execute([$id]);
$rows = $att->fetchAll();
?>
<div class="container-fluid">
  <h4>Detail Siswa: <?= htmlspecialchars($student['name']) ?></h4>
  <div class="card mb-3">
    <div class="card-body">
      <p><strong>NIS:</strong> <?= htmlspecialchars($student['nis']) ?></p>
      <p><strong>Kelas:</strong> <?= htmlspecialchars($student['class']) ?></p>

      <!-- Form filter tanggal + tombol export -->
      <form action="export_attendance.php" method="get" target="_blank" class="form-inline">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group mb-2 mr-2">
          <label for="from" class="sr-only">Dari</label>
          <input type="date" id="from" name="from" class="form-control" placeholder="Dari">
        </div>
        <div class="form-group mb-2 mr-2">
          <label for="to" class="sr-only">Sampai</label>
          <input type="date" id="to" name="to" class="form-control" placeholder="Sampai">
        </div>

        <!-- Dua tombol submit, menentukan format -->
        <button type="submit" name="format" value="csv" class="btn btn-primary mb-2 mr-2">Export CSV</button>
        <button type="submit" name="format" value="xlsx" class="btn btn-success mb-2">Export XLSX</button>
      </form>

    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <table class="table table-bordered">
        <thead><tr><th>No</th><th>Tanggal</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php $no=1; foreach($rows as $r): ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($r['date']) ?></td>
            <td><?= htmlspecialchars($r['time_in'] ?: '-') ?></td>
            <td><?= htmlspecialchars($r['time_out'] ?: '-') ?></td>
            <td>
              <?php
                $badge = 'secondary';
                if ($r['status'] === 'Hadir') $badge = 'success';
                elseif ($r['status'] === 'Terlambat') $badge = 'warning';
                elseif ($r['status'] === 'Alpha') $badge = 'danger';
              ?>
              <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($r['status']) ?></span>
            </td>
            <td><?= htmlspecialchars($r['note']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>