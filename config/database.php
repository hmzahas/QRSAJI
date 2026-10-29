<?php

$host     = getenv('DB_HOST')   ?: 'localhost';
$dbname   = getenv('DB_NAME')   ?: 'qrsaji_db';
$username = getenv('DB_USER')   ?: 'root';
$password = getenv('DB_PASS')   ?: '';
$port     = getenv('DB_PORT')   ?: '3306';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi MySQL QRSAJI.');
}
