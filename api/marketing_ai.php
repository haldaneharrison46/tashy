<?php
// api/marketing_ai.php — admin-only AI copy generation endpoint (JSON)
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

// CSRF
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($in['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$opts = [
    'platform' => $in['platform'] ?? 'instagram',
    'tone'     => $in['tone'] ?? 'warm and inviting',
    'count'    => (int)($in['count'] ?? 3),
    'topic'    => trim($in['topic'] ?? ''),
];

$pid = (int)($in['product_id'] ?? 0);
if ($pid > 0) {
    $st = db()->prepare('SELECT name, brand, price, description FROM products WHERE id = ?');
    $st->execute([$pid]);
    if ($p = $st->fetch()) $opts['product'] = $p;
}

echo json_encode(ai_generate_copy($opts));
