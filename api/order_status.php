<?php
// api/order_status.php — admin-only: move an order to a new status (logs + notifies)
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';

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

$orderId = (int)($in['order_id'] ?? 0);
$status  = (string)($in['status'] ?? '');
$note    = trim((string)($in['note'] ?? '')) ?: 'Moved on board';

if (!$orderId || !in_array($status, order_statuses(), true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$ok = record_order_status($orderId, $status, $note, $u['name']);
echo json_encode(['ok' => $ok, 'status' => $status]);
