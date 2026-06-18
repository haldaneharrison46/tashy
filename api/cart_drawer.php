<?php
/**
 * JSON API — Cart drawer fragment
 * GET /api/cart_drawer.php → { ok, count, itemsHtml, footerHtml }
 *
 * Renders the cart drawer's item list and footer with the SAME markup
 * and helpers (money(), product_img(), asset_base()) used by
 * includes/header.php, so the client can refresh the drawer live after
 * an add/update without a full page reload.
 */
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/cart.php';

header('Content-Type: application/json');

$cart = get_cart();
$base = asset_base();

/* ── Items HTML (inner contents of #cartItems) ── */
ob_start();
if (empty($cart)): ?>
<div class="cart-empty">
  <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
  <p>Your cart is empty.</p>
  <a href="<?= $base ?>/shop.php" class="btn btn-primary" style="margin-top:8px">Shop Now</a>
</div>
<?php else: foreach ($cart as $item): ?>
<div class="cart-item" data-item-id="<?= (int)$item['id'] ?>">
  <div class="cart-item-img">
    <img src="<?= product_img($item['image']) ?>" alt="<?= h($item['name']) ?>">
  </div>
  <div class="cart-item-info">
    <div class="cart-item-name"><?= h($item['name']) ?></div>
    <div class="cart-item-brand"><?= h($item['brand']) ?></div>
    <div class="cart-item-price"><?= money($item['price']) ?></div>
  </div>
  <div class="cart-qty">
    <div class="cart-qty-row">
      <button class="cart-qty-btn" onclick="cartQty(<?= (int)$item['id'] ?>, <?= (int)$item['quantity'] - 1 ?>)">−</button>
      <span class="cart-qty-num"><?= (int)$item['quantity'] ?></span>
      <button class="cart-qty-btn" onclick="cartQty(<?= (int)$item['id'] ?>, <?= (int)$item['quantity'] + 1 ?>)">+</button>
    </div>
    <button class="cart-remove-btn" onclick="cartRemove(<?= (int)$item['id'] ?>)">Remove</button>
  </div>
</div>
<?php endforeach; endif;
$itemsHtml = ob_get_clean();

/* ── Footer HTML (sibling after #cartItems) ── */
$footerHtml = '';
if (!empty($cart)) {
    $totals = cart_totals();
    ob_start(); ?>
<div class="cart-footer">
  <div class="cart-subtotal">
    <span>Subtotal</span><span><?= money($totals['subtotal']) ?></span>
  </div>
  <?php if ($totals['shipping'] > 0): ?>
  <div class="cart-subtotal"><span>Shipping</span><span><?= money($totals['shipping']) ?></span></div>
  <?php else: ?>
  <div class="cart-subtotal"><span>Shipping</span><span style="color:#3a9e6d">FREE</span></div>
  <?php endif; ?>
  <div class="cart-subtotal cart-total-row">
    <span><strong>Total (incl. GCT)</strong></span>
    <span><strong><?= money($totals['total']) ?></strong></span>
  </div>
  <a href="<?= $base ?>/checkout.php" class="cart-checkout-btn">Proceed to Checkout</a>
  <a href="<?= $base ?>/cart.php" class="cart-view-all">View full cart</a>
</div>
<?php $footerHtml = ob_get_clean();
}

echo json_encode([
    'ok'         => true,
    'count'      => cart_count(),
    'itemsHtml'  => $itemsHtml,
    'footerHtml' => $footerHtml,
]);
