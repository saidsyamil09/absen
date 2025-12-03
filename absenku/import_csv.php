<?php
// import_csv.php
// Import CSV ke tabel tujuan. POST multipart/form-data:
//  - table (nama tabel tujuan)
//  - csvfile (file CSV)
// Returns JSON summary.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method harus POST']);
    exit;
}

if (empty($_POST['table'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Field "table" wajib diisi.']);
    exit;
}
$table = trim($_POST['table']);

if (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'File CSV tidak diupload atau terjadi error upload.']);
    exit;
}

$tmpPath = $_FILES['csvfile']['tmp_name'];

try {
    // validasi tabel
    $tables = [];
    $tstmt = $pdo->query("SHOW TABLES");
    while ($row = $tstmt->fetch(PDO::FETCH_NUM)) $tables[] = $row[0];
    if (!in_array($table, $tables, true)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>"Tabel tujuan '{$table}' tidak ditemukan."]);
        exit;
    }

    // baca kolom tabel
    $cols = [];
    $cstmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    while ($crow = $cstmt->fetch(PDO::FETCH_ASSOC)) $cols[] = $crow['Field'];
    if (empty($cols)) throw new Exception('Tidak dapat membaca kolom tabel.');

    // buka CSV
    $handle = fopen($tmpPath, 'r');
    if ($handle === false) throw new Exception('Tidak dapat membuka file CSV.');

    // baca header (first row)
    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        throw new Exception('CSV kosong atau header tidak ditemukan.');
    }
    // normalize header dan kolom (case-insensitive match)
    $header = array_map(function($h){ return trim((string)$h); }, $header);
    $lcCols = array_change_key_case(array_flip($cols), CASE_LOWER); // colNameLower => index
    // buat mapping: headerIndex -> tableColumnName (jika cocok)
    $insertCols = [];
    $headerToTable = [];
    foreach ($header as $i => $h) {
        $key = mb_strtolower($h);
        if ($key === '') continue;
        if (isset($lcCols[$key])) {
            // header exactly matches column name (case-insensitive)
            $tableCol = $cols[$lcCols[$key]];
            $insertCols[] = $tableCol;
            $headerToTable[$i] = $tableCol;
        } else {
            // coba replace spasi dan non-alnum -> underscore and match again
            $norm = preg_replace('/[^a-z0-9_]+/i', '_', $key);
            if (isset($lcCols[$norm])) {
                $tableCol = $cols[$lcCols[$norm]];
                $insertCols[] = $tableCol;
                $headerToTable[$i] = $tableCol;
            } else {
                // tidak cocok -> diabaikan
            }
        }
    }

    if (empty($insertCols)) {
        fclose($handle);
        throw new Exception('Tidak ada kolom CSV yang cocok dengan kolom tabel tujuan.');
    }

    // siapkan statement insert
    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $colList = implode('`,`', $insertCols);
    $sql = "INSERT INTO `{$table}` (`" . $colList . "`) VALUES ({$placeholders})";
    $stmt = $pdo->prepare($sql);

    // mulai transaction
    $pdo->beginTransaction();
    $rowNum = 1;
    $inserted = 0;
    $skipped = 0;
    $errors = [];

    while (($data = fgetcsv($handle)) !== false) {
        $rowNum++;
        $values = [];
        foreach ($insertCols as $col) $values[] = null; // initialize
        // map data
        foreach ($headerToTable as $idx => $colName) {
            $pos = array_search($colName, $insertCols, true);
            $values[$pos] = isset($data[$idx]) ? $data[$idx] : null;
        }
        try {
            $stmt->execute($values);
            $inserted++;
        } catch (Exception $e) {
            $skipped++;
            $errors[] = "Row {$rowNum}: ".$e->getMessage();
            // lanjutkan
        }
    }

    $pdo->commit();
    fclose($handle);

    echo json_encode([
        'success'=>true,
        'message'=>'Import selesai.',
        'inserted'=>$inserted,
        'skipped'=>$skipped,
        'errors_sample'=>array_slice($errors,0,10)
    ]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Import gagal: '.$e->getMessage()]);
    exit;
}