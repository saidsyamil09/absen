<?php
// get_table_columns.php
// Query: get_table_columns.php?table=students
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

if (empty($_GET['table'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter table wajib.']);
    exit;
}

$table = preg_replace('/[^a-z0-9_]/i', '', $_GET['table']);

try {
    $cols = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $r['Field'];
    }
    echo json_encode(['success' => true, 'columns' => $cols]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}