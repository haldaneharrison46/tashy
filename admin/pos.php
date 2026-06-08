<?php
// Bootstrap auth + DB BEFORE any output so the post-sale redirect works
// (the server has output_buffering OFF, so header.php must come after the POST handlers).
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$pdo = db();
$TAX = defined('TAX_RATE') ? (float)TAX_RATE : 0.15;

/* ─────────────────────────────────────────────────────────────
   Complete a sale  →  create order (channel=pos), decrement stock
   ───────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_sale'])) {
    csrf_check();

    $lines       = json_decode($_POST['cart_json'] ?? '[]', true) ?: [];
    $custId      = (int)($_POST['customer_id'] ?? 0) ?: null;
    $custName    = trim($_POST['customer_name'] ?? '') ?: 'Walk-in Customer';
    $custEmail   = trim($_POST['customer_email'] ?? '');
    $custPhone   = trim($_POST['customer_phone'] ?? '');
    $payMethod   = in_array($_POST['payment_method'] ?? '', ['cash','card','transfer'], true) ? $_POST['payment_method'] : 'cash';
    $chargeGct   = !empty($_POST['charge_gct']);
    $discount    = max(0, (float)($_POST['discount'] ?? 0));
    $tendered    = max(0, (float)($_POST['amount_paid'] ?? 0));
    $fulfil      = ($_POST['fulfillment'] ?? 'pickup') === 'delivery' ? 'delivery' : 'pickup';
    $shipAddr    = trim($_POST['ship_address'] ?? '');
    $shipCity    = trim($_POST['ship_city'] ?? '');
    $shipParish  = trim($_POST['ship_parish'] ?? '');
    $shipZoneId  = (int)($_POST['ship_zone'] ?? 0);
    $contact     = trim($_POST['contact'] ?? '');
    $custAddr    = trim($_POST['cust_address'] ?? '');
    $instructions= trim($_POST['instructions'] ?? '');
    $saveCust    = !empty($_POST['save_customer']);

    $err = '';
    if (empty($lines)) $err = 'Add at least one item to the sale.';

    if (!$err) {
        // Re-price from DB (never trust client prices) & validate stock.
        $resolved = [];
        $subtotal = 0.0;
        foreach ($lines as $ln) {
            $pid = (int)($ln['id'] ?? 0);
            $qty = max(1, (int)($ln['qty'] ?? 0));
            if ($pid <= 0) continue;
            $st = $pdo->prepare('SELECT id, name, brand, price, stock FROM products WHERE id = ? AND active = 1');
            $st->execute([$pid]);
            $p = $st->fetch();
            if (!$p) { $err = 'A product is no longer available — please re-scan.'; break; }
            if ($qty > (int)$p['stock']) { $err = 'Not enough stock for ' . $p['name'] . ' (have ' . $p['stock'] . ').'; break; }
            $subtotal += (float)$p['price'] * $qty;
            $resolved[] = ['p' => $p, 'qty' => $qty];
        }
        if (!$err && empty($resolved)) $err = 'Add at least one item to the sale.';

        if (!$err) {
            $discount = min($discount, $subtotal);
            $taxable  = $subtotal - $discount;
            $tax      = $chargeGct ? round($taxable * $TAX, 2) : 0.0;
            $shipping = 0.0;
            if ($fulfil === 'delivery') {
                if ($shipZoneId) {
                    $z = $pdo->prepare("SELECT rate FROM shipping_zones WHERE id = ?");
                    $z->execute([$shipZoneId]); $zr = $z->fetchColumn();
                    $shipping = $zr !== false ? (float)$zr : (function_exists('shipping_for_parish') ? shipping_for_parish($shipParish, $subtotal) : 0.0);
                } elseif (function_exists('shipping_for_parish')) {
                    $shipping = shipping_for_parish($shipParish, $subtotal);
                }
            }
            $total    = $taxable + $tax + $shipping;
            if ($payMethod === 'cash' && $tendered > 0 && $tendered < $total) {
                $err = 'Cash tendered (J$' . number_format($tendered, 2) . ') is less than the total.';
            }

            if (!$err) {
                $pdo->beginTransaction();
                try {
                    $orderNum = generate_order_number();
                    $paid = ($payMethod === 'cash' && $tendered > 0) ? $tendered : $total;
                    $status = $fulfil === 'delivery' ? 'processing' : 'delivered';

                    // Optionally save / link a customer record
                    if ($saveCust && !$custId && $custName !== '' && ($custEmail !== '' || $custPhone !== '')) {
                        $existId = null;
                        if ($custEmail !== '') { $ex = $pdo->prepare("SELECT id FROM users WHERE email = ?"); $ex->execute([$custEmail]); $existId = $ex->fetchColumn() ?: null; }
                        if ($existId) {
                            $pdo->prepare("UPDATE users SET phone = COALESCE(NULLIF(?,''), phone), address = COALESCE(NULLIF(?,''), address) WHERE id = ?")
                                ->execute([$custPhone, $custAddr, $existId]);
                            $custId = (int)$existId;
                        } else {
                            $email = $custEmail !== '' ? $custEmail : ('pos+' . substr(md5($custName . microtime()), 0, 8) . '@tashykollections.org');
                            $pdo->prepare("INSERT INTO users (name, email, phone, address, password_hash, role) VALUES (?,?,?,?,?,?)")
                                ->execute([$custName, $email, $custPhone ?: null, $custAddr ?: null, password_hash(bin2hex(random_bytes(6)), PASSWORD_BCRYPT, ['cost' => 12]), 'customer']);
                            $custId = (int)$pdo->lastInsertId();
                        }
                    }

                    // Compose notes (alt. contact + delivery instructions)
                    $noteParts = [($fulfil === 'delivery' ? 'POS delivery sale' : 'In-store POS sale') . ' by ' . current_user()['name']];
                    if ($contact !== '')       $noteParts[] = 'Contact: ' . $contact;
                    if ($instructions !== '')  $noteParts[] = 'Instructions: ' . $instructions;
                    $notes = implode("\n", $noteParts);
                    $shipAddrFinal = $shipAddr !== '' ? $shipAddr : ($custAddr ?: null);

                    $st = $pdo->prepare('INSERT INTO orders
                        (user_id, order_number, status, subtotal, shipping, tax, discount, total, amount_paid,
                         currency, payment_method, channel, ship_name, ship_email, ship_phone,
                         ship_address, ship_city, ship_parish, ship_country, notes)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $st->execute([
                        $custId, $orderNum, $status,
                        $subtotal, $shipping, $tax, $discount, $total, $paid,
                        defined('CURRENCY') ? CURRENCY : 'JMD', $payMethod, 'pos',
                        $custName, $custEmail, $custPhone,
                        $shipAddrFinal, $shipCity ?: null, $shipParish ?: null, 'Jamaica',
                        $notes,
                    ]);
                    $orderId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO order_status_history (order_id, status, note, created_by) VALUES (?,?,?,?)')
                        ->execute([$orderId, $status, 'POS sale', current_user()['name']]);

                    $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, brand, price, quantity) VALUES (?,?,?,?,?,?)');
                    foreach ($resolved as $r) {
                        $ins->execute([$orderId, $r['p']['id'], $r['p']['name'], $r['p']['brand'], $r['p']['price'], $r['qty']]);
                        $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?')
                            ->execute([$r['qty'], $r['p']['id']]);
                    }
                    $pdo->commit();
                    flash('success', 'Sale completed — ' . $orderNum);
                    redirect(SITE_URL . '/admin/pos.php?receipt=' . $orderId);
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $err = 'Sale failed: ' . $e->getMessage();
                }
            }
        }
    }
    if ($err) flash('error', $err);
    // fall through to render (error shown)
}

/* ── Email the invoice to the customer ───────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_invoice'])) {
    csrf_check();
    $oid = (int)$_POST['order_id'];
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$oid]); $o = $st->fetch();
    if ($o && !empty($o['ship_email']) && filter_var($o['ship_email'], FILTER_VALIDATE_EMAIL)) {
        $it = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?'); $it->execute([$oid]); $oi = $it->fetchAll();
        require_once dirname(__DIR__) . '/includes/mail.php';
        $sent = tk_mail($o['ship_email'], 'Your ' . SITE_NAME . ' invoice ' . $o['order_number'],
            '<p>Hi ' . h($o['ship_name']) . ', here is your invoice.</p>' . tk_order_summary($o, $oi));
        flash($sent ? 'success' : 'error', $sent ? 'Invoice emailed to ' . $o['ship_email'] : 'Could not send the email.');
    } else {
        flash('error', 'No valid customer email on this sale — add one in Orders, or use WhatsApp/Print.');
    }
    redirect(SITE_URL . '/admin/pos.php?receipt=' . $oid);
}

// ── All redirecting POST handlers are done; safe to start output now ──
$pageTitle = 'Point of Sale';
require_once __DIR__ . '/header.php';

/* ─────────────────────────────────────────────────────────────
   Receipt view
   ───────────────────────────────────────────────────────────── */
if ($rid = (int)($_GET['receipt'] ?? 0)) {
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$rid]);
    $o = $st->fetch();
    if ($o) {
        $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $items->execute([$rid]);
        $items = $items->fetchAll();
        $change = ($o['amount_paid'] !== null) ? max(0, (float)$o['amount_paid'] - (float)$o['total']) : 0;
        ?>
        <style>
        @media print { .admin-sidebar,.admin-topbar,.no-print{display:none!important} .admin-main{padding:0!important} body{display:block!important} }
        .receipt { max-width:360px; margin:0 auto; background:#fff; border:1px solid var(--grey-light); border-radius:10px; padding:24px; font-size:0.86rem; }
        .receipt h2 { text-align:center; font-size:1.1rem; }
        .receipt .r-row { display:flex; justify-content:space-between; margin:3px 0; }
        .receipt .r-line { border-top:1px dashed #ccc; margin:10px 0; }
        </style>
        <?php
        $phone = preg_replace('/[^0-9]/', '', $o['ship_phone'] ?? '');
        if ($phone !== '' && strlen($phone) <= 10) $phone = '1' . $phone;
        $sendMsg = rawurlencode('Hi ' . $o['ship_name'] . ', here is your ' . SITE_NAME . ' receipt ' . $o['order_number']
                 . ' — Total J$' . number_format($o['total'], 2) . '. Track it: ' . SITE_URL . '/track.php?order=' . $o['order_number']);
        $waLink  = $phone ? "https://wa.me/{$phone}?text={$sendMsg}" : '';
        $smsLink = $phone ? "sms:+{$phone}?body={$sendMsg}" : '';
        $okm = flash('success'); $errm = flash('error');
        ?>
        <?php if ($okm): ?><div class="no-print" style="background:#d1fae5;color:#065f46;padding:10px 14px;border-radius:8px;margin-bottom:12px"><?= h($okm) ?></div><?php endif; ?>
        <?php if ($errm): ?><div class="no-print badge-danger" style="padding:10px 14px;border-radius:8px;margin-bottom:12px;display:block"><?= h($errm) ?></div><?php endif; ?>
        <div class="no-print" style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap">
          <a href="pos.php" class="btn btn-primary btn-sm">+ New Sale</a>
          <button onclick="window.print()" class="btn btn-outline btn-sm">🖨 Print</button>
          <a href="slip.php?id=<?= (int)$o['id'] ?>&type=invoice" target="_blank" class="btn btn-outline btn-sm">🧾 Invoice</a>
          <a href="slip.php?id=<?= (int)$o['id'] ?>&type=pick" target="_blank" class="btn btn-outline btn-sm">📋 Pick</a>
          <a href="slip.php?id=<?= (int)$o['id'] ?>&type=packing" target="_blank" class="btn btn-outline btn-sm">📦 Packing</a>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="email_invoice" value="1"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">✉ Email</button></form>
          <?php if ($waLink): ?><a href="<?= h($waLink) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">WhatsApp</a><?php endif; ?>
          <?php if ($smsLink): ?><a href="<?= h($smsLink) ?>" class="btn btn-outline btn-sm">SMS</a><?php endif; ?>
          <a href="orders.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm">Order</a>
        </div>
        <div class="receipt">
          <h2><?= SITE_NAME ?></h2>
          <p style="text-align:center;color:#888;font-size:0.78rem"><?= h(SITE_ADDRESS) ?></p>
          <div class="r-line"></div>
          <div class="r-row"><span>Receipt</span><strong><?= h($o['order_number']) ?></strong></div>
          <div class="r-row"><span>Date</span><span><?= date('d M Y, g:ia', strtotime($o['created_at'])) ?></span></div>
          <div class="r-row"><span>Cashier</span><span><?= h(current_user()['name']) ?></span></div>
          <div class="r-row"><span>Customer</span><span><?= h($o['ship_name']) ?></span></div>
          <div class="r-line"></div>
          <?php foreach ($items as $it): ?>
          <div class="r-row"><span><?= h($it['name']) ?> ×<?= $it['quantity'] ?></span><span>J$<?= number_format($it['price'] * $it['quantity'], 2) ?></span></div>
          <?php endforeach; ?>
          <div class="r-line"></div>
          <div class="r-row"><span>Subtotal</span><span>J$<?= number_format($o['subtotal'], 2) ?></span></div>
          <?php if ($o['discount'] > 0): ?><div class="r-row"><span>Discount</span><span>−J$<?= number_format($o['discount'], 2) ?></span></div><?php endif; ?>
          <?php if (($o['shipping'] ?? 0) > 0): ?><div class="r-row"><span>Shipping</span><span>J$<?= number_format($o['shipping'], 2) ?></span></div><?php endif; ?>
          <?php if ($o['tax'] > 0): ?><div class="r-row"><span>GCT (15%)</span><span>J$<?= number_format($o['tax'], 2) ?></span></div><?php endif; ?>
          <div class="r-row" style="font-weight:700;font-size:1rem"><span>TOTAL</span><span>J$<?= number_format($o['total'], 2) ?></span></div>
          <div class="r-line"></div>
          <div class="r-row"><span>Paid (<?= ucfirst($o['payment_method']) ?>)</span><span>J$<?= number_format($o['amount_paid'] ?? $o['total'], 2) ?></span></div>
          <?php if ($change > 0): ?><div class="r-row"><span>Change</span><span>J$<?= number_format($change, 2) ?></span></div><?php endif; ?>
          <div class="r-line"></div>
          <p style="text-align:center;color:#888;font-size:0.78rem">Thank you for shopping with us!</p>
        </div>
        <?php
        require_once __DIR__ . '/footer.php';
        exit;
    }
}

/* ─────────────────────────────────────────────────────────────
   POS register
   ───────────────────────────────────────────────────────────── */
$products = $pdo->query("SELECT id, name, brand, sku, price, stock, image FROM products
                         WHERE active = 1 ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id, name, email, phone, address FROM users WHERE role = 'customer' AND active = 1 ORDER BY name")->fetchAll();
$zones     = function_exists('get_shipping_zones') ? get_shipping_zones(true) : [];
$parishes  = function_exists('jamaica_parishes') ? jamaica_parishes() : [];

$jsProducts = array_map(fn($p) => [
    'id' => (int)$p['id'], 'name' => $p['name'], 'sku' => $p['sku'] ?? '',
    'price' => (float)$p['price'], 'stock' => (int)$p['stock'],
    'img' => product_img($p['image']),
], $products);
$jsCustomers = array_map(fn($c) => [
    'id' => (int)$c['id'], 'name' => $c['name'], 'email' => $c['email'] ?? '', 'phone' => $c['phone'] ?? '', 'address' => $c['address'] ?? '',
], $customers);

$err = flash('error');
?>
<style>
.pos-grid { display:grid; grid-template-columns:1fr 380px; gap:20px; align-items:start; }
.pos-products { background:var(--white); border:1px solid var(--grey-light); border-radius:12px; padding:16px; }
.pos-search { width:100%; margin-bottom:14px; }
.pos-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; max-height:64vh; overflow:auto; }
.pos-card { border:1px solid var(--grey-light); border-radius:10px; padding:10px; cursor:pointer; text-align:left; background:#fff; transition:border-color .12s, box-shadow .12s; }
.pos-card:hover { border-color:var(--rose-gold); box-shadow:0 2px 10px rgba(0,0,0,.06); }
.pos-card.oos { opacity:.45; cursor:not-allowed; }
.pos-card img { width:100%; height:78px; object-fit:cover; border-radius:6px; background:#f4f4f4; }
.pos-card .pc-name { font-size:0.78rem; font-weight:600; margin-top:6px; line-height:1.25; height:2.4em; overflow:hidden; }
.pos-card .pc-meta { display:flex; justify-content:space-between; font-size:0.74rem; margin-top:4px; }
.pos-card .pc-price { font-weight:700; color:var(--rose-gold); }
.pos-ticket { background:var(--white); border:1px solid var(--grey-light); border-radius:12px; padding:18px; position:sticky; top:80px; }
.tk-item { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid var(--grey-light); font-size:0.84rem; }
.tk-item .tk-name { flex:1; min-width:0; }
.tk-qbtn { width:24px; height:24px; border:1px solid var(--grey-light); background:#fff; border-radius:6px; cursor:pointer; font-weight:700; }
.tk-rm { color:#c0392b; cursor:pointer; font-size:0.9rem; background:none; border:none; }
.tk-empty { color:#999; text-align:center; padding:30px 0; font-size:0.85rem; }
.tk-row { display:flex; justify-content:space-between; margin:5px 0; font-size:0.9rem; }
.tk-total { font-weight:700; font-size:1.15rem; }
.pos-field { width:100%; margin:6px 0; }
</style>

<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

<div class="pos-grid">
  <!-- Products -->
  <div class="pos-products">
    <input type="text" id="posSearch" class="form-control pos-search" placeholder="🔍 Search product name or SKU…" autocomplete="off" autofocus>
    <div class="pos-cards" id="posCards"></div>
  </div>

  <!-- Ticket -->
  <div class="pos-ticket">
    <h2 style="font-size:1rem;margin-bottom:10px">Current Sale</h2>

    <select class="form-control pos-field" id="posCustomer">
      <option value="0">🚶 Walk-in Customer</option>
      <?php foreach ($jsCustomers as $c): ?>
      <option value="<?= $c['id'] ?>"><?= h($c['name']) ?><?= $c['phone'] ? ' · '.h($c['phone']) : '' ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" class="form-control pos-field" id="posCustName" placeholder="Customer name">
    <div style="display:flex;gap:6px">
      <input type="text" class="form-control pos-field" id="posCustPhone" placeholder="Telephone">
      <input type="email" class="form-control pos-field" id="posCustEmail" placeholder="Email">
    </div>
    <input type="text" class="form-control pos-field" id="posCustContact" placeholder="Alt. contact (name / number)">
    <input type="text" class="form-control pos-field" id="posCustAddr" placeholder="Customer address">
    <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;cursor:pointer;margin:4px 0"><input type="checkbox" id="posSaveCust"> Save as a customer record</label>

    <div style="display:flex;gap:6px;margin:8px 0">
      <label style="flex:1;text-align:center;border:1px solid var(--grey-light);border-radius:6px;padding:7px;cursor:pointer;font-size:0.82rem"><input type="radio" name="fulfil" value="pickup" checked> 🏬 Pickup</label>
      <label style="flex:1;text-align:center;border:1px solid var(--grey-light);border-radius:6px;padding:7px;cursor:pointer;font-size:0.82rem"><input type="radio" name="fulfil" value="delivery"> 🚚 Delivery</label>
    </div>
    <div id="posDelivery" style="display:none">
      <input type="text" class="form-control pos-field" id="posAddr" placeholder="Delivery address">
      <div style="display:flex;gap:6px">
        <input type="text" class="form-control pos-field" id="posCity" placeholder="City / town">
        <select class="form-control pos-field" id="posParish">
          <option value="">Parish</option>
          <?php foreach ($parishes as $pp): ?><option value="<?= h($pp) ?>"><?= h($pp) ?></option><?php endforeach; ?>
        </select>
      </div>
      <select class="form-control pos-field" id="posZone">
        <option value="0" data-rate="0">Shipping method…</option>
        <?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>" data-rate="<?= h($z['rate']) ?>"><?= h($z['name']) ?> — J$<?= number_format($z['rate'],0) ?></option><?php endforeach; ?>
      </select>
      <textarea class="form-control pos-field" id="posInstr" rows="2" placeholder="Delivery instructions (optional)"></textarea>
    </div>

    <div id="tkItems"><div class="tk-empty">No items yet — tap a product to add it.</div></div>

    <div style="margin-top:12px">
      <div class="tk-row"><span>Subtotal</span><span id="tkSubtotal">J$0.00</span></div>
      <div class="tk-row" id="tkShipRow" style="display:none"><span>Shipping</span><span id="tkShip">J$0.00</span></div>
      <div class="tk-row" style="align-items:center">
        <span>Discount</span>
        <span style="display:flex;gap:4px">
          <select id="posDiscType" class="form-control" style="width:62px;padding:4px 6px">
            <option value="flat">J$</option>
            <option value="pct">%</option>
          </select>
          <input type="number" id="posDiscount" class="form-control" value="0" min="0" step="0.01" style="width:90px;text-align:right">
        </span>
      </div>
      <div class="tk-row" id="tkDiscRow" style="display:none;color:#c0392b"><span>Discount applied</span><span id="tkDisc">−J$0.00</span></div>
      <div class="tk-row">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" id="posGct" checked> GCT (15%)</label>
        <span id="tkTax">J$0.00</span>
      </div>
      <div class="tk-row tk-total" style="border-top:2px solid var(--grey-light);padding-top:8px;margin-top:8px"><span>Total</span><span id="tkTotal">J$0.00</span></div>
    </div>

    <select class="form-control pos-field" id="posPayment" style="margin-top:12px">
      <option value="cash">💵 Cash</option>
      <option value="card">💳 Card</option>
      <option value="transfer">🏦 Bank Transfer</option>
    </select>

    <div id="cashRow">
      <div class="tk-row" style="align-items:center">
        <span>Cash tendered</span>
        <input type="number" id="posTendered" class="form-control" value="" min="0" step="0.01" placeholder="0.00" style="width:120px;text-align:right">
      </div>
      <div class="tk-row" style="font-weight:700"><span>Change</span><span id="tkChange">J$0.00</span></div>
    </div>

    <form method="post" id="saleForm">
      <?= csrf_field() ?>
      <input type="hidden" name="complete_sale" value="1">
      <input type="hidden" name="cart_json" id="cartJson">
      <input type="hidden" name="customer_id" id="fCustId">
      <input type="hidden" name="customer_name" id="fCustName">
      <input type="hidden" name="customer_email" id="fCustEmail">
      <input type="hidden" name="customer_phone" id="fCustPhone">
      <input type="hidden" name="payment_method" id="fPayment">
      <input type="hidden" name="charge_gct" id="fGct">
      <input type="hidden" name="discount" id="fDiscount">
      <input type="hidden" name="amount_paid" id="fTendered">
      <input type="hidden" name="fulfillment" id="fFulfil">
      <input type="hidden" name="ship_address" id="fAddr">
      <input type="hidden" name="ship_city" id="fCity">
      <input type="hidden" name="ship_parish" id="fParish">
      <input type="hidden" name="ship_zone" id="fZone">
      <input type="hidden" name="contact" id="fContact">
      <input type="hidden" name="cust_address" id="fCustAddr">
      <input type="hidden" name="instructions" id="fInstr">
      <input type="hidden" name="save_customer" id="fSaveCust">
      <button type="submit" class="btn btn-primary" id="btnComplete" style="width:100%;margin-top:14px;padding:14px;font-size:1rem" disabled>Complete Sale</button>
    </form>
    <button type="button" class="btn btn-outline btn-sm" id="btnClear" style="width:100%;margin-top:8px">Clear Sale</button>
  </div>
</div>

<script>
const PRODUCTS = <?= json_encode($jsProducts) ?>;
const CUSTOMERS = <?= json_encode($jsCustomers) ?>;
const TAX_RATE = <?= $TAX ?>;
const money = n => 'J$' + (Math.round(n * 100) / 100).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
let cart = []; // {id,name,price,qty,stock}

const cardsEl = document.getElementById('posCards');
function renderCards(filter='') {
  const f = filter.trim().toLowerCase();
  cardsEl.innerHTML = '';
  PRODUCTS.filter(p => !f || p.name.toLowerCase().includes(f) || (p.sku && p.sku.toLowerCase().includes(f)))
    .forEach(p => {
      const oos = p.stock <= 0;
      const d = document.createElement('div');
      d.className = 'pos-card' + (oos ? ' oos' : '');
      d.innerHTML = `<img src="${p.img}" alt="" onerror="this.style.visibility='hidden'">
        <div class="pc-name">${p.name.replace(/</g,'&lt;')}</div>
        <div class="pc-meta"><span class="pc-price">${money(p.price)}</span><span style="color:${oos?'#c0392b':'#888'}">${oos?'Out':('Stk '+p.stock)}</span></div>`;
      if (!oos) d.onclick = () => addItem(p);
      cardsEl.appendChild(d);
    });
}
function addItem(p) {
  const ex = cart.find(c => c.id === p.id);
  if (ex) { if (ex.qty < p.stock) ex.qty++; }
  else cart.push({id:p.id, name:p.name, price:p.price, qty:1, stock:p.stock});
  renderTicket();
}
function setQty(id, q) {
  const c = cart.find(x => x.id === id); if (!c) return;
  c.qty = Math.max(0, Math.min(q, c.stock));
  if (c.qty === 0) cart = cart.filter(x => x.id !== id);
  renderTicket();
}
function renderTicket() {
  const el = document.getElementById('tkItems');
  if (!cart.length) { el.innerHTML = '<div class="tk-empty">No items yet — tap a product to add it.</div>'; }
  else {
    el.innerHTML = cart.map(c => `<div class="tk-item">
        <button class="tk-qbtn" onclick="setQty(${c.id}, ${c.qty-1})">−</button>
        <span style="min-width:20px;text-align:center">${c.qty}</span>
        <button class="tk-qbtn" onclick="setQty(${c.id}, ${c.qty+1})">+</button>
        <span class="tk-name">${c.name.replace(/</g,'&lt;')}<br><small style="color:#888">${money(c.price)} ea</small></span>
        <span style="font-weight:700">${money(c.price*c.qty)}</span>
        <button class="tk-rm" onclick="setQty(${c.id},0)" title="Remove">✕</button>
      </div>`).join('');
  }
  recalc();
}
function posShipping() {
  if (document.querySelector('input[name="fulfil"]:checked').value !== 'delivery') return 0;
  const z = document.getElementById('posZone'); const opt = z.options[z.selectedIndex];
  return opt ? (parseFloat(opt.dataset.rate) || 0) : 0;
}
function recalc() {
  const subtotal = cart.reduce((s,c) => s + c.price*c.qty, 0);
  const dv = parseFloat(document.getElementById('posDiscount').value) || 0;
  const isPct = document.getElementById('posDiscType').value === 'pct';
  let discount = isPct ? subtotal * Math.min(100, Math.max(0, dv)) / 100 : dv;
  discount = Math.max(0, Math.min(discount, subtotal));
  const discRow = document.getElementById('tkDiscRow');
  if (discount > 0) { discRow.style.display = ''; document.getElementById('tkDisc').textContent = '−' + money(discount); }
  else discRow.style.display = 'none';
  const ship = posShipping();
  const shipRow = document.getElementById('tkShipRow');
  if (ship > 0) { shipRow.style.display = ''; document.getElementById('tkShip').textContent = money(ship); }
  else shipRow.style.display = 'none';
  const gct = document.getElementById('posGct').checked;
  const tax = gct ? (subtotal - discount) * TAX_RATE : 0;
  const total = subtotal - discount + tax + ship;
  document.getElementById('tkSubtotal').textContent = money(subtotal);
  document.getElementById('tkTax').textContent = money(tax);
  document.getElementById('tkTotal').textContent = money(total);
  const tendered = parseFloat(document.getElementById('posTendered').value) || 0;
  document.getElementById('tkChange').textContent = money(Math.max(0, tendered - total));
  document.getElementById('btnComplete').disabled = cart.length === 0;
  return {subtotal, discount, tax, ship, total, tendered};
}
// Search filter + barcode-scanner: scan a SKU then Enter to add the matching product.
const posSearch = document.getElementById('posSearch');
posSearch.addEventListener('input', e => renderCards(e.target.value));
posSearch.addEventListener('keydown', e => {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const q = e.target.value.trim().toLowerCase();
  if (!q) return;
  let prod = PRODUCTS.find(p => (p.sku || '').toLowerCase() === q);
  if (!prod) {
    const m = PRODUCTS.filter(p => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)));
    if (m.length === 1) prod = m[0];
  }
  if (prod && prod.stock > 0) { addItem(prod); e.target.value = ''; renderCards(''); }
  e.target.focus();
});
document.getElementById('posDiscType').addEventListener('change', recalc);
['posDiscount','posGct','posTendered'].forEach(id => document.getElementById(id).addEventListener('input', recalc));
function syncFulfil(){ document.getElementById('posDelivery').style.display = document.querySelector('input[name="fulfil"]:checked').value === 'delivery' ? '' : 'none'; recalc(); }
document.querySelectorAll('input[name="fulfil"]').forEach(r => r.addEventListener('change', syncFulfil));
document.getElementById('posZone').addEventListener('change', recalc);
document.getElementById('posCustomer').addEventListener('change', function(){
  const c = CUSTOMERS.find(x => x.id == this.value);
  if (c) { document.getElementById('posCustName').value = c.name || ''; document.getElementById('posCustPhone').value = c.phone || ''; document.getElementById('posCustEmail').value = c.email || ''; document.getElementById('posCustAddr').value = c.address || ''; }
});
document.getElementById('posPayment').addEventListener('change', e => {
  document.getElementById('cashRow').style.display = e.target.value === 'cash' ? '' : 'none';
});
document.getElementById('btnClear').onclick = () => { cart = []; document.getElementById('posDiscount').value = 0; document.getElementById('posTendered').value = ''; renderTicket(); };

document.getElementById('saleForm').addEventListener('submit', e => {
  const t = recalc();
  if (!cart.length) { e.preventDefault(); return; }
  const pay = document.getElementById('posPayment').value;
  if (pay === 'cash' && t.tendered > 0 && t.tendered < t.total) {
    if (!confirm('Cash tendered is less than the total. Continue anyway?')) { e.preventDefault(); return; }
  }
  const cust = CUSTOMERS.find(c => c.id == document.getElementById('posCustomer').value);
  const typedName  = document.getElementById('posCustName').value.trim();
  const typedPhone = document.getElementById('posCustPhone').value.trim();
  const typedEmail = document.getElementById('posCustEmail').value.trim();
  const fulfil = document.querySelector('input[name="fulfil"]:checked').value;
  document.getElementById('cartJson').value = JSON.stringify(cart.map(c => ({id:c.id, qty:c.qty})));
  document.getElementById('fCustId').value = cust ? cust.id : 0;
  document.getElementById('fCustName').value = typedName || (cust ? cust.name : 'Walk-in Customer');
  document.getElementById('fCustEmail').value = typedEmail || (cust ? cust.email : '');
  document.getElementById('fCustPhone').value = typedPhone || (cust ? cust.phone : '');
  document.getElementById('fPayment').value = pay;
  document.getElementById('fGct').value = document.getElementById('posGct').checked ? '1' : '';
  document.getElementById('fDiscount').value = t.discount;
  document.getElementById('fTendered').value = t.tendered;
  document.getElementById('fFulfil').value = fulfil;
  document.getElementById('fAddr').value = document.getElementById('posAddr').value.trim();
  document.getElementById('fCity').value = document.getElementById('posCity').value.trim();
  document.getElementById('fParish').value = document.getElementById('posParish').value;
  document.getElementById('fZone').value = document.getElementById('posZone').value;
  document.getElementById('fContact').value = document.getElementById('posCustContact').value.trim();
  document.getElementById('fCustAddr').value = document.getElementById('posCustAddr').value.trim();
  document.getElementById('fInstr').value = document.getElementById('posInstr').value.trim();
  document.getElementById('fSaveCust').value = document.getElementById('posSaveCust').checked ? '1' : '';
  document.getElementById('btnComplete').disabled = true;
  document.getElementById('btnComplete').textContent = 'Processing…';
});

renderCards();
renderTicket();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
