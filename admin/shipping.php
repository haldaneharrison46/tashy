<?php
$pageTitle = 'Shipping';
require_once __DIR__ . '/header.php';
require_once dirname(__DIR__) . '/includes/orders.php';

$pdo      = db();
$action   = $_GET['action'] ?? '';
$editId   = (int)($_GET['id'] ?? 0);
$tab      = $_GET['tab'] ?? 'shipments';
$parishes = jamaica_parishes();
$hasSched = tk_column_exists('orders', 'ship_date');

/* ── Save shipment (details, schedule, tracking, status) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shipment'])) {
    csrf_check();
    $oid = (int)$_POST['order_id'];
    $pdo->prepare('UPDATE orders SET ship_name=?, ship_phone=?, ship_email=?, ship_address=?, ship_city=?, ship_parish=?, ship_country=? WHERE id=?')
        ->execute([
            trim($_POST['ship_name'] ?? ''), trim($_POST['ship_phone'] ?? ''), trim($_POST['ship_email'] ?? ''),
            trim($_POST['ship_address'] ?? ''), trim($_POST['ship_city'] ?? ''), trim($_POST['ship_parish'] ?? ''),
            trim($_POST['ship_country'] ?? 'Jamaica') ?: 'Jamaica', $oid,
        ]);
    if ($hasSched) {
        $sd = trim($_POST['ship_date'] ?? '');
        $pdo->prepare('UPDATE orders SET ship_date=?, tracking_number=?, carrier=? WHERE id=?')
            ->execute([$sd !== '' ? $sd : null, trim($_POST['tracking_number'] ?? '') ?: null, trim($_POST['carrier'] ?? '') ?: null, $oid]);
    }
    $status = $_POST['status'] ?? '';
    if (in_array($status, order_statuses(), true)) {
        record_order_status($oid, $status, trim($_POST['status_note'] ?? ''), current_user()['name']);
    }
    if (!empty($_POST['notify_customer'])) { try { notify_shipping($oid); } catch (Throwable $e) {} }
    flash('success', 'Shipment updated' . (!empty($_POST['notify_customer']) ? ' — customer notified.' : '.'));
    redirect(asset_base() . '/admin/shipping.php?tab=shipments');
}

/* ── Send shipping notification ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_shipment'])) {
    csrf_check();
    $oid  = (int)$_POST['order_id'];
    $sent = false; try { $sent = notify_shipping($oid); } catch (Throwable $e) {}
    flash($sent ? 'success' : 'error', $sent ? 'Shipping notification sent.' : 'Could not send (no valid customer email?).');
    redirect(asset_base() . '/admin/shipping.php?tab=shipments');
}

/* ── Save global settings ──────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_check();
    set_setting('default_shipping_rate',  (string)max(0, (float)($_POST['default_shipping_rate'] ?? 0)));
    set_setting('free_shipping_threshold',(string)max(0, (float)($_POST['free_shipping_threshold'] ?? 0)));
    flash('success', 'Shipping settings saved.');
    redirect(asset_base() . '/admin/shipping.php');
}

/* ── Save zone (add / edit) ────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_zone'])) {
    csrf_check();
    $id      = (int)($_POST['zone_id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $picked  = array_values(array_intersect($parishes, (array)($_POST['parishes'] ?? [])));
    $plist   = implode(',', $picked);
    $rate    = max(0, (float)($_POST['rate'] ?? 0));
    $freeRaw = trim($_POST['free_threshold'] ?? '');
    $free    = $freeRaw === '' ? null : max(0, (float)$freeRaw);
    $active  = isset($_POST['active']) ? 1 : 0;
    $sort    = (int)($_POST['sort_order'] ?? 0);

    if ($name === '' || $plist === '') {
        flash('error', 'Zone name and at least one parish are required.');
        redirect(asset_base() . '/admin/shipping.php?action=' . ($id ? 'edit&id=' . $id : 'add'));
    }
    if ($id) {
        $pdo->prepare('UPDATE shipping_zones SET name=?, parishes=?, rate=?, free_threshold=?, active=?, sort_order=? WHERE id=?')
            ->execute([$name, $plist, $rate, $free, $active, $sort, $id]);
        flash('success', 'Zone updated.');
    } else {
        $pdo->prepare('INSERT INTO shipping_zones (name, parishes, rate, free_threshold, active, sort_order) VALUES (?,?,?,?,?,?)')
            ->execute([$name, $plist, $rate, $free, $active, $sort]);
        flash('success', 'Zone added.');
    }
    redirect(asset_base() . '/admin/shipping.php');
}

/* ── Delete zone ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_zone'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM shipping_zones WHERE id=?')->execute([(int)$_POST['zone_id']]);
    flash('success', 'Zone deleted.');
    redirect(asset_base() . '/admin/shipping.php');
}

/* ── Zone add / edit form ──────────────────── */
$editZone = null;
if ($action === 'edit' && $editId) {
    $st = $pdo->prepare('SELECT * FROM shipping_zones WHERE id=?');
    $st->execute([$editId]);
    $editZone = $st->fetch();
}
if ($action === 'add' || $editZone) {
    $z = $editZone ?? ['id'=>0,'name'=>'','parishes'=>'','rate'=>'','free_threshold'=>'','active'=>1,'sort_order'=>0];
    $picked = array_map('trim', explode(',', $z['parishes']));
    $err = flash('error');
    ?>
    <div style="margin-bottom:18px"><a href="shipping.php" style="color:var(--rose-gold)">&larr; Back to Shipping</a></div>
    <?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>
    <div class="admin-card" style="max-width:640px">
      <h2><?= $editZone ? 'Edit Shipping Zone' : 'Add Shipping Zone' ?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_zone" value="1">
        <input type="hidden" name="zone_id" value="<?= (int)$z['id'] ?>">
        <div class="admin-form-grid">
          <div class="form-group full"><label class="form-label">Zone Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= h($z['name']) ?>" placeholder="e.g. Western Jamaica"></div>
          <div class="form-group"><label class="form-label">Shipping Rate (J$) *</label>
            <input type="number" name="rate" class="form-control" step="0.01" min="0" required value="<?= h($z['rate']) ?>"></div>
          <div class="form-group"><label class="form-label">Free Over (J$, optional)</label>
            <input type="number" name="free_threshold" class="form-control" step="0.01" min="0" value="<?= h($z['free_threshold'] ?? '') ?>" placeholder="blank = use global"></div>
          <div class="form-group"><label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= (int)$z['sort_order'] ?>"></div>
          <div class="form-group" style="display:flex;align-items:flex-end">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="active" value="1" <?= $z['active'] ? 'checked' : '' ?>> Active
            </label>
          </div>
          <div class="form-group full">
            <label class="form-label">Parishes in this zone *</label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:6px">
              <?php foreach ($parishes as $p): ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;cursor:pointer">
                <input type="checkbox" name="parishes[]" value="<?= h($p) ?>" <?= in_array($p, $picked, true) ? 'checked' : '' ?>> <?= h($p) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:18px">
          <button type="submit" class="btn btn-primary"><?= $editZone ? 'Save Zone' : 'Add Zone' ?></button>
          <a href="shipping.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── Shipment edit form ────────────────────── */
if ($action === 'ship' && $editId) {
    $st = $pdo->prepare('SELECT * FROM orders WHERE id=?'); $st->execute([$editId]); $o = $st->fetch();
    if (!$o) { flash('error', 'Order not found.'); redirect(asset_base() . '/admin/shipping.php?tab=shipments'); }
    $err = flash('error');
    ?>
    <div style="margin-bottom:18px"><a href="shipping.php?tab=shipments" style="color:var(--rose-gold)">&larr; Back to Shipments</a></div>
    <?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>
    <?php if (!$hasSched): ?><div class="badge-warning" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;display:block">⚠ Scheduling/tracking columns aren't installed yet — run <a href="settings.php#data" style="color:inherit;text-decoration:underline">Database updates</a> to enable them.</div><?php endif; ?>
    <div style="display:flex;gap:22px;align-items:flex-start;flex-wrap:wrap">
      <div class="admin-card" style="flex:1;min-width:340px;max-width:560px">
        <h2>Shipment — <?= h($o['order_number']) ?></h2>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="save_shipment" value="1">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <div class="admin-form-grid">
            <div class="form-group full"><label class="form-label">Recipient name</label>
              <input type="text" name="ship_name" class="form-control" value="<?= h($o['ship_name'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Phone</label>
              <input type="text" name="ship_phone" class="form-control" value="<?= h($o['ship_phone'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Email</label>
              <input type="text" name="ship_email" class="form-control" value="<?= h($o['ship_email'] ?? '') ?>"></div>
            <div class="form-group full"><label class="form-label">Address</label>
              <input type="text" name="ship_address" class="form-control" value="<?= h($o['ship_address'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">City / Community</label>
              <input type="text" name="ship_city" class="form-control" value="<?= h($o['ship_city'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Parish</label>
              <select name="ship_parish" class="form-control">
                <option value="">—</option>
                <?php foreach ($parishes as $pp): ?><option value="<?= h($pp) ?>" <?= ($o['ship_parish'] ?? '')===$pp?'selected':'' ?>><?= h($pp) ?></option><?php endforeach; ?>
              </select></div>
            <div class="form-group full"><label class="form-label">Country</label>
              <input type="text" name="ship_country" class="form-control" value="<?= h($o['ship_country'] ?? 'Jamaica') ?>"></div>
            <div class="form-group"><label class="form-label">Scheduled delivery date</label>
              <input type="date" name="ship_date" class="form-control" value="<?= h($o['ship_date'] ?? '') ?>" <?= $hasSched ? '' : 'disabled' ?>></div>
            <div class="form-group"><label class="form-label">Carrier</label>
              <input type="text" name="carrier" class="form-control" value="<?= h($o['carrier'] ?? '') ?>" placeholder="e.g. Knutsford / ZIP" <?= $hasSched ? '' : 'disabled' ?>></div>
            <div class="form-group full"><label class="form-label">Tracking number</label>
              <input type="text" name="tracking_number" class="form-control" value="<?= h($o['tracking_number'] ?? '') ?>" <?= $hasSched ? '' : 'disabled' ?>></div>
            <div class="form-group"><label class="form-label">Shipping status</label>
              <select name="status" class="form-control">
                <?php foreach (order_statuses() as $s): ?><option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
              </select></div>
            <div class="form-group"><label class="form-label">Status note (optional)</label>
              <input type="text" name="status_note" class="form-control" placeholder="e.g. left with neighbour"></div>
            <div class="form-group full"><label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="notify_customer" value="1"> Email the customer these delivery details</label></div>
          </div>
          <div style="display:flex;gap:10px;margin-top:18px">
            <button type="submit" class="btn btn-primary">Save Shipment</button>
            <a href="shipping.php?tab=shipments" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </div>
      <div class="admin-card" style="width:240px">
        <h2>Quick actions</h2>
        <div style="display:flex;flex-direction:column;gap:8px">
          <a href="slip.php?id=<?= (int)$o['id'] ?>&type=label" target="_blank" class="btn btn-outline btn-sm">🏷 Shipping label</a>
          <a href="slip.php?id=<?= (int)$o['id'] ?>&type=packing" target="_blank" class="btn btn-outline btn-sm">📦 Packing slip</a>
          <a href="orders.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm">Open order</a>
          <a href="<?= asset_base() ?>/track.php?order=<?= urlencode($o['order_number']) ?>" target="_blank" class="btn btn-outline btn-sm">Public tracking ↗</a>
          <form method="post" onsubmit="return confirm('Send a delivery-details email to the customer?')">
            <?= csrf_field() ?><input type="hidden" name="notify_shipment" value="1"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm" style="width:100%">✉ Send notification</button>
          </form>
        </div>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── Overview ──────────────────────────────── */
$zones = get_shipping_zones(false);
$defRate = shipping_default_rate();
$freeThr = free_shipping_threshold();
$ok = flash('success'); $err = flash('error');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:20px">
  <a href="shipping.php?tab=shipments" class="btn <?= $tab!=='zones' ? 'btn-primary' : 'btn-outline' ?> btn-sm">📦 Shipments</a>
  <a href="shipping.php?tab=zones" class="btn <?= $tab==='zones' ? 'btn-primary' : 'btn-outline' ?> btn-sm">🚚 Zones &amp; Rates</a>
</div>

<?php if ($tab === 'zones'): ?>
<div class="admin-card" style="max-width:640px">
  <h2>Global Shipping Settings</h2>
  <p style="color:#888;font-size:0.84rem;margin-bottom:14px">Used when an order's parish doesn't match any active zone below, or as the default free-shipping threshold.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="save_settings" value="1">
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Default Rate (J$)</label>
        <input type="number" name="default_shipping_rate" class="form-control" step="0.01" min="0" value="<?= h(number_format($defRate, 2, '.', '')) ?>"></div>
      <div class="form-group"><label class="form-label">Free Shipping Over (J$)</label>
        <input type="number" name="free_shipping_threshold" class="form-control" step="0.01" min="0" value="<?= h(number_format($freeThr, 2, '.', '')) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:14px">Save Settings</button>
  </form>
</div>

<div style="display:flex;align-items:center;margin:24px 0 14px">
  <h2 style="font-size:1.05rem">Shipping Zones</h2>
  <a href="shipping.php?action=add" class="btn btn-primary btn-sm" style="margin-left:auto">+ Add Zone</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr><th>Zone</th><th>Parishes</th><th>Rate</th><th>Free Over</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($zones as $z): ?>
    <tr>
      <td style="font-weight:600"><?= h($z['name']) ?></td>
      <td style="color:#888;font-size:0.8rem"><?= h($z['parishes']) ?></td>
      <td style="font-weight:600">J$<?= number_format($z['rate'], 2) ?></td>
      <td style="color:#888;font-size:0.82rem"><?= ($z['free_threshold'] !== null && $z['free_threshold'] !== '') ? 'J$'.number_format($z['free_threshold'],2) : '— (global)' ?></td>
      <td><span class="badge <?= $z['active'] ? 'badge-success' : 'badge-grey' ?>"><?= $z['active'] ? 'Active' : 'Off' ?></span></td>
      <td style="white-space:nowrap">
        <a href="shipping.php?action=edit&id=<?= (int)$z['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem;margin-right:8px">Edit</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this zone?')">
          <?= csrf_field() ?>
          <input type="hidden" name="delete_zone" value="1">
          <input type="hidden" name="zone_id" value="<?= (int)$z['id'] ?>">
          <button type="submit" style="color:#c0392b;font-size:0.82rem;background:none;border:none;cursor:pointer">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($zones)): ?><tr><td colspan="6" style="text-align:center;color:#999;padding:28px">No zones yet — orders use the global default rate.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php else: /* ── Shipments tab ── */
$statusColors = order_status_colors();
$shStatus = $_GET['status'] ?? '';
$shSearch = trim($_GET['q'] ?? '');
$w = []; $pp = [];
if ($shStatus && in_array($shStatus, order_statuses(), true)) { $w[] = 'status=?'; $pp[] = $shStatus; }
if ($shSearch) { $w[] = '(order_number LIKE ? OR ship_name LIKE ? OR ship_parish LIKE ?)'; $pp = array_merge($pp, array_fill(0, 3, "%$shSearch%")); }
$wsql = $w ? 'WHERE ' . implode(' AND ', $w) : '';
$shipCols = $hasSched ? 'ship_date, tracking_number, carrier,' : '';
$shStmt = $pdo->prepare("SELECT id, order_number, status, ship_name, ship_city, ship_parish, $shipCols created_at FROM orders $wsql ORDER BY (status IN ('delivered','cancelled')) ASC, created_at DESC LIMIT 200");
$shStmt->execute($pp);
$shipments = $shStmt->fetchAll();
?>
<div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <form method="get" style="display:flex;gap:8px">
    <input type="hidden" name="tab" value="shipments">
    <input type="text" name="q" placeholder="Order #, name, parish…" class="form-control" value="<?= h($shSearch) ?>" style="width:220px">
    <select name="status" class="form-control">
      <option value="">All statuses</option>
      <?php foreach (order_statuses() as $s): ?><option value="<?= $s ?>" <?= $shStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    <?php if ($shStatus || $shSearch): ?><a href="shipping.php?tab=shipments" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
  <span style="color:#888;font-size:0.82rem;margin-left:auto"><?= count($shipments) ?> shipment(s)</span>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr><th>Order #</th><th>Recipient</th><th>Destination</th><th>Scheduled</th><th>Tracking</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($shipments as $o): ?>
    <tr>
      <td style="font-weight:600"><?= h($o['order_number']) ?></td>
      <td><?= h($o['ship_name'] ?: '—') ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h(trim(($o['ship_city'] ?? '') . ($o['ship_parish'] ? ', ' . $o['ship_parish'] : ''), ', ')) ?: '—' ?></td>
      <td style="font-size:0.82rem"><?= !empty($o['ship_date']) ? date('d M Y', strtotime($o['ship_date'])) : '<span style="color:#bbb">—</span>' ?></td>
      <td style="font-size:0.8rem;color:#888"><?= h($o['tracking_number'] ?? '') ?: '—' ?></td>
      <td><span class="badge badge-<?= $statusColors[$o['status']] ?? 'grey' ?>"><?= ucfirst($o['status']) ?></span></td>
      <td style="white-space:nowrap">
        <a href="shipping.php?action=ship&id=<?= (int)$o['id'] ?>" style="color:var(--rose-gold);font-size:0.8rem;margin-right:6px">Manage</a>
        <a href="slip.php?id=<?= (int)$o['id'] ?>&type=label" target="_blank" style="color:var(--rose-gold);font-size:0.8rem" title="Shipping label">🏷</a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($shipments)): ?><tr><td colspan="7" style="text-align:center;color:#999;padding:28px">No shipments found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
