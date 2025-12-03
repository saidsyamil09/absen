<?php
// get_tables.php
// Mengembalikan daftar tabel dalam database sebagai JSON
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

try {
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $r[0];
    }
    echo json_encode(['success' => true, 'tables' => $tables]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}