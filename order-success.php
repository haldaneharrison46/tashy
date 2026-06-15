<?php
require_once __DIR__ . '/includes/functions.php';
$orderNum = trim($_GET['order'] ?? '');
$order    = null;
if ($orderNum) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ?');
    $stmt->execute([$orderNum]);
    $order = $stmt->fetch();
}
$pageTitle = 'Order Confirmed | ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<div class="section" style="text-align:center">
  <div class="container" style="max-width:600px">
    <div style="font-size:3.5rem;margin-bottom:16px">🎉</div>
    <h1 style="color:var(--rose-gold)">Order Confirmed!</h1>
    <?php if ($order): ?>
    <p style="margin-top:12px;color:#666">Thank you, <strong><?= h($order['ship_name']) ?></strong>! Your order <strong><?= h($order['order_number']) ?></strong> has been placed.</p>
    <p style="color:#888;margin-top:8px;font-size:0.9rem">We'll contact you at <strong><?= h($order['ship_email']) ?></strong> with delivery updates.</p>
    <div style="background:var(--rose-pale);border-radius:12px;padding:20px;margin:28px 0;text-align:left">
      <p><strong>Order #:</strong> <?= h($order['order_number']) ?></p>
      <p><strong>Total:</strong> <?= money($order['total']) ?></p>
      <p><strong>Delivering to:</strong> <?= h($order['ship_address']) ?>, <?= h($order['ship_city']) ?>, <?= h($order['ship_parish']) ?></p>
      <p><strong>Payment:</strong> Cash on Delivery</p>
    </div>
    <?php else: ?>
    <p style="margin-top:12px;color:#666">Your order has been placed successfully.</p>
    <?php endif; ?>
    <div style="display:flex;gap:12px;justify-content:center;margin-top:16px">
      <a href="<?= asset_base() ?>/shop.php" class="btn btn-primary">Continue Shopping</a>
      <?php if (is_logged_in()): ?>
      <a href="<?= asset_base() ?>/account.php?tab=orders" class="btn btn-outline">View My Orders</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
