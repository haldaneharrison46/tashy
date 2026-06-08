<?php
// admin/slip.php — printable Invoice / Pick slip / Packing slip for an order.
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$id   = (int)($_GET['id'] ?? 0);
$type = in_array($_GET['type'] ?? '', ['invoice','pick','packing'], true) ? $_GET['type'] : 'invoice';

$pdo = db();
$st  = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { http_response_code(404); exit('Order not found.'); }

$it = $pdo->prepare("SELECT oi.*, p.sku FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
$it->execute([$id]);
$items = $it->fetchAll();

$titles = ['invoice' => 'Invoice', 'pick' => 'Pick Slip', 'packing' => 'Packing Slip'];
$j = fn($n) => 'J$' . number_format((float)$n, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $titles[$type] ?> · <?= h($o['order_number']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; background: #f0f0f0; }
  .toolbar { padding: 12px 16px; background: #1a1a1a; color: #fff; display: flex; gap: 8px; align-items: center; }
  .toolbar a, .toolbar button { font-size: 0.85rem; padding: 7px 12px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; }
  .toolbar .tabs { display: flex; gap: 6px; margin-left: auto; }
  .toolbar .tab { background: #333; color: #ddd; }
  .toolbar .tab.active { background: #c9956c; color: #fff; }
  .toolbar .print { background: #c9956c; color: #fff; }
  .doc { background: #fff; max-width: 720px; margin: 20px auto; padding: 36px; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
  .doc h1 { font-size: 1.4rem; margin: 0; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 14px; margin-bottom: 18px; }
  .muted { color: #666; font-size: 0.85rem; }
  table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 0.9rem; }
  th { text-align: left; border-bottom: 2px solid #ddd; padding: 8px 6px; font-size: 0.78rem; text-transform: uppercase; color: #888; }
  td { padding: 9px 6px; border-bottom: 1px solid #eee; }
  .right { text-align: right; }
  .totals { max-width: 280px; margin-left: auto; font-size: 0.9rem; }
  .totals .row { display: flex; justify-content: space-between; padding: 3px 0; }
  .totals .grand { font-weight: 700; font-size: 1.05rem; border-top: 2px solid #222; margin-top: 6px; padding-top: 8px; }
  .box { border: 1px solid #ddd; border-radius: 8px; padding: 14px; margin-top: 14px; }
  .sign { margin-top: 40px; display: flex; gap: 40px; }
  .sign div { flex: 1; border-top: 1px solid #999; padding-top: 6px; font-size: 0.8rem; color: #666; }
  @media print { .toolbar { display: none; } body { background: #fff; } .doc { box-shadow: none; margin: 0; max-width: none; } }
</style>
</head>
<body>
<div class="toolbar">
  <a href="orders.php?id=<?= (int)$o['id'] ?>" style="background:#444;color:#fff">&larr; Order</a>
  <button class="print" onclick="window.print()">🖨 Print</button>
  <div class="tabs">
    <?php foreach ($titles as $k => $lbl): ?>
    <a class="tab <?= $k === $type ? 'active' : '' ?>" href="slip.php?id=<?= (int)$o['id'] ?>&type=<?= $k ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="doc">
  <div class="head">
    <div>
      <h1><?= SITE_NAME ?></h1>
      <div class="muted"><?= h(SITE_ADDRESS) ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:1.1rem;font-weight:700"><?= $titles[$type] ?></div>
      <div class="muted"><?= h($o['order_number']) ?></div>
      <div class="muted"><?= date('d M Y', strtotime($o['created_at'])) ?></div>
    </div>
  </div>

  <!-- Ship to -->
  <div style="display:flex;justify-content:space-between;gap:20px;font-size:0.88rem">
    <div>
      <strong>Ship to</strong><br>
      <?= h($o['ship_name']) ?><br>
      <?php if ($o['ship_address']): ?><?= nl2br(h($o['ship_address'])) ?><br><?php endif; ?>
      <?= h($o['ship_city']) ?><?= $o['ship_parish'] ? ', ' . h($o['ship_parish']) : '' ?><br>
      <?= h($o['ship_country'] ?? 'Jamaica') ?><br>
      <?php if ($o['ship_phone']): ?>📞 <?= h($o['ship_phone']) ?><?php endif; ?>
    </div>
    <div style="text-align:right">
      <strong>Order</strong><br>
      <span class="muted">Status: <?= ucfirst(h($o['status'])) ?></span><br>
      <span class="muted">Channel: <?= strtoupper(h($o['channel'] ?? 'online')) ?></span><br>
      <?php if ($type === 'invoice'): ?><span class="muted">Payment: <?= strtoupper(h($o['payment_method'] ?? 'cod')) ?></span><?php endif; ?>
    </div>
  </div>

  <!-- Items -->
  <table>
    <thead>
      <tr>
        <th style="width:46px">Qty</th>
        <?php if ($type !== 'packing'): ?><th>SKU</th><?php endif; ?>
        <th>Item</th>
        <?php if ($type === 'invoice'): ?><th class="right">Price</th><th class="right">Total</th><?php endif; ?>
        <?php if ($type === 'pick'): ?><th style="width:90px">Picked ✓</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i): ?>
      <tr>
        <td><strong><?= (int)$i['quantity'] ?></strong></td>
        <?php if ($type !== 'packing'): ?><td class="muted"><?= h($i['sku'] ?: '—') ?></td><?php endif; ?>
        <td><?= h($i['name']) ?><?php if ($i['brand']): ?> <span class="muted">· <?= h($i['brand']) ?></span><?php endif; ?></td>
        <?php if ($type === 'invoice'): ?>
          <td class="right"><?= $j($i['price']) ?></td>
          <td class="right"><?= $j($i['price'] * $i['quantity']) ?></td>
        <?php endif; ?>
        <?php if ($type === 'pick'): ?><td style="border:1px solid #ccc;width:90px"></td><?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($type === 'invoice'): ?>
  <div class="totals">
    <div class="row"><span>Subtotal</span><span><?= $j($o['subtotal']) ?></span></div>
    <?php if (($o['discount'] ?? 0) > 0): ?><div class="row"><span>Discount</span><span>−<?= $j($o['discount']) ?></span></div><?php endif; ?>
    <div class="row"><span>Shipping</span><span><?= $o['shipping'] > 0 ? $j($o['shipping']) : 'FREE' ?></span></div>
    <div class="row"><span>GCT (15%)</span><span><?= $j($o['tax']) ?></span></div>
    <div class="row grand"><span>Total</span><span><?= $j($o['total']) ?></span></div>
    <?php if ($o['amount_paid'] !== null): ?>
    <div class="row" style="margin-top:6px"><span>Paid</span><span><?= $j($o['amount_paid']) ?></span></div>
    <?php $change = (float)$o['amount_paid'] - (float)$o['total']; if ($change > 0): ?>
    <div class="row"><span>Change</span><span><?= $j($change) ?></span></div>
    <?php endif; endif; ?>
  </div>
  <p class="muted" style="text-align:center;margin-top:24px">Thank you for shopping with <?= SITE_NAME ?>!</p>
  <?php endif; ?>

  <?php if ($type === 'packing' && $o['notes']): ?>
  <div class="box"><strong>Note:</strong> <?= h($o['notes']) ?></div>
  <?php endif; ?>

  <?php if ($type === 'pick'): ?>
  <div class="sign"><div>Picked by</div><div>Checked by</div></div>
  <?php endif; ?>
  <?php if ($type === 'packing'): ?>
  <div class="sign"><div>Packed by</div><div>Date</div></div>
  <?php endif; ?>
</div>
</body>
</html>
