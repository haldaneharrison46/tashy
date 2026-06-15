<?php
// ============================================================
// seed-catalog.php — ONE-TIME home-decor catalog seeder
//
// Runs database/seed-home-decor.sql against the live database.
// Admin login required. DELETE THIS FILE after a successful run.
//
//   1. Log in as admin (admin/login.php)
//   2. Visit  /seed-catalog.php?confirm=SEED
//   3. Delete this file from the server.
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_admin(); // redirects to admin login if not an admin

header('Content-Type: text/html; charset=utf-8');
$sqlFile = __DIR__ . '/database/seed-home-decor.sql';

echo '<!DOCTYPE html><meta charset="utf-8"><title>Seed Catalog</title>';
echo '<body style="font-family:system-ui,Arial,sans-serif;max-width:720px;margin:40px auto;padding:0 20px;line-height:1.6">';
echo '<h1>Tashy Kollections — Catalog Seeder</h1>';

if (($_GET['confirm'] ?? '') !== 'SEED') {
    echo '<p style="background:#fff8e1;border-left:4px solid #f0c040;padding:12px 16px">';
    echo '<strong>Warning:</strong> This will <strong>delete the current products & categories</strong> '
       . '(and clear carts/wishlists) and replace them with the home-decor catalog. '
       . 'Existing order history is preserved.</p>';
    echo '<p><a href="?confirm=SEED" style="display:inline-block;background:#c9956c;color:#fff;'
       . 'padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600">Run the seed now</a></p>';
    echo '</body>';
    exit;
}

if (!is_readable($sqlFile)) {
    echo '<p style="color:#c0392b">Could not read database/seed-home-decor.sql — make sure it was uploaded.</p>';
    exit;
}

// Split the SQL file into individual statements (no ';' appears inside our literals).
$raw   = file_get_contents($sqlFile);
$lines = preg_split('/\r?\n/', $raw);
$clean = [];
foreach ($lines as $line) {
    if (preg_match('/^\s*--/', $line)) continue; // skip comment lines
    $clean[] = $line;
}
$sql        = implode("\n", $clean);
$statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');

$pdo = db();
$ran = 0; $errors = [];
foreach ($statements as $stmt) {
    try {
        $pdo->exec($stmt);
        $ran++;
    } catch (Throwable $e) {
        $errors[] = h(substr($stmt, 0, 60)) . '… → ' . h($e->getMessage());
    }
}

$catCount  = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$prodCount = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

if ($errors) {
    echo '<p style="color:#c0392b"><strong>' . count($errors) . ' statement(s) failed:</strong></p><ul>';
    foreach ($errors as $e) echo '<li style="color:#c0392b;font-size:0.85rem">' . $e . '</li>';
    echo '</ul>';
} else {
    echo '<p style="background:#e8f7ef;border-left:4px solid #3a9e6d;padding:12px 16px">'
       . '✅ Success — ran ' . $ran . ' statements.</p>';
}
echo '<p>Catalog now has <strong>' . $catCount . ' categories</strong> and <strong>'
   . $prodCount . ' products</strong>.</p>';
echo '<p style="background:#fdecea;border-left:4px solid #c0392b;padding:12px 16px">'
   . '<strong>Now delete this file</strong> (seed-catalog.php) from the server.</p>';
echo '<p><a href="' . asset_base() . '/shop.php">View the shop →</a></p>';
echo '</body>';
