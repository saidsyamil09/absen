<?php
// delete_student.php
// Hapus siswa berdasarkan id atau nis
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$nis = isset($input['nis']) ? trim($input['nis']) : null;
$id  = isset($input['id']) ? (int)$input['id'] : null;

if (!$nis && !$id) {
    echo json_encode(['success' => false, 'message' => 'Harus memberikan id atau nis untuk menghapus.']);
    exit;
}

try {
    if ($nis) {
        $stmt = $pdo->prepare("DELETE FROM students WHERE nis = :nis");
        $stmt->execute([':nis'=>$nis]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
        $stmt->execute([':id'=>$id]);
    }

    echo json_encode(['success' => true, 'message' => 'Siswa berhasil dihapus.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus siswa: '.$e->getMessage()]);
}