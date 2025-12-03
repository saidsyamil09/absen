<?php
// add_student.php
// Tambah siswa baru ke tabel students
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

// Support JSON body or form-data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$nis   = isset($input['nis']) ? trim($input['nis']) : '';
$name  = isset($input['name']) ? trim($input['name']) : '';
$class = isset($input['class']) ? trim($input['class']) : null;

if ($nis === '' || $name === '') {
    echo json_encode(['success' => false, 'message' => 'NIS dan Nama wajib diisi.']);
    exit;
}

try {
    // Cek duplikat nis
    $stmt = $pdo->prepare("SELECT id FROM students WHERE nis = :nis LIMIT 1");
    $stmt->execute([':nis' => $nis]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'NIS sudah terdaftar.']);
        exit;
    }

    $ins = $pdo->prepare("INSERT INTO students (nis, name, class) VALUES (:nis, :name, :class)");
    $ins->execute([':nis'=>$nis, ':name'=>$name, ':class'=>$class]);

    echo json_encode(['success' => true, 'message' => 'Siswa berhasil ditambahkan.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menambah siswa: '.$e->getMessage()]);
}