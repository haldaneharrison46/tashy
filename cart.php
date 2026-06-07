<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';

// Handle form actions (update qty / remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);
    if ($action === 'update' && $itemId) {
        cart_update($itemId, (int)($_POST['qty'] ?? 0));
    } elseif ($action === 'remove' && $itemId) {
        cart_remove($itemId);
    }
    redirect(SITE_URL . '/cart.php');
}

$totals    = cart_totals();
$pageTitle = 'Your Cart | ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="section">
  <div class="container" style="max-width:860px">
    <h1 style="margin-bottom:28px">Your Cart</h1>

    <?php if (empty($totals['items'])): ?>
    <div style="text-align:center;padding:60px 0">
      <p style="color:#999;font-size:1.1rem">Your cart is empty.</p>
      <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary" style="margin-top:20px">Continue Shopping</a>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:1fr 320px;gap:32px;align-items:start">
      <!-- Items -->
      <div>
        <table class="cart-table" style="width:100%;border-collapse:collapse">
          <thead><tr style="border-bottom:2px solid var(--grey-light)">
            <th style="text-align:left;padding-bottom:12px">Product</th>
            <th style="text-align:center;padding-bottom:12px">Qty</th>
            <th style="text-align:right;padding-bottom:12px">Total</th>
            <th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($totals['items'] as $item): ?>
          <tr style="border-bottom:1px solid var(--grey-light)">
            <td style="padding:16px 0;display:flex;gap:14px;align-items:center">
              <img src="<?= product_img($item['image']) ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px" alt="">
              <div>
                <a href="<?= SITE_URL ?>/product.php?slug=<?= h($item['slug']) ?>" style="font-weight:600"><?= h($item['name']) ?></a>
                <div style="font-size:0.8rem;color:#888"><?= h($item['brand']) ?></div>
                <div style="font-size:0.85rem;color:var(--rose-gold)"><?= money($item['price']) ?></div>
              </div>
            </td>
            <td style="text-align:center">
              <form method="post" style="display:inline-flex;align-items:center;gap:6px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <input type="number" name="qty" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>"
                       style="width:54px;text-align:center;border:1px solid var(--grey-light);border-radius:6px;padding:4px" onchange="this.form.submit()">
              </form>
            </td>
            <td style="text-align:right;font-weight:700"><?= money($item['price'] * $item['quantity']) ?></td>
            <td style="text-align:right">
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <button type="submit" style="background:none;border:none;color:#c0392b;cursor:pointer;font-size:1.1rem" title="Remove">✕</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:16px">
          <a href="<?= SITE_URL ?>/shop.php" class="btn btn-outline btn-sm">← Continue Shopping</a>
        </div>
      </div>

      <!-- Summary -->
      <div class="cart-summary" style="background:var(--white);border:1px solid var(--grey-light);border-radius:12px;padding:24px">
        <h3 style="margin-bottom:20px">Order Summary</h3>
        <div style="display:flex;justify-content:space-between;margin-bottom:10px"><span>Subtotal</span><span><?= money($totals['subtotal']) ?></span></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:10px">
          <span>Shipping</span>
          <span><?= $totals['shipping'] > 0 ? money($totals['shipping']) : '<span style="color:#3a9e6d">FREE</span>' ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:10px"><span>GCT (15%)</span><span><?= money($totals['tax']) ?></span></div>
        <?php if ($totals['shipping'] > 0): ?>
        <p style="font-size:0.78rem;color:#888;margin-bottom:14px">Add <?= money(FREE_SHIPPING_THRESHOLD - $totals['subtotal']) ?> more for free shipping!</p>
        <?php endif; ?>
        <div style="border-top:1px solid var(--grey-light);padding-top:14px;display:flex;justify-content:space-between;font-weight:700;font-size:1.05rem"><span>Total</span><span><?= money($totals['total']) ?></span></div>
        <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary" style="width:100%;margin-top:20px;text-align:center">Checkout →</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
