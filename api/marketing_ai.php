<?php
// api/marketing_ai.php — admin-only AI endpoint (JSON). Modes: generate | suggest
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/marketing.php';

header('Content-Type: application/json');

$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($in['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$mode = $in['mode'] ?? 'generate';
$pdo  = db();

if ($mode === 'suggest') {
    $rows = $pdo->query("SELECT id, name, price, compare_price, stock, featured, tags
                         FROM products WHERE active = 1 ORDER BY name")->fetchAll();
    echo json_encode(ai_suggest_products($rows, 4));
    exit;
}

// generate
$ids = array_values(array_filter(array_map('intval', (array)($in['product_ids'] ?? []))));
$products = [];
if ($ids) {
    $place = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, name, brand, price, description FROM products WHERE id IN ($place)");
    $st->execute($ids);
    $products = $st->fetchAll();
}

echo json_encode(ai_generate_copy([
    'products' => $products,
    'topic'    => trim($in['topic'] ?? ''),
    'platform' => $in['platform'] ?? 'instagram',
    'tone'     => $in['tone'] ?? 'warm and inviting',
    'count'    => (int)($in['count'] ?? 3),
]));
