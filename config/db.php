<?php
/**
 * Database connection (PDO / MySQL).
 *
 * Credentials are read from environment variables so that no secrets
 * live in the repository. The defaults match a typical local
 * XAMPP/MariaDB setup (root, empty password).
 *
 *   DB_HOST  default: 127.0.0.1
 *   DB_NAME  default: air_ds
 *   DB_USER  default: root
 *   DB_PASS  default: (empty)
 */

$host    = getenv('DB_HOST') ?: '127.0.0.1';
$db      = getenv('DB_NAME') ?: 'air_ds';
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection error.');
}
