<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

/* ── CSV export (must run before any HTML output) ── */
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db()->query("SELECT u.name, u.email, u.phone, u.active, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) oc,
            (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id=u.id AND o.status!='cancelled') spent,
            (SELECT MAX(created_at) FROM orders o WHERE o.user_id=u.id) last_order
        FROM users u WHERE u.role='customer' ORDER BY u.name")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Email','Phone','Orders','Lifetime Spend (JMD)','Last Order','Status','Joined']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['name'], $r['email'], $r['phone'], (int)$r['oc'],
            number_format((float)$r['spent'], 2, '.', ''), $r['last_order'],
            $r['active'] ? 'Active' : 'Inactive', $r['created_at']]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Customers';
require_once __DIR__ . '/header.php';

$pdo    = db();
$action = $_GET['action'] ?? '';
$viewId = (int)($_GET['id'] ?? 0);

/* ── Save (add / edit) ──────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    csrf_check();
    $id    = (int)($_POST['customer_id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $active= isset($_POST['active']) ? 1 : 0;
    $pass  = (string)($_POST['password'] ?? '');

    $err = '';
    if ($name === '') $err = 'Name is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'A valid email is required.';
    else {
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $dup->execute([$email, $id]);
        if ($dup->fetch()) $err = 'Another account already uses that email.';
    }

    if ($err) { flash('error', $err); }
    elseif ($id) {
        $pdo->prepare('UPDATE users SET name=?, email=?, phone=?, address=?, active=? WHERE id=?')
            ->execute([$name, $email, $phone, $address ?: null, $active, $id]);
        if ($pass !== '') {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                ->execute([password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
        }
        flash('success', 'Customer updated.');
        redirect(SITE_URL . '/admin/customers.php?id=' . $id);
    } else {
        $hash = password_hash($pass !== '' ? $pass : bin2hex(random_bytes(8)), PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('INSERT INTO users (name, email, phone, address, password_hash, role, active) VALUES (?,?,?,?,?,?,?)')
            ->execute([$name, $email, $phone, $address ?: null, $hash, 'customer', $active]);
        flash('success', 'Customer added.');
        redirect(SITE_URL . '/admin/customers.php?id=' . (int)$pdo->lastInsertId());
    }
    redirect(SITE_URL . '/admin/customers.php' . ($id ? '?action=edit&id=' . $id : '?action=add'));
}

/* ── Toggle active ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    csrf_check();
    $uid = (int)$_POST['customer_id'];
    $pdo->prepare("UPDATE users SET active = 1 - active WHERE id=? AND role='customer'")->execute([$uid]);
    flash('success', 'Customer status updated.');
    redirect(SITE_URL . '/admin/customers.php' . ((int)($_POST['back_to_view'] ?? 0) ? '?id=' . $uid : ''));
}

/* ── Add / Edit form ───────────────────────── */
$editC = null;
if ($action === 'edit' && $viewId) {
    $st = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='customer'");
    $st->execute([$viewId]);
    $editC = $st->fetch();
}
if ($action === 'add' || $editC) {
    $c = $editC ?? ['id'=>0,'name'=>'','email'=>'','phone'=>'','address'=>'','active'=>1];
    $err = flash('error');
    ?>
    <div style="margin-bottom:18px"><a href="customers.php" style="color:var(--rose-gold)">&larr; Back to Customers</a></div>
    <?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>
    <div class="admin-card" style="max-width:560px">
      <h2><?= $editC ? 'Edit Customer' : 'Add Customer' ?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_customer" value="1">
        <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
        <div class="admin-form-grid">
          <div class="form-group full"><label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= h($c['name']) ?>"></div>
          <div class="form-group"><label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required value="<?= h($c['email']) ?>"></div>
          <div class="form-group"><label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= h($c['phone'] ?? '') ?>"></div>
          <div class="form-group full"><label class="form-label">Address</label>
            <input type="text" name="address" class="form-control" value="<?= h($c['address'] ?? '') ?>" placeholder="Street, city, parish"></div>
          <div class="form-group full"><label class="form-label"><?= $editC ? 'Reset Password (optional)' : 'Password (optional — random if blank)' ?></label>
            <input type="text" name="password" class="form-control" placeholder="<?= $editC ? 'Leave blank to keep current' : 'Auto-generated if blank' ?>"></div>
          <div class="form-group full">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?>> Active (can sign in &amp; order)
            </label>
          </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:18px">
          <button type="submit" class="btn btn-primary"><?= $editC ? 'Save Changes' : 'Add Customer' ?></button>
          <a href="customers.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── Customer detail ───────────────────────── */
if ($viewId) {
    $st = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='customer'");
    $st->execute([$viewId]);
    $c = $st->fetch();
    if (!$c) { flash('error', 'Customer not found.'); redirect(SITE_URL . '/admin/customers.php'); }

    $os = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
    $os->execute([$viewId]);
    $orders = $os->fetchAll();
    $spent  = 0; $cnt = 0;
    foreach ($orders as $o) { if ($o['status'] !== 'cancelled') { $spent += $o['total']; $cnt++; } }
    $aov = $cnt ? $spent / $cnt : 0;
    $statusColors = ['pending'=>'warning','processing'=>'info','shipped'=>'info','delivered'=>'success','cancelled'=>'danger'];
    $ok = flash('success');
    ?>
    <div style="margin-bottom:18px"><a href="customers.php" style="color:var(--rose-gold)">&larr; Back to Customers</a></div>
    <?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start">
      <div>
        <div class="stats-grid">
          <div class="stat-card"><div class="stat-val"><?= count($orders) ?></div><div class="stat-lbl">Orders</div></div>
          <div class="stat-card"><div class="stat-val"><?= money($spent) ?></div><div class="stat-lbl">Lifetime Spend</div></div>
          <div class="stat-card"><div class="stat-val"><?= money($aov) ?></div><div class="stat-lbl">Avg Order</div></div>
        </div>
        <div class="admin-card" style="padding:0;overflow:hidden">
          <div style="padding:14px 18px;font-weight:700;border-bottom:1px solid var(--grey-light)">Order History</div>
          <table class="admin-table">
            <thead><tr><th>Order #</th><th>Date</th><th>Status</th><th style="text-align:right">Total</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
              <td style="font-weight:600"><?= h($o['order_number']) ?></td>
              <td style="color:#888;font-size:0.82rem"><?= date('d M y', strtotime($o['created_at'])) ?></td>
              <td><span class="badge badge-<?= $statusColors[$o['status']] ?? 'grey' ?>"><?= ucfirst($o['status']) ?></span></td>
              <td style="text-align:right;font-weight:600"><?= money($o['total']) ?></td>
              <td><a href="orders.php?id=<?= (int)$o['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem">View →</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?><tr><td colspan="5" style="text-align:center;color:#999;padding:24px">No orders yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div>
        <div class="admin-card">
          <h2><?= h($c['name']) ?> <?php if (!$c['active']): ?><span class="badge badge-grey">Inactive</span><?php endif; ?></h2>
          <p style="font-size:0.85rem;color:#888;margin-top:6px">✉ <?= h($c['email']) ?></p>
          <p style="font-size:0.85rem;color:#888">📞 <?= h($c['phone'] ?: '—') ?></p>
          <?php if (!empty($c['address'])): ?><p style="font-size:0.85rem;color:#888">📍 <?= h($c['address']) ?></p><?php endif; ?>
          <p style="font-size:0.8rem;color:#aaa;margin-top:8px">Joined <?= date('d M Y', strtotime($c['created_at'])) ?></p>
          <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
            <a href="customers.php?action=edit&id=<?= (int)$c['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="toggle_active" value="1">
              <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
              <input type="hidden" name="back_to_view" value="1">
              <button type="submit" class="btn btn-outline btn-sm"><?= $c['active'] ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── Customer list ─────────────────────────── */
$search = trim($_GET['q'] ?? '');
$params = []; $whereSQL = "WHERE u.role='customer'";
if ($search) { $whereSQL .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }

$st = $pdo->prepare("SELECT u.*,
    (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) AS order_count,
    (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id=u.id AND o.status!='cancelled') AS total_spent,
    (SELECT MAX(created_at) FROM orders o WHERE o.user_id=u.id) AS last_order
    FROM users u $whereSQL ORDER BY u.created_at DESC LIMIT 300");
$st->execute($params);
$customers = $st->fetchAll();
$ok = flash('success'); $err = flash('error');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

<div style="display:flex;gap:10px;align-items:center;margin-bottom:20px">
  <form method="get" style="display:flex;gap:8px">
    <input type="text" name="q" placeholder="Search name, email, phone…" class="form-control" value="<?= h($search) ?>" style="width:260px">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
    <?php if ($search): ?><a href="customers.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
  <a href="customers.php?export=csv" class="btn btn-outline" style="margin-left:auto">⬇ Export CSV</a>
  <a href="customers.php?action=add" class="btn btn-primary">+ Add Customer</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th style="text-align:center">Orders</th><th>Spent</th><th>Last Order</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td style="font-weight:600"><?= h($c['name']) ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($c['email']) ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($c['phone'] ?: '—') ?></td>
      <td style="text-align:center"><?= (int)$c['order_count'] ?></td>
      <td style="font-weight:600"><?= money($c['total_spent']) ?></td>
      <td style="color:#888;font-size:0.8rem"><?= $c['last_order'] ? date('d M y', strtotime($c['last_order'])) : '—' ?></td>
      <td><span class="badge <?= $c['active'] ? 'badge-success' : 'badge-grey' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
      <td><a href="customers.php?id=<?= (int)$c['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem">View →</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($customers)): ?><tr><td colspan="8" style="text-align:center;color:#999;padding:28px">No customers found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
