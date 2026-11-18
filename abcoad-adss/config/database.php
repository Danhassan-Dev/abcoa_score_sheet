<?php
declare(strict_types=1);

/**
 * Local development database connection for ABCOAD ADSS.
 * For production, move credentials to environment configuration
 * and use a dedicated MySQL account with limited privileges.
 */

define('DB_HOST', getenv('ADSS_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('ADSS_DB_NAME') ?: 'abcoad_adss');
define('DB_USER', getenv('ADSS_DB_USER') ?: 'root');
define('DB_PASS', (getenv('ADSS_DB_PASS') !== false) ? (string)getenv('ADSS_DB_PASS') : '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('ADSS database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please contact the system administrator.');
}
