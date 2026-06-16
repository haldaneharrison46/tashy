<?php
// ============================================================
// includes/cart.php — Cart helpers (DB-backed)
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';   // tk_column_exists / compute_tax / customer_tax_exempt
require_once __DIR__ . '/auth.php';

// ── Key: user_id for logged-in, session_id for guests ────────
function cart_key(): array {
    if (is_logged_in()) {
        return ['user_id' => $_SESSION['user_id'], 'session_id' => null];
    }
    return ['user_id' => null, 'session_id' => session_id()];
}

function cart_where(): array {
    $key = cart_key();
    if ($key['user_id']) {
        return ['WHERE user_id = ?', [$key['user_id']]];
    }
    return ['WHERE session_id = ?', [$key['session_id']]];
}

// ── Get cart items with product data ─────────────────────────
function get_cart(): array {
    [$clause, $params] = cart_where();
    // products.taxable may not exist yet (pre-migration) — default to taxable.
    $taxCol = tk_column_exists('products', 'taxable') ? 'p.taxable' : '1 AS taxable';
    $stmt = db()->prepare(
        'SELECT ci.id, ci.quantity, p.id AS product_id, p.name, p.brand,
                p.price, p.compare_price, p.image, p.stock, p.slug, ' . $taxCol . '
         FROM cart_items ci
         JOIN products p ON ci.product_id = p.id
         ' . $clause . ' AND p.active = 1
         ORDER BY ci.id'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ── Cart totals ───────────────────────────────────────────────
function cart_totals(): array {
    $items       = get_cart();
    $subtotal    = 0;
    $taxableBase = 0;
    foreach ($items as $item) {
        $line = $item['price'] * $item['quantity'];
        $subtotal += $line;
        if (($item['taxable'] ?? 1)) $taxableBase += $line;
    }
    // Estimate using the editable default rate/threshold (parish-specific
    // zone rate is applied at checkout once the parish is known).
    $thr  = function_exists('free_shipping_threshold') ? free_shipping_threshold() : (float)FREE_SHIPPING_THRESHOLD;
    $rate = function_exists('shipping_default_rate')   ? shipping_default_rate()   : 1500.00;
    $shipping = ($subtotal > 0 && $subtotal < $thr) ? $rate : 0.00;
    // GCT honours per-item taxable flag and the signed-in customer's exemption.
    // Destination country defaults to the tax country here (the cart has no
    // address yet); checkout recomputes once the country is chosen.
    $exempt = function_exists('customer_tax_exempt')
        ? customer_tax_exempt(is_logged_in() ? (int)$_SESSION['user_id'] : null) : false;
    $tax    = function_exists('compute_tax')
        ? compute_tax($taxableBase, ['tax_exempt' => $exempt])
        : round($taxableBase * TAX_RATE, 2);
    $total  = $subtotal + $shipping + $tax;
    return compact('items', 'subtotal', 'taxableBase', 'shipping', 'tax', 'total');
}

// ── Cart item count ───────────────────────────────────────────
function cart_count(): int {
    [$clause, $params] = cart_where();
    $stmt = db()->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items ci ' . $clause);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

// ── Add to cart ───────────────────────────────────────────────
function cart_add(int $productId, int $qty = 1): bool {
    $key = cart_key();
    // Check stock
    $s = db()->prepare('SELECT stock FROM products WHERE id = ? AND active = 1');
    $s->execute([$productId]);
    $product = $s->fetch();
    if (!$product) return false;

    [$clause, $params] = cart_where();
    $check = db()->prepare('SELECT id, quantity FROM cart_items ci ' . $clause . ' AND product_id = ?');
    $check->execute(array_merge($params, [$productId]));
    $existing = $check->fetch();

    if ($existing) {
        $newQty = min($existing['quantity'] + $qty, $product['stock']);
        db()->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')
            ->execute([$newQty, $existing['id']]);
    } else {
        $newQty = min($qty, $product['stock']);
        $stmt   = db()->prepare(
            'INSERT INTO cart_items (user_id, session_id, product_id, quantity) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$key['user_id'], $key['session_id'], $productId, $newQty]);
    }
    return true;
}

// ── Update quantity ───────────────────────────────────────────
function cart_update(int $itemId, int $qty): void {
    [$clause, $params] = cart_where();
    if ($qty <= 0) {
        db()->prepare('DELETE FROM cart_items WHERE id = ? ' . str_replace('WHERE', 'AND', $clause))
            ->execute(array_merge([$itemId], $params));
    } else {
        db()->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? ' . str_replace('WHERE', 'AND', $clause))
            ->execute(array_merge([$qty, $itemId], $params));
    }
}

// ── Remove item ───────────────────────────────────────────────
function cart_remove(int $itemId): void {
    [$clause, $params] = cart_where();
    db()->prepare('DELETE FROM cart_items WHERE id = ? ' . str_replace('WHERE', 'AND', $clause))
        ->execute(array_merge([$itemId], $params));
}

// ── Clear cart ────────────────────────────────────────────────
function cart_clear(): void {
    [$clause, $params] = cart_where();
    db()->prepare('DELETE FROM cart_items ' . $clause)->execute($params);
}
