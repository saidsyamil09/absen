<?php
// import_csv.php (updated to accept optional 'mapping' JSON)
// POST multipart/form-data:
//  - table (nama tabel tujuan) [required]
//  - csvfile (file CSV) [required]
//  - mapping (optional) JSON string: { "0": "nis", "1": "name", ... }  // header index -> table column
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method harus POST']);
    exit;
}

if (empty($_POST['table'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Field "table" wajib diisi.']);
    exit;
}
$table = trim($_POST['table']);

if (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File CSV tidak diupload atau terjadi error upload.']);
    exit;
}

$mapping = [];
if (!empty($_POST['mapping'])) {
    $decoded = json_decode($_POST['mapping'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $mapping = $decoded;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mapping JSON tidak valid.']);
        exit;
    }
}

$tmpPath = $_FILES['csvfile']['tmp_name'];

try {
    // validate table exists
    $tables = [];
    $tstmt = $pdo->query("SHOW TABLES");
    while ($row = $tstmt->fetch(PDO::FETCH_NUM)) $tables[] = $row[0];
    if (!in_array($table, $tables, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Tabel tujuan '{$table}' tidak ditemukan."]);
        exit;
    }

    // get table columns
    $cols = [];
    $cstmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    while ($crow = $cstmt->fetch(PDO::FETCH_ASSOC)) $cols[] = $crow['Field'];
    if (empty($cols)) throw new Exception('Tidak dapat membaca kolom tabel.');

    // open CSV
    $handle = fopen($tmpPath, 'r');
    if ($handle === false) throw new Exception('Tidak dapat membuka file CSV.');

    // read header
    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        throw new Exception('CSV kosong atau header tidak ditemukan.');
    }
    $header = array_map(function($h){ return trim((string)$h); }, $header);

    // Build headerToTable mapping and insertCols
    $headerToTable = []; // index => columnName
    $insertCols = [];

    if (!empty($mapping)) {
        // mapping is provided: keys are header indices (strings) or header names; values are table column names
        foreach ($mapping as $k => $v) {
            // allow numeric index keys or header name keys
            if (is_numeric($k)) {
                $idx = (int)$k;
                if ($idx < 0 || $idx >= count($header)) continue;
                $colName = trim($v);
                if ($colName === '') continue;
                if (!in_array($colName, $cols, true)) continue;
                $headerToTable[$idx] = $colName;
                if (!in_array($colName, $insertCols, true)) $insertCols[] = $colName;
            } else {
                // key is header name - find its index
                $hname = trim($k);
                $idx = array_search($hname, $header, true);
                if ($idx === false) continue;
                $colName = trim($v);
                if ($colName === '') continue;
                if (!in_array($colName, $cols, true)) continue;
                $headerToTable[$idx] = $colName;
                if (!in_array($colName, $insertCols, true)) $insertCols[] = $colName;
            }
        }
        if (empty($insertCols)) {
            fclose($handle);
            throw new Exception('Mapping diberikan tetapi tidak cocok dengan kolom tabel.');
        }
    } else {
        // original behavior: auto-detect case-insensitive header -> column
        $lcCols = array_change_key_case(array_flip($cols), CASE_LOWER); // lowercase column -> index
        foreach ($header as $i => $h) {
            $key = mb_strtolower($h);
            if ($key === '') continue;
            if (isset($lcCols[$key])) {
                $tableCol = $cols[$lcCols[$key]];
                $headerToTable[$i] = $tableCol;
                if (!in_array($tableCol, $insertCols, true)) $insertCols[] = $tableCol;
            } else {
                $norm = preg_replace('/[^a-z0-9_]+/i', '_', $key);
                if (isset($lcCols[$norm])) {
                    $tableCol = $cols[$lcCols[$norm]];
                    $headerToTable[$i] = $tableCol;
                    if (!in_array($tableCol, $insertCols, true)) $insertCols[] = $tableCol;
                }
            }
        }
        if (empty($insertCols)) {
            fclose($handle);
            throw new Exception('Tidak ada kolom CSV yang cocok dengan kolom tabel tujuan.');
        }
    }

    // prepare insert statement
    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $colList = implode('`,`', $insertCols);
    $sql = "INSERT INTO `{$table}` (`" . $colList . "`) VALUES ({$placeholders})";
    $stmt = $pdo->prepare($sql);

    // start transaction
    $pdo->beginTransaction();
    $rowNum = 1;
    $inserted = 0;
    $skipped = 0;
    $errors = [];

    while (($data = fgetcsv($handle)) !== false) {
        $rowNum++;
        $values = array_fill(0, count($insertCols), null);
        // map data by header index using headerToTable
        foreach ($headerToTable as $idx => $colName) {
            $pos = array_search($colName, $insertCols, true);
            if ($pos === false) continue;
            $values[$pos] = isset($data[$idx]) ? $data[$idx] : null;
        }
        try {
            $stmt->execute($values);
            $inserted++;
        } catch (Exception $e) {
            $skipped++;
            $errors[] = "Row {$rowNum}: " . $e->getMessage();
        }
    }

    $pdo->commit();
    fclose($handle);

    echo json_encode([
        'success' => true,
        'message' => 'Import selesai.',
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors_sample' => array_slice($errors, 0, 10)
    ]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Import gagal: ' . $e->getMessage()]);
    exit;
}