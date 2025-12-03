<?php
// db.php
// Sesuaikan DSN / username / password sesuai environment Anda.
// Contoh menggunakan MySQL
$DB_HOST = '127.0.0.1';
$DB_NAME = 'e_absensi';
$DB_USER = 'db_user';
$DB_PASS = 'db_pass';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Pada production, jangan tampilkan error mentah
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}