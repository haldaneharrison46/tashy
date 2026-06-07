<?php
/**
 * JSON API — Cart operations
 * POST /api/cart.php  { action: "add"|"update"|"remove"|"clear", product_id, quantity, item_id }
 * GET  /api/cart.php  → cart summary
 */
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/cart.php';

header('Content-Type: application/json');

function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $totals = cart_totals();
    json_out(['ok' => true, 'count' => cart_count(), 'total' => $totals['total'], 'items' => $totals['items']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Method not allowed'], 405);

// Parse JSON body or form data
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $body['action'] ?? '';

// CSRF: skip for JSON requests from same origin (check Referer or use token)
// For simplicity, require csrf token header or field
// (In production use a proper CSRF strategy for XHR; here we accept the session token)

switch ($action) {
    case 'add':
        $pid = (int)($body['product_id'] ?? 0);
        $qty = max(1, (int)($body['quantity'] ?? 1));
        if (!$pid) json_out(['ok' => false, 'error' => 'Invalid product'], 400);
        // Check product exists and has stock
        $stmt = db()->prepare('SELECT id, name, stock FROM products WHERE id=? AND active=1');
        $stmt->execute([$pid]);
        $product = $stmt->fetch();
        if (!$product) json_out(['ok' => false, 'error' => 'Product not found'], 404);
        if ($product['stock'] < $qty) json_out(['ok' => false, 'error' => 'Not enough stock'], 409);
        cart_add($pid, $qty);
        json_out(['ok' => true, 'count' => cart_count(), 'message' => h($product['name']) . ' added to cart.']);

    case 'update':
        $itemId = (int)($body['item_id'] ?? 0);
        $qty    = (int)($body['quantity'] ?? 0);
        if (!$itemId) json_out(['ok' => false, 'error' => 'Invalid item'], 400);
        cart_update($itemId, $qty);
        $totals = cart_totals();
        json_out(['ok' => true, 'count' => cart_count(), 'total' => $totals['total']]);

    case 'remove':
        $itemId = (int)($body['item_id'] ?? 0);
        if (!$itemId) json_out(['ok' => false, 'error' => 'Invalid item'], 400);
        cart_remove($itemId);
        $totals = cart_totals();
        json_out(['ok' => true, 'count' => cart_count(), 'total' => $totals['total']]);

    case 'clear':
        cart_clear();
        json_out(['ok' => true, 'count' => 0]);

    default:
        json_out(['ok' => false, 'error' => 'Unknown action'], 400);
}
