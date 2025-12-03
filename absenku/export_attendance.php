<?php
// export_attendance.php (diperbaiki)
// Pastikan file ini diletakkan di project root C:\xampp\htdocs\absenku atau sesuaikan path ke vendor/autoload.php

// Jika koneksi DB ada di file lain, include/require di sini
// contoh: require_once __DIR__ . '/db.php';

// include composer autoload jika tersedia
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Use harus di file scope (tidak berada di dalam if/else/fungsi)
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';

if (!$id) {
    http_response_code(400);
    echo "ID siswa tidak diberikan.";
    exit;
}

// pastikan $pdo ada; jika tidak, coba include koneksi DB di project Anda
if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
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

// validasi tanggal (format Y-m-d) dan susun kondisi SQL
$where = "WHERE student_id = ?";
$params = [$id];

$from_dt = null;
$to_dt = null;
if ($from !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $from);
    if ($d && $d->format('Y-m-d') === $from) {
        $from_dt = $from;
        $where .= " AND date >= ?";
        $params[] = $from_dt;
    } else {
        http_response_code(400);
        echo "Format tanggal 'from' tidak valid. Gunakan YYYY-MM-DD.";
        exit;
    }
}
if ($to !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $to);
    if ($d && $d->format('Y-m-d') === $to) {
        $to_dt = $to;
        $where .= " AND date <= ?";
        $params[] = $to_dt;
    } else {
        http_response_code(400);
        echo "Format tanggal 'to' tidak valid. Gunakan YYYY-MM-DD.";
        exit;
    }
}

// ambil data presensi sesuai filter
$sql = "SELECT * FROM attendance $where ORDER BY date DESC";
$att = $pdo->prepare($sql);
$att->execute($params);
$rows = $att->fetchAll();

// siapkan nama file (aman untuk filesystem)
$student_name_safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $student['name']);
$range_label = '';
if ($from_dt || $to_dt) {
    $range_label = '_' . ($from_dt ?: 'start') . '_' . ($to_dt ?: 'end');
}
$timestamp = date('Ymd_His');
$base_filename = "attendance_{$student_name_safe}{$range_label}_{$timestamp}";

if ($format === 'xlsx') {
    // Pastikan PhpSpreadsheet tersedia (autoload harus sudah di-include di atas)
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        http_response_code(500);
        echo "PhpSpreadsheet tidak ditemukan. Install dengan: composer require phpoffice/phpspreadsheet";
        exit;
    }

    // gunakan PhpSpreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Attendance');

    // header kolom
    $headers = ['NIS', 'Nama', 'Kelas', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Catatan'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $col++;
    }

    // isi data
    $row_num = 2;
    foreach ($rows as $r) {
        $sheet->setCellValue('A' . $row_num, $student['nis']);
        $sheet->setCellValue('B' . $row_num, $student['name']);
        $sheet->setCellValue('C' . $row_num, $student['class']);
        $sheet->setCellValue('D' . $row_num, $r['date']);
        $sheet->setCellValue('E' . $row_num, $r['time_in'] ?: '');
        $sheet->setCellValue('F' . $row_num, $r['time_out'] ?: '');
        $sheet->setCellValue('G' . $row_num, $r['status']);
        $sheet->setCellValue('H' . $row_num, $r['note']);
        $row_num++;
    }

    // Auto-size kolom sederhana
    foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // output
    $filename = $base_filename . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {
    // default CSV export
    $filename = $base_filename . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // BOM agar Excel membaca UTF-8 dengan benar
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        echo "Gagal membuka output stream.";
        exit;
    }

    // header
    fputcsv($out, ['NIS', 'Nama', 'Kelas', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Catatan']);

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
}
?>