<?php
/**
 * JSON API — Product search (autocomplete + full)
 * GET /api/search.php?q=TERM&limit=8&cat=CATEGORY_ID
 */
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

$q     = trim($_GET['q'] ?? '');
$limit = min(20, max(1, (int)($_GET['limit'] ?? 8)));
$cat   = (int)($_GET['cat'] ?? 0);

if (strlen($q) < 2) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

$params = [];
$conditions = ['p.active = 1'];

// FULLTEXT search
$useFulltext = strlen($q) >= 3;
if ($useFulltext) {
    $conditions[] = 'MATCH(p.name, p.brand, p.description, p.tags) AGAINST (? IN BOOLEAN MODE)';
    $params[] = '+' . implode('* +', explode(' ', preg_replace('/\s+/', ' ', $q))) . '*';
} else {
    $conditions[] = '(p.name LIKE ? OR p.brand LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat) {
    $conditions[] = 'p.category_id = ?';
    $params[] = $cat;
}

$where = 'WHERE ' . implode(' AND ', $conditions);
$orderBy = $useFulltext
    ? 'ORDER BY MATCH(p.name, p.brand, p.description, p.tags) AGAINST (? IN BOOLEAN MODE) DESC'
    : 'ORDER BY p.featured DESC, p.name ASC';

if ($useFulltext) $params[] = '+' . implode('* +', explode(' ', preg_replace('/\s+/', ' ', $q))) . '*';
$params[] = $limit;

$sql = "SELECT p.id, p.name, p.brand, p.slug, p.price, p.compare_price, p.image, c.name as category
        FROM products p LEFT JOIN categories c ON p.category_id = c.id
        $where $orderBy LIMIT ?";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$results = array_map(function($r) {
    return [
        'id'            => (int)$r['id'],
        'name'          => $r['name'],
        'brand'         => $r['brand'],
        'slug'          => $r['slug'],
        'price'         => (float)$r['price'],
        'price_fmt'     => money($r['price']),
        'compare_price' => $r['compare_price'] ? (float)$r['compare_price'] : null,
        'image'         => product_img($r['image']),
        'category'      => $r['category'],
        'url'           => SITE_URL . '/product.php?slug=' . urlencode($r['slug']),
    ];
}, $rows);

echo json_encode(['ok' => true, 'results' => $results, 'query' => $q]);
