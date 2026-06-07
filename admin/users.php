<?php
$pageTitle = 'Users';
require_once __DIR__ . '/header.php';

$pdo = db();

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
    redirect(SITE_URL . '/admin/users.php');
}

/* ── Delete user ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    csrf_check();
    $uid = (int)$_POST['user_id'];
    if ($uid !== current_user()['id']) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        flash('success', 'User deleted.');
    }
    redirect(SITE_URL . '/admin/users.php');
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
        <?php if ($u['id'] !== current_user()['id']): ?>
        <form method="post" style="display:inline">
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
        <span style="font-size:0.75rem;color:#888">(you)</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?><tr><td colspan="8" style="text-align:center;color:#999;padding:28px">No users found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
