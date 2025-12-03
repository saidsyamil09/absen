<?php
// import_database.php
// Hati-hati: skrip ini akan mengeksekusi SQL dari file yang diupload.
// Hanya terima file dari sumber tepercaya. Pertimbangkan membatasi akses admin.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method harus POST.']);
    exit;
}

if (!isset($_FILES['sqlfile']) || $_FILES['sqlfile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada file SQL yang diupload atau terjadi error upload.']);
    exit;
}

$uploadedPath = $_FILES['sqlfile']['tmp_name'];
$contents = file_get_contents($uploadedPath);
if ($contents === false || trim($contents) === '') {
    echo json_encode(['success' => false, 'message' => 'File kosong atau tidak dapat dibaca.']);
    exit;
}

try {
    // Disarankan backup DB sebelum import. Kita akan eksekusi di dalam transaksi.
    $pdo->beginTransaction();
    // Split statements pada tanda ; di akhir baris
    // Catatan: ini sederhana dan bisa gagal jika file SQL kompleks (procedures, delimiter custom).
    $statements = array_filter(array_map('trim', preg_split('/;\\s*\\n/', $contents)));
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Import berhasil.']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Import gagal: '.$e->getMessage()]);
}