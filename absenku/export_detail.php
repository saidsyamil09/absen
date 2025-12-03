<?php
// export_attendance.php
// Export data presensi (attendance) seorang siswa ke CSV.
// Pastikan $pdo tersedia di scope ini (sama seperti detail.php).
// Jika Anda menggunakan file koneksi terpisah, uncomment require yang sesuai, contoh:
// require_once 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo "ID siswa tidak diberikan.";
    exit;
}

// pastikan $pdo ada; jika tidak, require koneksi DB Anda
if (!isset($pdo)) {
    // Sesuaikan path ke file koneksi Anda jika perlu
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        // Jika $pdo tidak ada dan file koneksi tidak ditemukan, hentikan.
        http_response_code(500);
        echo "Koneksi database tidak ditemukan. Sesuaikan export_attendance.php untuk include file koneksi Anda.";
        exit;
    }
}

// ambil data siswa
$s = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$s->execute([$id]);
$student = $s->fetch();
if (!$student) {
    http_response_code(404);
    echo "Siswa tidak ditemukan.";
    exit;
}

// ambil data presensi
$att = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC");
$att->execute([$id]);
$rows = $att->fetchAll();

// siapkan nama file
$student_name_safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $student['name']);
$filename = sprintf("attendance_%s_%s.csv", $student_name_safe, date('Ymd_His'));

// header untuk download CSV (Excel-friendly)
// BOM UTF-8 agar Excel menampilkan karakter UTF-8 dengan benar
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

// tulis BOM
echo "\xEF\xBB\xBF";

// buka output sebagai stream dan gunakan fputcsv untuk escape otomatis
$out = fopen('php://output', 'w');
if ($out === false) {
    http_response_code(500);
    echo "Gagal membuka output stream.";
    exit;
}

// header kolom
fputcsv($out, ['NIS', 'Nama', 'Kelas', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Catatan']);

// jika tidak ada baris, tetap kirim header saja (bisa diubah sesuai kebutuhan)
foreach ($rows as $r) {
    $line = [
        $student['nis'],
        $student['name'],
        $student['class'],
        $r['date'],
        $r['time_in'] ?: '',
        $r['time_out'] ?: '',
        $r['status'],
        $r['note']
    ];
    fputcsv($out, $line);
}

fclose($out);
exit;
?>