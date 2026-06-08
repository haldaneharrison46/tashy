<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/header.php';

$pdo = db();

// Stats
$totalRevenue  = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$totalOrders   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$lowStock      = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND active=1")->fetchColumn();

// Sales snapshot (revenue + order counts over time, and channel split)
$NC = "status != 'cancelled'";  // exclude cancelled
$salesToday   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $NC AND DATE(created_at)=CURDATE()")->fetchColumn();
$ordToday     = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$salesWeek    = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $NC AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();
$ordWeek      = $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();
$salesMonth   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $NC AND created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
$ordMonth     = $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
$posSales     = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $NC AND channel='pos'")->fetchColumn();
$onlineSales  = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $NC AND channel<>'pos'")->fetchColumn();
$chTotal      = max(1e-9, (float)$posSales + (float)$onlineSales);

// Recent orders
$recentOrders = $pdo->query("SELECT o.*, u.name as uname FROM orders o LEFT JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();

// Top products
$topProducts = $pdo->query("
  SELECT p.name, p.brand, SUM(oi.quantity) as sold, SUM(oi.price * oi.quantity) as revenue
  FROM order_items oi JOIN products p ON oi.product_id=p.id
  GROUP BY oi.product_id ORDER BY sold DESC LIMIT 5
")->fetchAll();

$statusColors = ['pending'=>'warning','processing'=>'info','shipped'=>'info','delivered'=>'success','cancelled'=>'danger'];
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-val"><?= money($totalRevenue) ?></div>
    <div class="stat-lbl">Total Revenue</div>
  </div>
  <div class="stat-card">
    <div class="stat-val"><?= number_format($totalOrders) ?></div>
    <div class="stat-lbl">Total Orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-val"><?= number_format($totalProducts) ?></div>
    <div class="stat-lbl">Active Products</div>
  </div>
  <div class="stat-card">
    <div class="stat-val"><?= number_format($totalUsers) ?></div>
    <div class="stat-lbl">Customers</div>
  </div>
  <div class="stat-card">
    <div class="stat-val" style="color:<?= $pendingOrders ? '#e67e22' : 'var(--rose-gold)' ?>"><?= $pendingOrders ?></div>
    <div class="stat-lbl">Pending Orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-val" style="color:<?= $lowStock ? '#e74c3c' : 'var(--rose-gold)' ?>"><?= $lowStock ?></div>
    <div class="stat-lbl">Low Stock Items</div>
  </div>
</div>

<div class="admin-card" style="margin-bottom:24px">
  <h2>Sales Snapshot</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px">
    <div><div style="font-size:1.3rem;font-weight:700;color:var(--rose-gold)"><?= money($salesToday) ?></div>
      <div style="font-size:0.78rem;color:#888">Today · <?= (int)$ordToday ?> order(s)</div></div>
    <div><div style="font-size:1.3rem;font-weight:700;color:var(--rose-gold)"><?= money($salesWeek) ?></div>
      <div style="font-size:0.78rem;color:#888">Last 7 days · <?= (int)$ordWeek ?> order(s)</div></div>
    <div><div style="font-size:1.3rem;font-weight:700;color:var(--rose-gold)"><?= money($salesMonth) ?></div>
      <div style="font-size:0.78rem;color:#888">This month · <?= (int)$ordMonth ?> order(s)</div></div>
    <div style="min-width:200px">
      <div style="font-size:0.82rem;display:flex;justify-content:space-between"><span>🛒 Online</span><strong><?= money($onlineSales) ?></strong></div>
      <div style="font-size:0.82rem;display:flex;justify-content:space-between;margin-top:2px"><span>🧾 In-store (POS)</span><strong><?= money($posSales) ?></strong></div>
      <div style="height:8px;border-radius:5px;overflow:hidden;background:#eee;margin-top:6px;display:flex">
        <div style="width:<?= round((float)$onlineSales / $chTotal * 100) ?>%;background:var(--rose-gold)"></div>
        <div style="width:<?= round((float)$posSales / $chTotal * 100) ?>%;background:#1d4ed8"></div>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start">

  <!-- Recent orders -->
  <div class="admin-card">
    <h2>Recent Orders <a href="orders.php" style="font-size:0.75rem;color:var(--rose-gold);font-weight:400;margin-left:8px">View all →</a></h2>
    <table class="admin-table">
      <thead><tr>
        <th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th>
      </tr></thead>
      <tbody>
      <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td><a href="orders.php?id=<?= $o['id'] ?>" style="color:var(--rose-gold);font-weight:600"><?= h($o['order_number']) ?></a></td>
        <td><?= h($o['uname'] ?? $o['ship_name']) ?></td>
        <td style="font-weight:600"><?= money($o['total']) ?></td>
        <td><span class="badge badge-<?= $statusColors[$o['status']] ?? 'grey' ?>"><?= ucfirst($o['status']) ?></span></td>
        <td style="color:#888;font-size:0.8rem"><?= date('d M y', strtotime($o['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recentOrders)): ?><tr><td colspan="5" style="color:#999;text-align:center;padding:24px">No orders yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Top products -->
  <div class="admin-card">
    <h2>Top Products</h2>
    <?php if (empty($topProducts)): ?>
    <p style="color:#999;font-size:0.85rem">No sales data yet.</p>
    <?php else: ?>
    <?php foreach ($topProducts as $tp): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--grey-light);font-size:0.83rem">
      <div>
        <div style="font-weight:600"><?= h($tp['name']) ?></div>
        <div style="color:#888"><?= h($tp['brand']) ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:700"><?= money($tp['revenue']) ?></div>
        <div style="color:#888"><?= $tp['sold'] ?> sold</div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
