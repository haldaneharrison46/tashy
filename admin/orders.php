<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/header.php';

$pdo    = db();
$viewId = (int)($_GET['id'] ?? 0);

$statuses = ['pending','processing','shipped','delivered','cancelled'];
$statusColors = ['pending'=>'warning','processing'=>'info','shipped'=>'info','delivered'=>'success','cancelled'=>'danger'];

/* ── Update order status ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    csrf_check();
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'] ?? '';
    if (in_array($status, $statuses)) {
        $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status, $oid]);
        flash('success', 'Order status updated.');
    }
    redirect(SITE_URL . '/admin/orders.php' . ($viewId ? "?id=$oid" : ''));
}

/* ── Single order view ─────────────────────── */
if ($viewId) {
    $stmt = $pdo->prepare("SELECT o.*, u.name as uname, u.email as uemail FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.id=?");
    $stmt->execute([$viewId]);
    $order = $stmt->fetch();
    if (!$order) { flash('error', 'Order not found.'); redirect(SITE_URL . '/admin/orders.php'); }

    $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
    $items->execute([$viewId]);
    $items = $items->fetchAll();
    ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <a href="orders.php" style="color:var(--rose-gold)">&larr; Back to Orders</a>
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
            <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>Shipping</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'FREE' ?></span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>GCT</span><span><?= money($order['tax']) ?></span></div>
            <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem"><span>Total</span><span><?= money($order['total']) ?></span></div>
          </div>
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
            <select name="status" class="form-control" style="margin-bottom:10px">
              <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm" style="width:100%">Update</button>
          </form>
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
      <td style="font-size:0.8rem;color:#888">COD</td>
      <td style="color:#888;font-size:0.8rem"><?= date('d M y', strtotime($o['created_at'])) ?></td>
      <td><a href="orders.php?id=<?= $o['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem">View →</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?><tr><td colspan="7" style="text-align:center;color:#999;padding:28px">No orders found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
