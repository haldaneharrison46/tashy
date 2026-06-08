<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';

$totals = cart_totals();
if (empty($totals['items'])) redirect(SITE_URL . '/cart.php');

$user   = current_user();
$errors = [];

$parishes = ['Kingston','St. Andrew','St. Thomas','Portland','St. Mary','St. Ann',
             'Trelawny','St. James','Hanover','Westmoreland','St. Elizabeth',
             'Manchester','Clarendon','St. Catherine'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = $_POST;

    // Validate
    if (empty($f['name']))    $errors[] = 'Full name is required.';
    if (empty($f['email']) || !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (empty($f['phone']))   $errors[] = 'Phone number required.';
    if (empty($f['address'])) $errors[] = 'Delivery address required.';
    if (empty($f['city']))    $errors[] = 'City / Community required.';
    if (empty($f['parish']))  $errors[] = 'Parish required.';

    if (empty($errors)) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $orderNum = generate_order_number();
            $stmt = $pdo->prepare('INSERT INTO orders
                (user_id, order_number, subtotal, shipping, tax, total, currency,
                 ship_name, ship_email, ship_phone, ship_address, ship_city, ship_parish, ship_country, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $user ? $user['id'] : null,
                $orderNum,
                $totals['subtotal'], $totals['shipping'], $totals['tax'], $totals['total'],
                CURRENCY,
                $f['name'], $f['email'], $f['phone'],
                $f['address'], $f['city'], $f['parish'], 'Jamaica',
                trim($f['notes'] ?? ''),
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, brand, price, quantity) VALUES (?,?,?,?,?,?)');
            foreach ($totals['items'] as $item) {
                $ins->execute([$orderId, $item['product_id'], $item['name'], $item['brand'], $item['price'], $item['quantity']]);
                // Decrement stock
                $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?')
                    ->execute([$item['quantity'], $item['product_id']]);
            }

            cart_clear();
            $pdo->commit();

            // Send confirmation (customer) + alert (store). Non-fatal if mail fails.
            try {
                require_once __DIR__ . '/includes/mail.php';
                send_order_emails([
                    'order_number' => $orderNum,
                    'subtotal' => $totals['subtotal'], 'shipping' => $totals['shipping'],
                    'tax' => $totals['tax'], 'total' => $totals['total'],
                    'ship_name' => $f['name'], 'ship_email' => $f['email'], 'ship_phone' => $f['phone'],
                    'ship_address' => $f['address'], 'ship_city' => $f['city'], 'ship_parish' => $f['parish'],
                ], $totals['items']);
            } catch (Throwable $e) { /* don't block the order on email errors */ }

            redirect(SITE_URL . '/order-success.php?order=' . urlencode($orderNum));
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Order failed. Please try again.';
        }
    }
}

$pageTitle = 'Checkout | ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="section">
  <div class="container" style="max-width:900px">
    <h1 style="margin-bottom:28px">Checkout</h1>

    <?php if ($errors): ?>
    <div style="background:#fef2f2;border:1px solid #fca5a5;color:#c0392b;padding:14px 18px;border-radius:8px;margin-bottom:24px">
      <?php foreach ($errors as $e): ?><p><?= h($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:32px;align-items:start">
      <!-- Form -->
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-card" style="background:var(--white);border:1px solid var(--grey-light);border-radius:12px;padding:28px;margin-bottom:20px">
          <h3 style="margin-bottom:20px">Delivery Details</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= h($user['name'] ?? ($_POST['name'] ?? '')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email *</label>
              <input type="email" name="email" class="form-control" required value="<?= h($user['email'] ?? ($_POST['email'] ?? '')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Phone *</label>
              <input type="tel" name="phone" class="form-control" required value="<?= h($user['phone'] ?? ($_POST['phone'] ?? '')) ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Street Address *</label>
              <input type="text" name="address" class="form-control" required value="<?= h($_POST['address'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">City / Community *</label>
              <input type="text" name="city" class="form-control" required value="<?= h($_POST['city'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Parish *</label>
              <select name="parish" class="form-control" required>
                <option value="">Select Parish</option>
                <?php foreach ($parishes as $p): ?>
                <option value="<?= h($p) ?>" <?= (($_POST['parish'] ?? '') === $p) ? 'selected' : '' ?>><?= h($p) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Order Notes (optional)</label>
              <textarea name="notes" class="form-control" rows="2"><?= h($_POST['notes'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <div class="form-card" style="background:var(--white);border:1px solid var(--grey-light);border-radius:12px;padding:28px">
          <h3 style="margin-bottom:16px">Payment</h3>
          <p style="color:#888;font-size:0.9rem;margin-bottom:12px">We accept payment on delivery (Cash / Bank Transfer). Online payment coming soon.</p>
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <input type="radio" name="payment" value="cod" checked> Cash / Pay on Delivery
          </label>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:24px;font-size:1rem;padding:16px">
          Place Order — <?= money($totals['total']) ?>
        </button>
      </form>

      <!-- Summary -->
      <div style="background:var(--white);border:1px solid var(--grey-light);border-radius:12px;padding:24px;position:sticky;top:80px">
        <h3 style="margin-bottom:16px">Order Summary</h3>
        <?php foreach ($totals['items'] as $item): ?>
        <div style="display:flex;gap:10px;margin-bottom:12px">
          <img src="<?= product_img($item['image']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:6px" alt="">
          <div style="flex:1;min-width:0">
            <p style="font-size:0.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($item['name']) ?></p>
            <p style="font-size:0.78rem;color:#888">Qty: <?= $item['quantity'] ?></p>
          </div>
          <span style="font-size:0.85rem;font-weight:700"><?= money($item['price'] * $item['quantity']) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="border-top:1px solid var(--grey-light);margin-top:12px;padding-top:12px">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem"><span>Subtotal</span><span><?= money($totals['subtotal']) ?></span></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem"><span>Shipping</span><span><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'FREE' ?></span></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem"><span>GCT</span><span><?= money($totals['tax']) ?></span></div>
          <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem"><span>Total</span><span><?= money($totals['total']) ?></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
