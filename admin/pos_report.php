<?php
$pageTitle = 'POS Report';
require_once __DIR__ . '/header.php';

$pdo = db();
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$to   = $_GET['to']   ?? date('Y-m-d');
$ch   = ($_GET['ch'] ?? 'pos') === 'all' ? 'all' : 'pos';

// normalise dates
$from = date('Y-m-d', strtotime($from) ?: time());
$to   = date('Y-m-d', strtotime($to)   ?: time());

$where  = "status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?";
$params = [$from, $to];
if ($ch === 'pos') { $where .= " AND channel = 'pos'"; }

// Summary
$sum = $pdo->prepare("SELECT COUNT(*) orders, COALESCE(SUM(total),0) sales, COALESCE(SUM(discount),0) disc, COALESCE(SUM(tax),0) tax FROM orders WHERE $where");
$sum->execute($params);
$S = $sum->fetch();
$itemsSold = $pdo->prepare("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $where");
$itemsSold->execute($params);
$itemsSold = (int)$itemsSold->fetchColumn();
$aov = $S['orders'] ? $S['sales'] / $S['orders'] : 0;

// Payment breakdown
$pay = $pdo->prepare("SELECT payment_method, COUNT(*) c, COALESCE(SUM(total),0) t FROM orders WHERE $where GROUP BY payment_method ORDER BY t DESC");
$pay->execute($params);
$pay = $pay->fetchAll();

// Top items
$top = $pdo->prepare("SELECT oi.name, SUM(oi.quantity) qty, SUM(oi.price*oi.quantity) rev
                      FROM order_items oi JOIN orders o ON o.id=oi.order_id
                      WHERE $where GROUP BY oi.name ORDER BY qty DESC LIMIT 10");
$top->execute($params);
$top = $top->fetchAll();

// Daily
$daily = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) c, COALESCE(SUM(total),0) t FROM orders WHERE $where GROUP BY DATE(created_at) ORDER BY d DESC");
$daily->execute($params);
$daily = $daily->fetchAll();
$maxDay = 0; foreach ($daily as $d) { $maxDay = max($maxDay, (float)$d['t']); }
?>
<form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:22px">
  <div><label class="form-label" style="font-size:0.75rem">From</label><input type="date" name="from" value="<?= h($from) ?>" class="form-control"></div>
  <div><label class="form-label" style="font-size:0.75rem">To</label><input type="date" name="to" value="<?= h($to) ?>" class="form-control"></div>
  <div><label class="form-label" style="font-size:0.75rem">Channel</label>
    <select name="ch" class="form-control">
      <option value="pos" <?= $ch==='pos'?'selected':'' ?>>In-store (POS)</option>
      <option value="all" <?= $ch==='all'?'selected':'' ?>>All sales</option>
    </select></div>
  <button type="submit" class="btn btn-primary btn-sm">Run</button>
  <a href="pos.php" class="btn btn-outline btn-sm">← POS</a>
</form>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-val"><?= money($S['sales']) ?></div><div class="stat-lbl">Sales</div></div>
  <div class="stat-card"><div class="stat-val"><?= (int)$S['orders'] ?></div><div class="stat-lbl">Transactions</div></div>
  <div class="stat-card"><div class="stat-val"><?= money($aov) ?></div><div class="stat-lbl">Avg Sale</div></div>
  <div class="stat-card"><div class="stat-val"><?= $itemsSold ?></div><div class="stat-lbl">Items Sold</div></div>
  <div class="stat-card"><div class="stat-val"><?= money($S['tax']) ?></div><div class="stat-lbl">GCT Collected</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">
  <div class="admin-card">
    <h2>Payment Methods</h2>
    <table class="admin-table">
      <thead><tr><th>Method</th><th style="text-align:center">Txns</th><th style="text-align:right">Total</th></tr></thead>
      <tbody>
      <?php foreach ($pay as $p): ?>
      <tr><td><?= strtoupper(h($p['payment_method'] ?: 'n/a')) ?></td><td style="text-align:center"><?= (int)$p['c'] ?></td><td style="text-align:right;font-weight:600"><?= money($p['t']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (empty($pay)): ?><tr><td colspan="3" style="text-align:center;color:#999;padding:20px">No sales in range.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="admin-card">
    <h2>Top Items</h2>
    <?php foreach ($top as $t): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--grey-light);font-size:0.85rem">
      <span><?= h($t['name']) ?> <span style="color:#888">×<?= (int)$t['qty'] ?></span></span>
      <strong><?= money($t['rev']) ?></strong>
    </div>
    <?php endforeach; ?>
    <?php if (empty($top)): ?><p style="color:#999;font-size:0.85rem">No items in range.</p><?php endif; ?>
  </div>
</div>

<div class="admin-card" style="margin-top:24px">
  <h2>Daily Sales</h2>
  <?php foreach ($daily as $d): ?>
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;font-size:0.84rem">
    <span style="width:90px;color:#888"><?= date('d M', strtotime($d['d'])) ?></span>
    <div style="flex:1;background:#f0ede9;border-radius:5px;height:18px;overflow:hidden">
      <div style="height:100%;background:var(--rose-gold);width:<?= $maxDay ? round((float)$d['t']/$maxDay*100) : 0 ?>%"></div>
    </div>
    <span style="width:110px;text-align:right;font-weight:600"><?= money($d['t']) ?></span>
    <span style="width:54px;text-align:right;color:#888"><?= (int)$d['c'] ?> txn</span>
  </div>
  <?php endforeach; ?>
  <?php if (empty($daily)): ?><p style="color:#999;font-size:0.85rem">No sales in the selected range.</p><?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
