<?php
// Temporary DB reachability test — uploaded, run via PHP CLI, then deleted.
require __DIR__ . '/config/db.php';
try {
    $n = db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo "DB_OK products=$n\n";
} catch (Throwable $e) {
    echo "DB_FAIL: " . $e->getMessage() . "\n";
}
