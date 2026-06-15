<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';

require_login();
$user = current_user();
$tab  = $_GET['tab'] ?? 'profile';

/* ── Profile update ─────────────────────────────────────────── */
$profileError = '';
$profileOk    = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_check();
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if (strlen($name) < 2) {
        $profileError = 'Please enter your full name.';
    } else {
        db()->prepare('UPDATE users SET name=?, phone=? WHERE id=?')
             ->execute([$name, $phone, $user['id']]);
        $_SESSION['sb_user'] = array_merge($_SESSION['sb_user'], ['name' => $name, 'phone' => $phone]);
        $user['name']  = $name;
        $user['phone'] = $phone;
        $profileOk = true;
    }
    $tab = 'profile';
}

/* ── Password change ─────────────────────────────────────────── */
$pwError = '';
$pwOk    = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $row = db()->prepare('SELECT password FROM users WHERE id=?');
    $row->execute([$user['id']]);
    $hash = $row->fetchColumn();

    if (!password_verify($current, $hash)) {
        $pwError = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $pwError = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $pwError = 'Passwords do not match.';
    } else {
        db()->prepare('UPDATE users SET password=? WHERE id=?')
             ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
        $pwOk = true;
    }
    $tab = 'security';
}

/* ── Order list ─────────────────────────────────────────────── */
$orders = [];
if ($tab === 'orders') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll();
}

/* ── Wishlist / favourites ────────────────────────────────────── */
$wishlist = [];
if ($tab === 'wishlist') {
    $stmt = db()->prepare(
        'SELECT p.* FROM wishlist w JOIN products p ON p.id = w.product_id
         WHERE w.user_id = ? AND p.active = 1 ORDER BY w.id DESC'
    );
    $stmt->execute([$user['id']]);
    $wishlist = $stmt->fetchAll();
}

/* ── Single order detail ──────────────────────────────────────── */
$orderDetail = null;
$orderItems  = [];
if ($tab === 'order' && !empty($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE id=? AND user_id=?');
    $stmt->execute([(int)$_GET['id'], $user['id']]);
    $orderDetail = $stmt->fetch();
    if ($orderDetail) {
        $stmt2 = db()->prepare('SELECT * FROM order_items WHERE order_id=?');
        $stmt2->execute([$orderDetail['id']]);
        $orderItems = $stmt2->fetchAll();
    }
}

$pageTitle = 'My Account | ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<style>
.account-layout { display:grid; grid-template-columns:220px 1fr; gap:32px; align-items:start; }
.account-nav { background:var(--white); border:1px solid var(--grey-light); border-radius:12px; padding:16px 0; }
.account-nav a { display:block; padding:10px 20px; color:var(--black); text-decoration:none; font-size:0.92rem; transition:background .15s; }
.account-nav a:hover, .account-nav a.active { background:var(--rose-pale); color:var(--rose-gold); }
.account-nav .nav-divider { height:1px; background:var(--grey-light); margin:8px 0; }
.account-panel { background:var(--white); border:1px solid var(--grey-light); border-radius:12px; padding:28px; }
.status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:uppercase; }
.status-pending  { background:#fef9c3; color:#a16207; }
.status-processing { background:#dbeafe; color:#1d4ed8; }
.status-shipped  { background:#d1fae5; color:#065f46; }
.status-delivered{ background:#dcfce7; color:#14532d; }
.status-cancelled{ background:#fee2e2; color:#991b1b; }
@media(max-width:700px){
  .account-layout { grid-template-columns:1fr; }
  .account-nav { display:flex; overflow-x:auto; white-space:nowrap; }
  .account-nav a { padding:10px 14px; }
  .account-nav .nav-divider { width:1px; height:auto; margin:0 4px; }
}
</style>

<div class="section">
  <div class="container" style="max-width:940px">
    <h1 style="margin-bottom:28px">My Account</h1>
    <div class="account-layout">

      <!-- Sidebar nav -->
      <nav class="account-nav">
        <a href="account.php?tab=profile"  class="<?= $tab==='profile'  ? 'active':'' ?>">👤 Profile</a>
        <a href="account.php?tab=orders"   class="<?= $tab==='orders'||$tab==='order' ? 'active':'' ?>">📦 Orders</a>
        <a href="account.php?tab=wishlist" class="<?= $tab==='wishlist' ? 'active':'' ?>">❤️ Favourites</a>
        <a href="account.php?tab=security" class="<?= $tab==='security' ? 'active':'' ?>">🔒 Security</a>
        <div class="nav-divider"></div>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="admin/index.php">⚙️ Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" style="color:#c0392b">Sign Out</a>
      </nav>

      <!-- Panel content -->
      <div class="account-panel">

        <?php if ($tab === 'profile'): ?>
        <h2 style="margin-bottom:20px">Profile Details</h2>
        <?php if ($profileOk): ?>
          <div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px">Profile updated!</div>
        <?php elseif ($profileError): ?>
          <div style="background:#fef2f2;color:#c0392b;padding:12px 16px;border-radius:8px;margin-bottom:20px"><?= h($profileError) ?></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="update_profile" value="1">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" required value="<?= h($user['name']) ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" value="<?= h($user['email']) ?>" disabled style="opacity:.6;cursor:not-allowed">
              <small style="color:#999">Email cannot be changed.</small>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= h($user['phone'] ?? '') ?>">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" style="margin-top:10px">Save Changes</button>
        </form>

        <?php elseif ($tab === 'security'): ?>
        <h2 style="margin-bottom:20px">Change Password</h2>
        <?php if ($pwOk): ?>
          <div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px">Password updated!</div>
        <?php elseif ($pwError): ?>
          <div style="background:#fef2f2;color:#c0392b;padding:12px 16px;border-radius:8px;margin-bottom:20px"><?= h($pwError) ?></div>
        <?php endif; ?>
        <form method="post" style="max-width:360px">
          <?= csrf_field() ?>
          <input type="hidden" name="change_password" value="1">
          <div class="form-group" style="margin-bottom:14px">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="form-group" style="margin-bottom:14px">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="8">
          </div>
          <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </form>

        <?php elseif ($tab === 'orders'): ?>
        <h2 style="margin-bottom:20px">Order History</h2>
        <?php if (empty($orders)): ?>
          <p style="color:#999">You haven't placed any orders yet. <a href="shop.php" style="color:var(--rose-gold)">Start shopping →</a></p>
        <?php else: ?>
          <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;min-width:500px">
              <thead><tr style="border-bottom:2px solid var(--grey-light);text-align:left">
                <th style="padding:8px 0">Order #</th>
                <th style="padding:8px 0">Date</th>
                <th style="padding:8px 0">Total</th>
                <th style="padding:8px 0">Status</th>
                <th></th>
              </tr></thead>
              <tbody>
              <?php foreach ($orders as $o): ?>
              <tr style="border-bottom:1px solid var(--grey-light)">
                <td style="padding:12px 0;font-weight:600"><?= h($o['order_number']) ?></td>
                <td style="padding:12px 0;color:#888;font-size:0.85rem"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                <td style="padding:12px 0;font-weight:700"><?= money($o['total']) ?></td>
                <td style="padding:12px 0">
                  <span class="status-badge status-<?= h($o['status']) ?>"><?= ucfirst(h($o['status'])) ?></span>
                </td>
                <td style="padding:12px 0;text-align:right">
                  <a href="account.php?tab=order&id=<?= $o['id'] ?>" style="color:var(--rose-gold);font-size:0.85rem">View →</a>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <?php elseif ($tab === 'wishlist'): ?>
        <h2 style="margin-bottom:20px">My Favourites</h2>
        <?php if (empty($wishlist)): ?>
          <p style="color:#999">You haven't saved any favourites yet. <a href="shop.php" style="color:var(--rose-gold)">Browse the shop →</a> and tap the ♥ on any product.</p>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:18px">
            <?php foreach ($wishlist as $p): ?>
            <div class="product-card" data-product-id="<?= $p['id'] ?>" style="position:relative">
              <button class="fav-btn active" aria-label="Remove from wishlist" data-product-id="<?= $p['id'] ?>" onclick="toggleWishlist(this)" style="position:absolute;top:8px;right:8px;z-index:2">❤️</button>
              <a href="<?= asset_base() ?>/product.php?slug=<?= h($p['slug']) ?>">
                <div class="product-card-img">
                  <img src="<?= asset_base() ?>/assets/images/<?= h($p['image'] ?: 'placeholder.svg') ?>" alt="<?= h($p['name']) ?>" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px">
                </div>
              </a>
              <div class="product-card-body" style="padding-top:10px">
                <div class="product-brand" style="font-size:.78rem;color:#999"><?= h($p['brand']) ?></div>
                <h3 class="product-name" style="font-size:.92rem;margin:2px 0 6px"><a href="<?= asset_base() ?>/product.php?slug=<?= h($p['slug']) ?>" style="color:inherit;text-decoration:none"><?= h($p['name']) ?></a></h3>
                <span class="price-current" style="font-weight:700"><?= money($p['price']) ?></span>
                <button class="quick-add-btn btn btn-primary btn-sm" style="margin-top:8px;width:100%" onclick="addToCart(<?= $p['id'] ?>, this)">Add to Cart</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php elseif ($tab === 'order' && $orderDetail): ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
          <a href="account.php?tab=orders" style="color:var(--rose-gold);font-size:0.85rem">← Back to Orders</a>
        </div>
        <h2 style="margin-bottom:4px">Order <?= h($orderDetail['order_number']) ?></h2>
        <p style="color:#888;margin-bottom:20px;font-size:0.85rem">Placed <?= date('d M Y, g:ia', strtotime($orderDetail['created_at'])) ?></p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
          <div style="background:var(--rose-pale);border-radius:8px;padding:16px">
            <p style="font-weight:600;margin-bottom:8px">Delivery Address</p>
            <p style="font-size:0.88rem;color:#555"><?= h($orderDetail['ship_name']) ?></p>
            <p style="font-size:0.88rem;color:#555"><?= h($orderDetail['ship_address']) ?></p>
            <p style="font-size:0.88rem;color:#555"><?= h($orderDetail['ship_city']) ?>, <?= h($orderDetail['ship_parish']) ?></p>
            <p style="font-size:0.88rem;color:#555"><?= h($orderDetail['ship_phone']) ?></p>
          </div>
          <div style="background:var(--rose-pale);border-radius:8px;padding:16px">
            <p style="font-weight:600;margin-bottom:8px">Payment</p>
            <p style="font-size:0.88rem;color:#555">Cash on Delivery</p>
            <p style="font-size:0.88rem;color:#555;margin-top:8px">Status: <span class="status-badge status-<?= h($orderDetail['status']) ?>"><?= ucfirst(h($orderDetail['status'])) ?></span></p>
          </div>
        </div>

        <table style="width:100%;border-collapse:collapse">
          <thead><tr style="border-bottom:2px solid var(--grey-light);text-align:left">
            <th style="padding-bottom:10px">Product</th>
            <th style="padding-bottom:10px;text-align:center">Qty</th>
            <th style="padding-bottom:10px;text-align:right">Price</th>
          </tr></thead>
          <tbody>
          <?php foreach ($orderItems as $oi): ?>
          <tr style="border-bottom:1px solid var(--grey-light)">
            <td style="padding:12px 0">
              <p style="font-weight:600"><?= h($oi['name']) ?></p>
              <p style="font-size:0.8rem;color:#888"><?= h($oi['brand']) ?></p>
            </td>
            <td style="text-align:center;padding:12px 0">×<?= $oi['quantity'] ?></td>
            <td style="text-align:right;padding:12px 0;font-weight:700"><?= money($oi['price'] * $oi['quantity']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div style="border-top:2px solid var(--grey-light);margin-top:12px;padding-top:12px;max-width:260px;margin-left:auto">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem"><span>Subtotal</span><span><?= money($orderDetail['subtotal']) ?></span></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem"><span>Shipping</span><span><?= $orderDetail['shipping'] > 0 ? money($orderDetail['shipping']) : 'FREE' ?></span></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem"><span>GCT</span><span><?= money($orderDetail['tax']) ?></span></div>
          <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem"><span>Total</span><span><?= money($orderDetail['total']) ?></span></div>
        </div>

        <?php else: ?>
        <p style="color:#999">Page not found.</p>
        <?php endif; ?>

      </div>
    </div><!-- .account-layout -->
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
