<?php
// export_database.php
// Eksport SQL dump sederhana untuk semua tabel di database
require_once __DIR__ . '/db.php';

// Nama file
$filename = 'db_export_' . date('Ymd_His') . '.sql';

// Kumpulkan semua table
try {
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlDump = "";
    foreach ($tables as $table) {
        // CREATE TABLE
        $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $create = $row['Create Table'] ?? $row['Create View'] ?? null;
        if ($create) {
            $sqlDump .= "-- ----------------------------\n";
            $sqlDump .= "-- Table structure for `{$table}`\n";
            $sqlDump .= "-- ----------------------------\n";
            $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sqlDump .= $create . ";\n\n";
        }

        // DATA
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $sqlDump .= "-- ----------------------------\n";
            $sqlDump .= "-- Records for `{$table}`\n";
            $sqlDump .= "-- ----------------------------\n";
            foreach ($rows as $r) {
                $columns = array_map(function($c){ return "`{$c}`"; }, array_keys($r));
                $values = array_map(function($v) use ($pdo){
                    if (is_null($v)) return "NULL";
                    // escape using PDO quote
                    return $pdo->quote($v);
                }, array_values($r));
                $sqlDump .= "INSERT INTO `{$table}` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
            }
            $sqlDump .= "\n";
        }
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    echo $sqlDump;
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo "Error creating export: " . $e->getMessage();
    exit;
}