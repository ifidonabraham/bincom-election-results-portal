<?php
// includes/db.php
// Production-ready database configuration supporting environment variables and local development

$is_local = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']) && empty(getenv('DB_HOST'));

if ($is_local) {
    // Local XAMPP MySQL defaults
    $host = '127.0.0.1';
    $port = 3306;
    $dbname = 'bincom_test';
    $user = 'root';
    $pass = '';
} else {
    // Cloud database (Aiven / remote)
    $host = getenv('DB_HOST') ?: '46.101.174.125';
    $port = getenv('DB_PORT') ?: 17992;
    $dbname = getenv('DB_NAME') ?: 'defaultdb';
    $user = getenv('DB_USER') ?: 'avnadmin';
    $pass = getenv('DB_PASS') !== false && getenv('DB_PASS') !== '' ? getenv('DB_PASS') : (file_exists(__DIR__ . '/.secret') ? trim(file_get_contents(__DIR__ . '/.secret')) : '');
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
];

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection error: ' . $e->getMessage()]);
        exit;
    }
    die("<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;'><h3>Database Connection Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>");
}
