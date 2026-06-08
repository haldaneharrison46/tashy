<?php
// ============================================================
// sitemap.php — XML sitemap (served as /sitemap.xml via .htaccess)
// ============================================================
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim(SITE_URL, '/');
$urls = [];

// Static pages
foreach (['/index.php' => '1.0', '/shop.php' => '0.9', '/about.php' => '0.6',
          '/contact.php' => '0.6', '/wholesale.php' => '0.6', '/policy.php' => '0.4'] as $p => $pri) {
    $urls[] = ['loc' => $base . $p, 'priority' => $pri];
}

// Categories
foreach (get_categories() as $c) {
    $urls[] = ['loc' => $base . '/shop.php?cat=' . urlencode($c['slug']), 'priority' => '0.7'];
}

// Products
$rows = db()->query("SELECT slug FROM products WHERE active = 1")->fetchAll();
foreach ($rows as $r) {
    $urls[] = ['loc' => $base . '/product.php?slug=' . urlencode($r['slug']), 'priority' => '0.8'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n";
    if (!empty($u['lastmod'])) echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";
