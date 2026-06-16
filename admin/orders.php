<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/header.php';
require_once dirname(__DIR__) . '/includes/orders.php';

$pdo    = db();
$viewId = (int)($_GET['id'] ?? 0);

$statuses     = order_statuses();
$statusColors = order_status_colors();

/* ── Update order status (logs history + emails customer) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    csrf_check();
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'] ?? '';
    $note   = trim($_POST['status_note'] ?? '');
    if (record_order_status($oid, $status, $note, current_user()['name'])) {
        flash('success', 'Status updated — customer notified.');
    }
    redirect(asset_base() . '/admin/orders.php' . ($viewId ? "?id=$oid" : ''));
}

/* ── Tag order for follow-up ───────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tag_order'])) {
    csrf_check();
    $oid = (int)$_POST['order_id'];
    $ch  = in_array($_POST['followup_channel'] ?? '', ['chat','email','whatsapp','call',''], true) ? ($_POST['followup_channel'] ?? '') : '';
    $nt  = trim($_POST['followup_note'] ?? '');
    $pdo->prepare("UPDATE orders SET followup_channel=?, followup_note=? WHERE id=?")
        ->execute([$ch !== '' ? $ch : null, $nt !== '' ? $nt : null, $oid]);
    flash('success', $ch ? 'Order tagged for follow-up.' : 'Follow-up tag cleared.');
    redirect(asset_base() . '/admin/orders.php?id=' . $oid);
}

/* ── Record a payment ──────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    csrf_check();
    $oid    = (int)$_POST['order_id'];
    $amt    = max(0, (float)($_POST['amount'] ?? 0));
    $method = in_array($_POST['method'] ?? '', ['cash','card','transfer','paypal','cod'], true) ? $_POST['method'] : 'cash';
    $cur = $pdo->prepare('SELECT status FROM orders WHERE id=?'); $cur->execute([$oid]); $cst = $cur->fetchColumn();
    if ($cst !== false) {
        $pdo->prepare('UPDATE orders SET amount_paid=?, payment_method=? WHERE id=?')->execute([$amt, $method, $oid]);
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, note, created_by) VALUES (?,?,?,?)')
            ->execute([$oid, $cst, 'Payment recorded: J$' . number_format($amt, 2) . ' (' . $method . ')', current_user()['name']]);
        flash('success', 'Payment recorded.');
    }
    redirect(asset_base() . '/admin/orders.php' . ($viewId ? '?id=' . $oid : ''));
}

/* ── Cancel transaction (restocks) ─────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    csrf_check();
    $oid = (int)$_POST['order_id'];
    $cur = $pdo->prepare('SELECT status FROM orders WHERE id=?'); $cur->execute([$oid]); $cst = $cur->fetchColumn();
    if ($cst !== false && $cst !== 'cancelled') {
        $its = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id=? AND product_id IS NOT NULL');
        $its->execute([$oid]);
        foreach ($its->fetchAll() as $it) {
            $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')->execute([(int)$it['quantity'], (int)$it['product_id']]);
        }
        record_order_status($oid, 'cancelled', trim($_POST['cancel_note'] ?? '') ?: 'Cancelled by staff', current_user()['name']);
        flash('success', 'Order cancelled and stock restored.');
    } else {
        flash('error', 'Order is already cancelled.');
    }
    redirect(asset_base() . '/admin/orders.php' . ($viewId ? '?id=' . $oid : ''));
}

/* ── Single order view ─────────────────────── */
if ($viewId) {
    $stmt = $pdo->prepare("SELECT o.*, u.name as uname, u.email as uemail FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.id=?");
    $stmt->execute([$viewId]);
    $order = $stmt->fetch();
    if (!$order) { flash('error', 'Order not found.'); redirect(asset_base() . '/admin/orders.php'); }

    $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
    $items->execute([$viewId]);
    $items = $items->fetchAll();
    $history = get_order_history($viewId);
    $links   = order_contact_links($order);
    ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
      <a href="orders.php" style="color:var(--rose-gold)">&larr; Back to Orders</a>
      <span style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
        <a href="slip.php?id=<?= (int)$order['id'] ?>&type=receipt" target="_blank" class="btn btn-outline btn-sm">🧾 Receipt</a>
        <a href="slip.php?id=<?= (int)$order['id'] ?>&type=receipt_text" target="_blank" class="btn btn-outline btn-sm">🖨 Text receipt</a>
        <a href="slip.php?id=<?= (int)$order['id'] ?>&type=invoice" target="_blank" class="btn btn-outline btn-sm">📄 Invoice</a>
        <a href="slip.php?id=<?= (int)$order['id'] ?>&type=label" target="_blank" class="btn btn-outline btn-sm">🏷 Label</a>
        <a href="slip.php?id=<?= (int)$order['id'] ?>&type=pick" target="_blank" class="btn btn-outline btn-sm">📋 Pick slip</a>
        <a href="slip.php?id=<?= (int)$order['id'] ?>&type=packing" target="_blank" class="btn btn-outline btn-sm">📦 Packing slip</a>
        <a href="returns.php?action=new&order_id=<?= (int)$order['id'] ?>" class="btn btn-outline btn-sm">↩️ Return</a>
      </span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 280px;gap:24px;align-items:start">
      <div>
        <div class="admin-card">
          <h2>Order <?= h($order['order_number']) ?></h2>
          <p style="color:#888;margin-bottom:16px;font-size:0.85rem">Placed <?= date('d M Y, g:ia', strtotime($order['created_at'])) ?></p>

          <table class="admin-table">
            <thead><tr><th>Product</th><th>Brand</th><th>Qty</th><th style="text-align:right">Line Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
              <td style="font-weight:600"><?= h($it['name']) ?></td>
              <td style="color:#888"><?= h($it['brand']) ?></td>
              <td>×<?= $it['quantity'] ?></td>
              <td style="text-align:right;font-weight:600"><?= money($it['price'] * $it['quantity']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <div style="border-top:2px solid var(--grey-light);margin-top:12px;padding-top:12px;max-width:220px;margin-left:auto;font-size:0.88rem">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
            <?php if (($order['discount'] ?? 0) > 0): ?><div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>Discount</span><span>−<?= money($order['discount']) ?></span></div><?php endif; ?>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>Shipping</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'FREE' ?></span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>GCT</span><span><?= money($order['tax']) ?></span></div>
            <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem"><span>Total</span><span><?= money($order['total']) ?></span></div>
          </div>
        </div>

        <div class="admin-card">
          <h2>Status History</h2>
          <?php foreach (array_reverse($history) as $hh): ?>
          <div style="display:flex;gap:8px;margin-bottom:8px;font-size:0.84rem">
            <span style="color:var(--rose-gold)">●</span>
            <div><strong><?= ucfirst(h($hh['status'])) ?></strong>
              <span style="color:#999;font-size:0.76rem">· <?= date('d M y, g:ia', strtotime($hh['created_at'])) ?><?= $hh['created_by'] ? ' · ' . h($hh['created_by']) : '' ?></span>
              <?php if (!empty($hh['note']) && $hh['note'] !== 'Imported'): ?><div style="color:#888;font-size:0.8rem"><?= h($hh['note']) ?></div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <div class="admin-card" style="margin-bottom:16px">
          <h2>Update Status</h2>
          <?php $ok = flash('success'); if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:0.82rem"><?= h($ok) ?></div><?php endif; ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            <select name="status" class="form-control" style="margin-bottom:8px">
              <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
            <?php $orderPresets = get_preset_messages('order'); if (!empty($orderPresets)): ?>
            <select id="orderPreset" class="form-control" style="margin-bottom:8px">
              <option value="">Preset message…</option>
              <?php foreach ($orderPresets as $pm): ?><option value="<?= h($pm['body']) ?>"><?= h($pm['title']) ?></option><?php endforeach; ?>
            </select>
            <?php endif; ?>
            <input type="text" name="status_note" id="statusNote" class="form-control" placeholder="Note (optional, e.g. tracking #)" style="margin-bottom:8px">
            <button type="submit" class="btn btn-primary btn-sm" style="width:100%">Update &amp; notify</button>
            <a href="<?= asset_base() ?>/track.php?order=<?= urlencode($order['order_number']) ?>" target="_blank" style="display:block;text-align:center;margin-top:8px;font-size:0.78rem;color:var(--rose-gold)">View public tracking ↗</a>
          </form>
          <?php if (!empty($orderPresets)): ?>
          <script>
          (function(){
            var sel = document.getElementById('orderPreset'), note = document.getElementById('statusNote'); if (!sel) return;
            var vars = { name: <?= json_encode($order['ship_name'] ?? '') ?>, order: <?= json_encode($order['order_number']) ?>,
                         total: 'J$' + <?= json_encode(number_format((float)$order['total'], 2)) ?>, store: <?= json_encode(SITE_NAME) ?> };
            sel.addEventListener('change', function(){
              if (!sel.value) return;
              note.value = sel.value.replace(/\{(\w+)\}/g, function(_,k){ return vars[k] != null ? vars[k] : ('{'+k+'}'); });
            });
          })();
          </script>
          <?php endif; ?>
        </div>

        <div class="admin-card">
          <h2>Customer</h2>
          <p style="font-size:0.85rem;font-weight:600"><?= h($order['ship_name']) ?></p>
          <p style="font-size:0.82rem;color:#888;margin-top:4px"><?= h($order['ship_email']) ?></p>
          <p style="font-size:0.82rem;color:#888"><?= h($order['ship_phone']) ?></p>
          <p style="font-size:0.82rem;margin-top:10px"><?= h($order['ship_address']) ?></p>
          <p style="font-size:0.82rem"><?= h($order['ship_city']) ?>, <?= h($order['ship_parish']) ?></p>
          <?php if ($order['notes']): ?>
          <p style="font-size:0.8rem;margin-top:10px;color:#888"><em>Note: <?= h($order['notes']) ?></em></p>
          <?php endif; ?>
          <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
            <?php if (!empty($links['whatsapp'])): ?><a href="<?= h($links['whatsapp']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">WhatsApp</a><?php endif; ?>
            <?php if (!empty($links['email'])): ?><a href="<?= h($links['email']) ?>" class="btn btn-outline btn-sm">Email</a><?php endif; ?>
          </div>
        </div>

        <div class="admin-card" style="margin-top:16px">
          <h2>Follow-up Tag <?php if (!empty($order['followup_channel'])): ?><span style="font-size:0.9rem">🏷️</span><?php endif; ?></h2>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="tag_order" value="1">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            <select name="followup_channel" class="form-control" style="margin-bottom:8px">
              <?php foreach (['' => '— none —','chat'=>'💬 Chat','email'=>'✉ Email','whatsapp'=>'WhatsApp','call'=>'📞 Call'] as $cv=>$cl): ?>
              <option value="<?= $cv ?>" <?= ($order['followup_channel'] ?? '')===$cv?'selected':'' ?>><?= h($cl) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="followup_note" class="form-control" placeholder="Follow-up note" value="<?= h($order['followup_note'] ?? '') ?>" style="margin-bottom:8px">
            <button type="submit" class="btn btn-outline btn-sm" style="width:100%">Save Tag</button>
          </form>
        </div>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── List orders ───────────────────────────── */
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$where = []; $params = [];
if ($statusFilter) { $where[] = "o.status=?"; $params[] = $statusFilter; }
if ($search)       { $where[] = "(o.order_number LIKE ? OR o.ship_name LIKE ? OR o.ship_email LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orders = $pdo->prepare("SELECT o.*, u.name as uname FROM orders o LEFT JOIN users u ON o.user_id=u.id $whereSQL ORDER BY o.created_at DESC LIMIT 200");
$orders->execute($params);
$orders = $orders->fetchAll();

$ok = flash('success');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px"><?= h($ok) ?></div><?php endif; ?>

<div style="display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
  <form method="get" style="display:flex;gap:8px">
    <input type="text" name="q" placeholder="Order #, name, email…" class="form-control" value="<?= h($search) ?>" style="width:220px">
    <select name="status" class="form-control">
      <option value="">All Statuses</option>
      <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    <?php if ($statusFilter || $search): ?><a href="orders.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
  <span style="color:#888;font-size:0.82rem;margin-left:auto"><?= count($orders) ?> order(s)</span>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr>
      <th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td style="font-weight:600"><?= h($o['order_number']) ?></td>
      <td>
        <div><?= h($o['uname'] ?? $o['ship_name']) ?></div>
        <div style="font-size:0.75rem;color:#888"><?= h($o['ship_email']) ?></div>
      </td>
      <td style="font-weight:600"><?= money($o['total']) ?></td>
      <td><span class="badge badge-<?= $statusColors[$o['status']] ?? 'grey' ?>"><?= ucfirst($o['status']) ?></span></td>
      <td style="font-size:0.8rem;color:#888">
        <?= strtoupper(h($o['payment_method'] ?? 'cod')) ?>
        <?php if (($o['channel'] ?? 'online') === 'pos'): ?><span class="badge badge-info" style="margin-left:4px">POS</span><?php endif; ?>
      </td>
      <td style="color:#888;font-size:0.8rem"><?= date('d M y', strtotime($o['created_at'])) ?></td>
      <td style="white-space:nowrap">
        <a href="orders.php?id=<?= $o['id'] ?>" style="color:var(--rose-gold);font-size:0.8rem;margin-right:6px">View</a>
        <a href="slip.php?id=<?= (int)$o['id'] ?>&type=receipt" target="_blank" style="color:var(--rose-gold);font-size:0.8rem;margin-right:6px" title="Reprint receipt">🧾</a>
        <button type="button" class="lnk-pay" data-id="<?= (int)$o['id'] ?>" data-num="<?= h($o['order_number']) ?>" data-total="<?= h(number_format((float)$o['total'],2,'.','')) ?>" data-method="<?= h($o['payment_method'] ?? 'cash') ?>" style="background:none;border:none;color:var(--rose-gold);font-size:0.8rem;cursor:pointer;margin-right:6px">Pay</button>
        <?php if ($o['status'] !== 'cancelled'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Cancel order <?= h($o['order_number']) ?> and restore stock?')">
          <?= csrf_field() ?><input type="hidden" name="cancel_order" value="1"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <button type="submit" style="background:none;border:none;color:#c0392b;font-size:0.8rem;cursor:pointer">Cancel</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?><tr><td colspan="7" style="text-align:center;color:#999;padding:28px">No orders found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Record payment modal -->
<div id="payModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:12px;max-width:380px;width:100%;padding:22px">
    <h3 style="margin-bottom:4px">Record Payment</h3>
    <p style="font-size:0.82rem;color:#888;margin-bottom:14px">Order <span id="payNum"></span></p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="record_payment" value="1">
      <input type="hidden" name="order_id" id="payOrderId">
      <div class="form-group"><label class="form-label">Amount (J$)</label>
        <input type="number" step="0.01" min="0" name="amount" id="payAmount" class="form-control"></div>
      <div class="form-group"><label class="form-label">Method</label>
        <select name="method" id="payMethod" class="form-control">
          <option value="cash">Cash</option><option value="card">Card</option>
          <option value="transfer">Bank Transfer</option><option value="paypal">PayPal</option>
          <option value="cod">Cash on Delivery</option>
        </select></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-outline" id="payCancel">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Payment</button>
      </div>
    </form>
  </div>
</div>
<script>
(function(){
  const modal = document.getElementById('payModal');
  document.querySelectorAll('.lnk-pay').forEach(b => b.addEventListener('click', () => {
    document.getElementById('payOrderId').value = b.dataset.id;
    document.getElementById('payNum').textContent = b.dataset.num;
    document.getElementById('payAmount').value = b.dataset.total;
    const m = document.getElementById('payMethod'); m.value = b.dataset.method || 'cash';
    modal.style.display = 'flex';
  }));
  document.getElementById('payCancel').addEventListener('click', () => modal.style.display='none');
  modal.addEventListener('click', e => { if (e.target === modal) modal.style.display='none'; });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
