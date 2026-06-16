<?php
$pageTitle = 'Vendors';
require_once __DIR__ . '/header.php';

$pdo    = db();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

$ready = (function () { try { db()->query('SELECT 1 FROM vendors LIMIT 1'); return true; } catch (Throwable $e) { return false; } })();
if (!$ready) {
    echo '<div class="badge-warning" style="padding:14px 18px;border-radius:8px;display:block">The vendors table isn\'t set up yet. Go to <a href="settings.php#data" style="color:inherit;text-decoration:underline">Settings → Database updates</a> and click <strong>Apply database updates</strong>.</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── Save ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vendor'])) {
    csrf_check();
    $id    = (int)($_POST['vendor_id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $vals  = [
        $name,
        trim($_POST['contact_name'] ?? '') ?: null,
        trim($_POST['email'] ?? '') ?: null,
        trim($_POST['phone'] ?? '') ?: null,
        trim($_POST['address'] ?? '') ?: null,
        trim($_POST['notes'] ?? '') ?: null,
        isset($_POST['active']) ? 1 : 0,
    ];
    if ($name === '') { flash('error', 'Vendor name is required.'); redirect(asset_base() . '/admin/vendors.php?action=' . ($id ? 'edit&id=' . $id : 'add')); }
    if ($id) {
        $pdo->prepare('UPDATE vendors SET name=?, contact_name=?, email=?, phone=?, address=?, notes=?, active=? WHERE id=?')
            ->execute(array_merge($vals, [$id]));
    } else {
        $pdo->prepare('INSERT INTO vendors (name, contact_name, email, phone, address, notes, active) VALUES (?,?,?,?,?,?,?)')
            ->execute($vals);
        $id = (int)$pdo->lastInsertId();
    }
    flash('success', 'Vendor saved.');
    redirect(asset_base() . '/admin/vendors.php');
}

/* ── Delete ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vendor'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM vendors WHERE id=?')->execute([(int)$_POST['vendor_id']]);
    flash('success', 'Vendor deleted.');
    redirect(asset_base() . '/admin/vendors.php');
}

/* ── Add / edit form ── */
$editV = null;
if ($action === 'edit' && $editId) {
    $st = $pdo->prepare('SELECT * FROM vendors WHERE id=?'); $st->execute([$editId]); $editV = $st->fetch();
}
if ($action === 'add' || $editV) {
    $v = $editV ?? ['id'=>0,'name'=>'','contact_name'=>'','email'=>'','phone'=>'','address'=>'','notes'=>'','active'=>1];
    $err = flash('error');
    ?>
    <div style="margin-bottom:18px"><a href="vendors.php" style="color:var(--rose-gold)">&larr; Back to Vendors</a></div>
    <?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>
    <div class="admin-card" style="max-width:600px">
      <h2><?= $editV ? 'Edit Vendor' : 'Add Vendor' ?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_vendor" value="1">
        <input type="hidden" name="vendor_id" value="<?= (int)$v['id'] ?>">
        <div class="admin-form-grid">
          <div class="form-group full"><label class="form-label">Vendor / Supplier Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= h($v['name']) ?>"></div>
          <div class="form-group"><label class="form-label">Contact Person</label>
            <input type="text" name="contact_name" class="form-control" value="<?= h($v['contact_name'] ?? '') ?>"></div>
          <div class="form-group"><label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= h($v['phone'] ?? '') ?>"></div>
          <div class="form-group"><label class="form-label">Email</label>
            <input type="text" name="email" class="form-control" value="<?= h($v['email'] ?? '') ?>"></div>
          <div class="form-group"><label class="form-label">Address</label>
            <input type="text" name="address" class="form-control" value="<?= h($v['address'] ?? '') ?>"></div>
          <div class="form-group full"><label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= h($v['notes'] ?? '') ?></textarea></div>
          <div class="form-group full"><label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="active" value="1" <?= $v['active'] ? 'checked' : '' ?>> Active</label></div>
        </div>
        <div style="display:flex;gap:10px;margin-top:18px">
          <button type="submit" class="btn btn-primary"><?= $editV ? 'Save Changes' : 'Add Vendor' ?></button>
          <a href="vendors.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── List ── */
$vendors = $pdo->query("SELECT v.*, (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.id) AS product_count FROM vendors v ORDER BY v.name")->fetchAll();
$ok = flash('success');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;margin-bottom:18px">
  <h2 style="font-size:1.05rem">Vendors &amp; Suppliers</h2>
  <a href="vendors.php?action=add" class="btn btn-primary" style="margin-left:auto">+ Add Vendor</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th style="text-align:center">Products</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($vendors as $v): ?>
    <tr>
      <td style="font-weight:600"><?= h($v['name']) ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($v['contact_name'] ?: '—') ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($v['phone'] ?: '—') ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($v['email'] ?: '—') ?></td>
      <td style="text-align:center"><?= (int)$v['product_count'] ?></td>
      <td><span class="badge <?= $v['active'] ? 'badge-success' : 'badge-grey' ?>"><?= $v['active'] ? 'Active' : 'Inactive' ?></span></td>
      <td>
        <a href="vendors.php?action=edit&id=<?= (int)$v['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem;margin-right:8px">Edit</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this vendor? Products keep their data but lose the vendor link.')">
          <?= csrf_field() ?><input type="hidden" name="delete_vendor" value="1"><input type="hidden" name="vendor_id" value="<?= (int)$v['id'] ?>">
          <button type="submit" style="background:none;border:none;color:#c0392b;font-size:0.82rem;cursor:pointer">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($vendors)): ?><tr><td colspan="7" style="text-align:center;color:#999;padding:28px">No vendors yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
