<?php
$pageTitle = 'Users';
require_once __DIR__ . '/header.php';

$pdo = db();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

/* ── Save (add / edit) ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    csrf_check();
    $id    = (int)($_POST['user_id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $role  = ($_POST['role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';
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
    // Don't let an admin lock themselves out by demoting/deactivating self
    if (!$err && $id === current_user()['id'] && ($role !== 'admin' || !$active)) {
        $err = 'You cannot change your own role or active status here.';
    }

    if ($err) {
        flash('error', $err);
        redirect(asset_base() . '/admin/users.php' . ($id ? '?action=edit&id=' . $id : '?action=add'));
    }

    if ($id) {
        $pdo->prepare('UPDATE users SET name=?, email=?, phone=?, role=?, active=? WHERE id=?')
            ->execute([$name, $email, $phone ?: null, $role, $active, $id]);
        if ($pass !== '') $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
            ->execute([password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
    } else {
        $hash = password_hash($pass !== '' ? $pass : bin2hex(random_bytes(8)), PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('INSERT INTO users (name, email, phone, role, active, password_hash) VALUES (?,?,?,?,?,?)')
            ->execute([$name, $email, $phone ?: null, $role, $active, $hash]);
        $id = (int)$pdo->lastInsertId();
    }

    // Quick-login PIN (admin/staff). Stored hashed in admin_pin_hash.
    $pinRaw = preg_replace('/\D/', '', (string)($_POST['admin_pin'] ?? ''));
    if (isset($_POST['clear_pin'])) {
        $pdo->prepare('UPDATE users SET admin_pin_hash=NULL WHERE id=?')->execute([$id]);
    } elseif ($pinRaw !== '') {
        if (strlen($pinRaw) >= 4 && strlen($pinRaw) <= 8) {
            $pdo->prepare('UPDATE users SET admin_pin_hash=? WHERE id=?')
                ->execute([password_hash($pinRaw, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
        } else {
            flash('error', 'PIN must be 4–8 digits (other changes saved).');
        }
    }
    flash('success', 'User saved.');
    redirect(asset_base() . '/admin/users.php');
}

/* ── Add / edit form ───────────────────────── */
$editU = null;
if ($action === 'edit' && $editId) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$editId]); $editU = $st->fetch();
}
if ($action === 'add' || $editU) {
    $uu = $editU ?? ['id'=>0,'name'=>'','email'=>'','phone'=>'','role'=>'admin','active'=>1];
    $err = flash('error');
    ?>
    <div style="margin-bottom:18px"><a href="users.php" style="color:var(--rose-gold)">&larr; Back to Users</a></div>
    <?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>
    <div class="admin-card" style="max-width:560px">
      <h2><?= $editU ? 'Edit User' : 'Add Staff / User' ?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_user" value="1">
        <input type="hidden" name="user_id" value="<?= (int)$uu['id'] ?>">
        <div class="admin-form-grid">
          <div class="form-group full"><label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= h($uu['name']) ?>"></div>
          <div class="form-group"><label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required value="<?= h($uu['email']) ?>"></div>
          <div class="form-group"><label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= h($uu['phone'] ?? '') ?>"></div>
          <div class="form-group"><label class="form-label">Role</label>
            <select name="role" class="form-control">
              <option value="admin"    <?= ($uu['role'] ?? '')==='admin'?'selected':'' ?>>Admin / Staff</option>
              <option value="customer" <?= ($uu['role'] ?? '')==='customer'?'selected':'' ?>>Customer</option>
            </select></div>
          <div class="form-group"><label class="form-label"><?= $editU ? 'Reset password (optional)' : 'Password (optional — random if blank)' ?></label>
            <input type="text" name="password" class="form-control" placeholder="<?= $editU ? 'Leave blank to keep current' : 'Auto-generated if blank' ?>"></div>
          <div class="form-group"><label class="form-label">Quick-login PIN <span style="color:#888;font-weight:400">(4–8 digits)</span> <?= !empty($uu['admin_pin_hash']) ? '<span class="badge badge-success">set</span>' : '' ?></label>
            <input type="text" name="admin_pin" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="off" placeholder="<?= !empty($uu['admin_pin_hash']) ? 'Leave blank to keep' : 'e.g. 4821' ?>"></div>
          <div class="form-group full"><label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="active" value="1" <?= $uu['active'] ? 'checked' : '' ?>> Active (can sign in)</label></div>
          <?php if (!empty($uu['admin_pin_hash'])): ?>
          <div class="form-group full"><label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="clear_pin" value="1"> Remove quick-login PIN</label></div>
          <?php endif; ?>
        </div>
        <p style="font-size:0.78rem;color:#888;margin-top:-6px">The PIN enables fast staff sign-in at the admin login (works for Admin / Staff accounts).</p>
        <div style="display:flex;gap:10px;margin-top:18px">
          <button type="submit" class="btn btn-primary"><?= $editU ? 'Save Changes' : 'Add User' ?></button>
          <a href="users.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── Toggle admin role ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_role'])) {
    csrf_check();
    $uid = (int)$_POST['user_id'];
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $cur = $stmt->fetchColumn();
    if ($cur === 'admin' && $uid !== current_user()['id']) {
        $pdo->prepare("UPDATE users SET role='customer' WHERE id=?")->execute([$uid]);
        flash('success', 'User demoted to customer.');
    } elseif ($cur === 'customer') {
        $pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$uid]);
        flash('success', 'User promoted to admin.');
    }
    redirect(asset_base() . '/admin/users.php');
}

/* ── Delete user ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    csrf_check();
    $uid = (int)$_POST['user_id'];
    if ($uid !== current_user()['id']) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        flash('success', 'User deleted.');
    }
    redirect(asset_base() . '/admin/users.php');
}

$search = trim($_GET['q'] ?? '');
$params = [];
$whereSQL = '';
if ($search) {
    $whereSQL = "WHERE name LIKE ? OR email LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) as order_count,
    (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id=u.id AND o.status!='cancelled') as total_spent
    FROM users u $whereSQL ORDER BY u.created_at DESC LIMIT 200");
$stmt->execute($params);
$users = $stmt->fetchAll();

$ok = flash('success');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px"><?= h($ok) ?></div><?php endif; ?>

<div style="display:flex;gap:10px;align-items:center;margin-bottom:20px">
  <form method="get" style="display:flex;gap:8px">
    <input type="text" name="q" placeholder="Search name or email…" class="form-control" value="<?= h($search) ?>" style="width:260px">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
    <?php if ($search): ?><a href="users.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
  <span style="color:#888;font-size:0.82rem;margin-left:auto"><?= count($users) ?> user(s)</span>
  <a href="users.php?action=add" class="btn btn-primary">+ Add Staff</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr>
      <th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Orders</th><th>Spent</th><th>Joined</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td style="font-weight:600"><?= h($u['name']) ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($u['email']) ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($u['phone'] ?? '—') ?></td>
      <td>
        <span class="badge <?= $u['role']==='admin' ? 'badge-info' : 'badge-grey' ?>"><?= ucfirst($u['role']) ?></span>
      </td>
      <td style="text-align:center"><?= $u['order_count'] ?></td>
      <td style="font-weight:600"><?= money($u['total_spent']) ?></td>
      <td style="color:#888;font-size:0.8rem"><?= date('d M y', strtotime($u['created_at'])) ?></td>
      <td>
        <a href="users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:0.72rem">Edit</a>
        <?php if ($u['id'] !== current_user()['id']): ?>
        <form method="post" style="display:inline;margin-left:6px">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <button type="submit" name="toggle_role" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:0.72rem">
            <?= $u['role']==='admin' ? 'Demote' : 'Make Admin' ?>
          </button>
        </form>
        <form method="post" style="display:inline;margin-left:6px" onsubmit="return confirm('Delete this user? This cannot be undone.')">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <button type="submit" name="delete_user" class="btn btn-sm" style="background:#fef2f2;color:#c0392b;border:1px solid #fca5a5;padding:3px 8px;font-size:0.72rem">Delete</button>
        </form>
        <?php else: ?>
        <span style="font-size:0.75rem;color:#888;margin-left:6px">(you)</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?><tr><td colspan="8" style="text-align:center;color:#999;padding:28px">No users found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
