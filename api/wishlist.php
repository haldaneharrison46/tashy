<?php
/**
 * JSON API — Wishlist toggle
 * POST /api/wishlist.php { product_id: N }
 * GET  /api/wishlist.php → { ids: [N, N, ...] }
 */
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Login required', 'login' => true], 401);

$userId = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare('SELECT product_id FROM wishlist WHERE user_id=?');
    $stmt->execute([$userId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    json_out(['ok' => true, 'ids' => array_map('intval', $ids)]);
}

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$pid  = (int)($body['product_id'] ?? 0);

if (!$pid) json_out(['ok' => false, 'error' => 'Invalid product'], 400);

// Check product exists
$exists = db()->prepare('SELECT id FROM products WHERE id=? AND active=1');
$exists->execute([$pid]);
if (!$exists->fetch()) json_out(['ok' => false, 'error' => 'Product not found'], 404);

// Toggle
$check = db()->prepare('SELECT id FROM wishlist WHERE user_id=? AND product_id=?');
$check->execute([$userId, $pid]);
$inList = $check->fetch();

if ($inList) {
    db()->prepare('DELETE FROM wishlist WHERE user_id=? AND product_id=?')->execute([$userId, $pid]);
    json_out(['ok' => true, 'action' => 'removed', 'product_id' => $pid]);
} else {
    db()->prepare('INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?,?)')->execute([$userId, $pid]);
    json_out(['ok' => true, 'action' => 'added', 'product_id' => $pid]);
}
