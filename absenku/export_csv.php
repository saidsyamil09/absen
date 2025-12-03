<?php
// export_csv.php
// Export table CSV atau semua tabel sebagai ZIP
require_once __DIR__ . '/db.php';

$table = isset($_GET['table']) ? trim($_GET['table']) : '';

try {
    // ambil daftar tabel
    $tables = [];
    $tstmt = $pdo->query("SHOW TABLES");
    while ($row = $tstmt->fetch(PDO::FETCH_NUM)) $tables[] = $row[0];

    if ($table === '') {
        throw new Exception('Parameter "table" wajib (contoh: ?table=students atau ?table=all).');
    }

    if ($table === 'all') {
        if (!class_exists('ZipArchive')) throw new Exception('ZipArchive tidak tersedia di PHP ini.');

        $tmpZip = tempnam(sys_get_temp_dir(), 'csvzip_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) throw new Exception('Gagal membuat arsip zip.');

        foreach ($tables as $t) {
            $tmpCsv = tempnam(sys_get_temp_dir(), 'csv_');
            $out = fopen($tmpCsv, 'w');
            $q = $pdo->query("SELECT * FROM `{$t}`");
            $first = true;
            while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                if ($first) { fputcsv($out, array_keys($row)); $first = false; }
                fputcsv($out, array_values($row));
            }
            fclose($out);
            $zip->addFile($tmpCsv, $t . '.csv');
            // jadwalkan hapus file temp saat script berakhir
            register_shutdown_function(function($p){ @unlink($p); }, $tmpCsv);
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="db_export_all_' . date('Ymd_His') . '.zip"');
        header('Content-Length: ' . filesize($tmpZip));
        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    } else {
        if (!in_array($table, $tables, true)) throw new Exception("Tabel '{$table}' tidak ditemukan.");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$table.'_'.date('Ymd_His').'.csv"');
        $out = fopen('php://output', 'w');
        $q = $pdo->query("SELECT * FROM `{$table}`");
        $first = true;
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            if ($first) { fputcsv($out, array_keys($row)); $first = false; }
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    exit;
}