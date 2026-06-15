<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/orders.php';

$num   = trim($_GET['order'] ?? $_POST['order'] ?? '');
$order = null; $history = []; $items = [];
if ($num !== '') {
    $st = db()->prepare("SELECT * FROM orders WHERE order_number = ?");
    $st->execute([$num]);
    $order = $st->fetch();
    if ($order) {
        $history = get_order_history((int)$order['id']);
        $it = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $it->execute([(int)$order['id']]);
        $items = $it->fetchAll();
    }
}

$pageTitle = 'Track Your Order | ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
$colors  = order_status_colors();
$steps   = ['pending'=>'Order received','processing'=>'Being prepared','shipped'=>'Shipped','delivered'=>'Delivered'];
?>
<div class="section">
  <div class="container" style="max-width:680px">
    <h1 style="margin-bottom:8px">Track Your Order</h1>
    <p style="color:#888;margin-bottom:24px">Enter your order number (from your confirmation email) to see its progress.</p>

    <form method="get" style="display:flex;gap:8px;margin-bottom:28px;flex-wrap:wrap">
      <input type="text" name="order" value="<?= h($num) ?>" placeholder="e.g. TK-AB12CD-2026" class="form-control" required style="flex:1;min-width:220px">
      <button type="submit" class="btn btn-primary">Track</button>
    </form>

    <?php if ($num !== '' && !$order): ?>
      <div style="background:#fef2f2;border:1px solid #fca5a5;color:#c0392b;padding:14px 18px;border-radius:8px">
        We couldn't find an order with that number. Please check it and try again.
      </div>
    <?php elseif ($order): ?>
      <?php $cur = $order['status']; $isCancelled = $cur === 'cancelled'; ?>
      <div style="background:var(--white);border:1px solid var(--grey-light);border-radius:12px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
          <div>
            <div style="font-weight:700;font-size:1.1rem"><?= h($order['order_number']) ?></div>
            <div style="color:#888;font-size:0.85rem">Placed <?= date('d M Y', strtotime($order['created_at'])) ?></div>
          </div>
          <span class="badge badge-<?= $colors[$cur] ?? 'grey' ?>" style="font-size:0.8rem;padding:6px 12px"><?= ucfirst($cur) ?></span>
        </div>

        <?php if (!$isCancelled): ?>
        <!-- Progress steps -->
        <?php $order_keys = array_keys($steps); $curIdx = array_search($cur, $order_keys); if ($curIdx === false) $curIdx = -1; ?>
        <div style="display:flex;justify-content:space-between;margin:28px 0 8px;position:relative">
          <?php foreach ($steps as $k => $lbl): $done = array_search($k, $order_keys) <= $curIdx; ?>
          <div style="flex:1;text-align:center;position:relative">
            <div style="width:26px;height:26px;border-radius:50%;margin:0 auto;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#fff;background:<?= $done ? 'var(--rose-gold)' : '#d9d4cf' ?>"><?= $done ? '✓' : '' ?></div>
            <div style="font-size:0.72rem;color:<?= $done ? '#333' : '#aaa' ?>;margin-top:6px"><?= h($lbl) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Timeline -->
        <div style="border-top:1px solid var(--grey-light);margin-top:20px;padding-top:16px">
          <h3 style="font-size:0.95rem;margin-bottom:12px">History</h3>
          <?php foreach (array_reverse($history) as $h): ?>
          <div style="display:flex;gap:10px;margin-bottom:10px;font-size:0.85rem">
            <span style="color:var(--rose-gold)">●</span>
            <div>
              <strong><?= ucfirst(h($h['status'])) ?></strong>
              <span style="color:#999;font-size:0.78rem">· <?= date('d M Y, g:ia', strtotime($h['created_at'])) ?></span>
              <?php if (!empty($h['note']) && $h['note'] !== 'Imported'): ?><div style="color:#888;font-size:0.8rem"><?= h($h['note']) ?></div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Items -->
        <div style="border-top:1px solid var(--grey-light);margin-top:8px;padding-top:16px">
          <h3 style="font-size:0.95rem;margin-bottom:10px">Items</h3>
          <?php foreach ($items as $it): ?>
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px">
            <span><?= h($it['name']) ?> &times; <?= (int)$it['quantity'] ?></span>
            <span><?= money($it['price'] * $it['quantity']) ?></span>
          </div>
          <?php endforeach; ?>
          <div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid var(--grey-light);margin-top:8px;padding-top:8px">
            <span>Total</span><span><?= money($order['total']) ?></span>
          </div>
          <p style="color:#888;font-size:0.82rem;margin-top:12px">Shipping to <?= h($order['ship_city'] ?: '') ?><?= $order['ship_parish'] ? ', ' . h($order['ship_parish']) : '' ?>, <?= h($order['ship_country'] ?? 'Jamaica') ?>.</p>
        </div>
      </div>
    <?php endif; ?>

    <p style="margin-top:20px;font-size:0.85rem;color:#888">Need help? <a href="<?= asset_base() ?>/contact.php" style="color:var(--rose-gold)">Contact us</a>.</p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
