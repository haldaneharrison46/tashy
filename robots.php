<?php
// ============================================================
// robots.php — served as /robots.txt via .htaccess
// Base-path aware: paths and the sitemap URL follow SITE_URL, so this is
// correct whether the site runs at the web root or in a /tashy sub-folder.
// ============================================================
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=UTF-8');
$b = base_path(); // '' at root, '/tashy' in a sub-folder
?>
User-agent: *
Allow: <?= $b ?>/
Disallow: <?= $b ?>/admin/
Disallow: <?= $b ?>/api/
Disallow: <?= $b ?>/config/
Disallow: <?= $b ?>/includes/
Disallow: <?= $b ?>/database/

Sitemap: <?= rtrim(SITE_URL, '/') ?>/sitemap.xml
