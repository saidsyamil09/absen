<?php
// Export surat ke PDF menggunakan Dompdf (composer required)
// Jika Dompdf belum terinstall, tunjukkan pesan
require_once __DIR__ . '/db.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT l.*, s.name AS student_name, s.nis FROM letters l LEFT JOIN students s ON s.id = l.student_id WHERE l.id = ?");
$stmt->execute([$id]);
$l = $stmt->fetch();
if (!$l) { echo "Surat tidak ditemukan"; exit; }

// cek apakah Dompdf ada
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<p>Library Dompdf tidak ditemukan. Untuk export PDF, jalankan di folder proyek: <code>composer require dompdf/dompdf</code></p>";
    echo "<p><a href='view_letter.php?id={$id}'>Buka tampilan surat (print manual)</a></p>";
    exit;
}

require __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;

$html = '<html><head><meta charset="utf-8"><style>body{font-family:Arial,Helvetica,sans-serif;padding:20px}</style></head><body>';
$html .= '<h3 style="text-align:center">Surat Panggilan</h3>';
$html .= '<p>Nomor: <strong>'.htmlspecialchars($l['number']).'</strong></p>';
$html .= '<p>Tanggal: '.htmlspecialchars($l['date']).'</p>';
$html .= '<p>Kepada Yth: '.htmlspecialchars($l['student_name']).' (NIS: '.htmlspecialchars($l['nis']).')</p>';
$html .= '<p>Dengan hormat, berdasarkan catatan kehadiran, siswa <strong>'.htmlspecialchars($l['student_name']).'</strong> telah absen sebanyak <strong>'.$l['alpha_count'].'</strong> kali.</p>';
$html .= '<p>Dimohon hadir ke sekolah / BK untuk klarifikasi.</p>';
$html .= '<p style="margin-top:50px">Hormat kami,<br>SMKN 2 Luwu</p>';
$html .= '</body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','portrait');
$dompdf->render();
$dompdf->stream('surat_'.$l['number'].'.pdf', ['Attachment'=>0]);
exit;