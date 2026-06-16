<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';

$totals = cart_totals();
if (empty($totals['items'])) redirect(asset_base() . '/cart.php');

$user   = current_user();
$errors = [];

$parishes = ['Kingston','St. Andrew','St. Thomas','Portland','St. Mary','St. Ann',
             'Trelawny','St. James','Hanover','Westmoreland','St. Elizabeth',
             'Manchester','Clarendon','St. Catherine'];

// Country list — Jamaica first (the tax country); other destinations are exempt from GCT.
$countries = ['Jamaica','United States','Canada','United Kingdom','Cayman Islands',
              'Trinidad and Tobago','Barbados','Bahamas','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = $_POST;

    $country = trim($f['country'] ?? 'Jamaica') ?: 'Jamaica';
    $isTaxCountry = strcasecmp($country, tax_country()) === 0;
    $payMethods = enabled_payment_methods();
    $payment    = array_key_exists($f['payment'] ?? '', $payMethods) ? $f['payment'] : (string)array_key_first($payMethods);

    // Validate
    if (empty($f['name']))    $errors[] = 'Full name is required.';
    if (empty($f['email']) || !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (empty($f['phone']))   $errors[] = 'Phone number required.';
    if (empty($f['address'])) $errors[] = 'Delivery address required.';
    if (empty($f['city']))    $errors[] = 'City / Community required.';
    if ($isTaxCountry && empty($f['parish'])) $errors[] = 'Parish required.';

    if (empty($errors)) {
        // Shipping: parish-zone rate within Jamaica; flat default rate abroad.
        if ($isTaxCountry) {
            $shipCharge = shipping_for_parish($f['parish'], $totals['subtotal']);
        } else {
            $thr = free_shipping_threshold();
            $shipCharge = ($totals['subtotal'] > 0 && $thr > 0 && $totals['subtotal'] >= $thr) ? 0.0 : shipping_default_rate();
        }
        // GCT: per-item taxable base, customer exemption, destination country.
        $exempt = customer_tax_exempt($user ? (int)$user['id'] : null);
        $taxCharge  = compute_tax($totals['taxableBase'] ?? $totals['subtotal'], ['country' => $country, 'tax_exempt' => $exempt]);
        $orderTotal = $totals['subtotal'] + $shipCharge + $taxCharge;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $orderNum = generate_order_number();
            $stmt = $pdo->prepare('INSERT INTO orders
                (user_id, order_number, subtotal, shipping, tax, total, currency, payment_method,
                 ship_name, ship_email, ship_phone, ship_address, ship_city, ship_parish, ship_country, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $user ? $user['id'] : null,
                $orderNum,
                $totals['subtotal'], $shipCharge, $taxCharge, $orderTotal,
                CURRENCY, $payment,
                $f['name'], $f['email'], $f['phone'],
                $f['address'], $f['city'], ($f['parish'] ?? ''), $country,
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
                    'subtotal' => $totals['subtotal'], 'shipping' => $shipCharge,
                    'tax' => $taxCharge, 'total' => $orderTotal,
                    'ship_name' => $f['name'], 'ship_email' => $f['email'], 'ship_phone' => $f['phone'],
                    'ship_address' => $f['address'], 'ship_city' => $f['city'], 'ship_parish' => ($f['parish'] ?? ''),
                ], $totals['items']);
            } catch (Throwable $e) { /* don't block the order on email errors */ }

            redirect(asset_base() . '/order-success.php?order=' . urlencode($orderNum));
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
              <label class="form-label">Country *</label>
              <select name="country" id="ckCountry" class="form-control" required>
                <?php $selCountry = $_POST['country'] ?? 'Jamaica'; foreach ($countries as $cn): ?>
                <option value="<?= h($cn) ?>" <?= ($selCountry === $cn) ? 'selected' : '' ?>><?= h($cn) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">City / Community *</label>
              <input type="text" name="city" class="form-control" required value="<?= h($_POST['city'] ?? '') ?>">
            </div>
            <div class="form-group" id="ckParishGroup">
              <label class="form-label">Parish <span id="ckParishStar">*</span></label>
              <select name="parish" id="ckParish" class="form-control" required>
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
          <?php $methods = enabled_payment_methods(); $first = true; ?>
          <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ($methods as $mk => $mlabel): ?>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="radio" name="payment" value="<?= h($mk) ?>" <?= $first ? 'checked' : '' ?>> <?= h($mlabel) ?>
              <?= $mk==='cod' ? '<span style="color:#888;font-size:0.85rem">— pay when it arrives</span>' : '' ?>
            </label>
            <?php $first = false; endforeach; ?>
          </div>
          <?php if (array_key_exists('transfer', $methods) && trim((string)get_setting('bank_transfer_details','')) !== ''): ?>
          <p style="color:#888;font-size:0.82rem;margin-top:12px;white-space:pre-wrap"><strong>Bank transfer:</strong> <?= h(get_setting('bank_transfer_details','')) ?></p>
          <?php endif; ?>
          <?php if (array_key_exists('paypal', $methods)): ?>
          <p style="color:#888;font-size:0.82rem;margin-top:10px">Choosing PayPal? You'll get a secure PayPal link on the next screen.</p>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:24px;font-size:1rem;padding:16px">
          Place Order — <span id="ckBtnTotal"><?= money($totals['total']) ?></span>
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
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem" id="ckTaxRow"><span><?= h(tax_label()) ?></span><span id="ckTaxVal"><?= money($totals['tax']) ?></span></div>
          <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem"><span>Total</span><span id="ckTotalVal"><?= money($totals['total']) ?></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
  $_cur = currency_config()[current_currency()];
?>
<script>
(function(){
  var TAX_COUNTRY = <?= json_encode(tax_country()) ?>;
  var RATE        = <?= json_encode((float)$_cur['rate']) ?>;
  var SYM         = <?= json_encode($_cur['symbol']) ?>;
  var SUBTOTAL    = <?= json_encode((float)$totals['subtotal']) ?>;       // JMD
  var SHIPPING    = <?= json_encode((float)$totals['shipping']) ?>;       // JMD (estimate)
  var TAX_JMD     = <?= json_encode((float)$totals['tax']) ?>;           // JMD when taxed
  function fmt(jmd){ return SYM + (jmd * RATE).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  var country = document.getElementById('ckCountry');
  var parish  = document.getElementById('ckParish');
  var star    = document.getElementById('ckParishStar');
  function sync(){
    var isTax = country.value.trim().toLowerCase() === TAX_COUNTRY.toLowerCase();
    // Parish only required for the tax country (Jamaica).
    if (parish){ parish.required = isTax; }
    if (star){ star.style.display = isTax ? '' : 'none'; }
    var tax = isTax ? TAX_JMD : 0;
    document.getElementById('ckTaxVal').textContent = fmt(tax);
    var total = SUBTOTAL + SHIPPING + tax;
    document.getElementById('ckTotalVal').textContent = fmt(total);
    document.getElementById('ckBtnTotal').textContent = fmt(total);
  }
  if (country){ country.addEventListener('change', sync); sync(); }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
