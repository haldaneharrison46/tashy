<?php
$pageTitle = 'Shipping';
require_once __DIR__ . '/header.php';

$pdo      = db();
$action   = $_GET['action'] ?? '';
$editId   = (int)($_GET['id'] ?? 0);
$parishes = jamaica_parishes();

/* ── Save global settings ──────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_check();
    set_setting('default_shipping_rate',  (string)max(0, (float)($_POST['default_shipping_rate'] ?? 0)));
    set_setting('free_shipping_threshold',(string)max(0, (float)($_POST['free_shipping_threshold'] ?? 0)));
    flash('success', 'Shipping settings saved.');
    redirect(SITE_URL . '/admin/shipping.php');
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
        redirect(SITE_URL . '/admin/shipping.php?action=' . ($id ? 'edit&id=' . $id : 'add'));
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
    redirect(SITE_URL . '/admin/shipping.php');
}

/* ── Delete zone ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_zone'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM shipping_zones WHERE id=?')->execute([(int)$_POST['zone_id']]);
    flash('success', 'Zone deleted.');
    redirect(SITE_URL . '/admin/shipping.php');
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

/* ── Overview ──────────────────────────────── */
$zones = get_shipping_zones(false);
$defRate = shipping_default_rate();
$freeThr = free_shipping_threshold();
$ok = flash('success'); $err = flash('error');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

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

<?php require_once __DIR__ . '/footer.php'; ?>
