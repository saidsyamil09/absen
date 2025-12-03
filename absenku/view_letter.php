<?php
require_once __DIR__ . '/db.php';
require_login();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT l.*, s.name AS student_name, s.nis FROM letters l LEFT JOIN students s ON s.id = l.student_id WHERE l.id = ?");
$stmt->execute([$id]);
$l = $stmt->fetch();
if (!$l) { echo "Surat tidak ditemukan"; exit; }
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Surat <?= htmlspecialchars($l['number']) ?></title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>@media print{ .no-print{display:none} }</style>
</head>
<body class="p-4">
  <div class="border p-4">
    <h3 class="text-center">Surat Panggilan</h3>
    <p>Nomor: <strong><?= htmlspecialchars($l['number']) ?></strong></p>
    <p>Tanggal: <?= $l['date'] ?></p>
    <p>Kepada Yth: <?= htmlspecialchars($l['student_name']) ?> (NIS: <?= htmlspecialchars($l['nis']) ?>)</p>
    <p>Dengan hormat, berdasarkan catatan kehadiran, siswa <?= htmlspecialchars($l['student_name']) ?> telah absen sebanyak <strong><?= $l['alpha_count'] ?></strong> kali.</p>
    <p>Dimohon hadir ke sekolah / BK untuk klarifikasi.</p>
    <p class="mt-5">Hormat kami,<br>SMKN 2 Luwu</p>
  </div>
  <div class="mt-3 no-print">
    <button class="btn btn-primary" onclick="window.print()">Cetak</button>
    <a class="btn btn-secondary" href="?page=letter_print">Kembali</a>
  </div>
</body>
</html>