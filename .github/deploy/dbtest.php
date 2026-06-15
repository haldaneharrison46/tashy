<?php
// Temporary DB reachability test — uploaded, run via PHP CLI, then deleted.
// Connects directly with a short timeout so it can't hang, and prints a clear
// RESULT line. Reuses the DB_* constants from the provisioned config.
error_reporting(E_ERROR);
require __DIR__ . '/config/db.php';
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_TIMEOUT => 8, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $n = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo "\nRESULT=DB_OK products=$n\n";
} catch (Throwable $e) {
    echo "\nRESULT=DB_FAIL " . $e->getMessage() . "\n";
}
