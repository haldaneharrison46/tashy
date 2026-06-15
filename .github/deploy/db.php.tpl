<?php
define('DB_HOST',    '__DB_HOST__');
define('DB_NAME',    '__DB_NAME__');
define('DB_USER',    '__DB_USER__');
define('DB_PASS',    '__DB_PASS__');
define('DB_CHARSET', 'utf8mb4');
define('SITE_NAME',  'Tashy Kollections');
define('SITE_URL',   '__SITE_URL__');
define('SITE_EMAIL', 'hello@tashykollections.com');
define('SITE_ADDRESS', '37 Cornwall Street, Falmouth, Trelawny, Jamaica');
define('CURRENCY',   'JMD');
define('CURRENCY_SYMBOL', 'J$');
define('TAX_RATE',   0.15);
define('FREE_SHIPPING_THRESHOLD', 5000);
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
