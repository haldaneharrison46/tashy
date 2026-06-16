<?php
// admin/slip.php — printable order documents:
//   invoice · pick slip · packing slip · receipt (graphic/HQ) ·
//   receipt_text (basic text for dot-matrix) · label (shipping/packing label)
// Every document carries a Code128 barcode + QR code (tracking URL).
// Toolbar actions: print, download (.txt / save-as-PDF), email to customer, WhatsApp.
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mail.php';
require_admin();

$id    = (int)($_GET['id'] ?? 0);
$types = ['invoice','pick','packing','receipt','receipt_text','label'];
$type  = in_array($_GET['type'] ?? '', $types, true) ? $_GET['type'] : 'invoice';

$pdo = db();
$st  = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { http_response_code(404); exit('Order not found.'); }

$it = $pdo->prepare("SELECT oi.*, p.sku FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
$it->execute([$id]);
$items = $it->fetchAll();

$j        = fn($n) => 'J$' . number_format((float)$n, 2);
$trackUrl = (defined('SITE_URL') ? SITE_URL : '') . '/track.php?order=' . urlencode($o['order_number']);

$titles = [
  'invoice'      => 'Invoice',
  'pick'         => 'Pick Slip',
  'packing'      => 'Packing Slip',
  'receipt'      => 'Receipt',
  'receipt_text' => 'Receipt (Text)',
  'label'        => 'Shipping Label',
];

// ── Email the receipt to the customer ─────────────────────────
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email') {
    csrf_check();
    $to = trim((string)($o['ship_email'] ?? ''));
    if ($to === '') {
        $flash = 'This order has no customer email address.';
    } else {
        $rows = '';
        foreach ($items as $i) {
            $rows .= '<tr><td style="padding:4px 8px;border-bottom:1px solid #eee">' . (int)$i['quantity'] . '× ' . h($i['name']) . '</td>'
                   . '<td style="padding:4px 8px;border-bottom:1px solid #eee;text-align:right">' . $j($i['price'] * $i['quantity']) . '</td></tr>';
        }
        $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;color:#222">'
              . '<h2 style="color:#c9956c">' . h(SITE_NAME) . '</h2>'
              . '<p>Receipt for order <strong>' . h($o['order_number']) . '</strong> — ' . date('d M Y', strtotime($o['created_at'])) . '</p>'
              . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $rows . '</table>'
              . '<p style="text-align:right;font-size:16px;margin-top:10px"><strong>Total: ' . $j($o['total']) . '</strong></p>'
              . '<p>Track your order: <a href="' . h($trackUrl) . '">' . h($trackUrl) . '</a></p>'
              . '<p style="color:#888">Thank you for shopping with ' . h(SITE_NAME) . '!</p></div>';
        $ok = tk_mail($to, 'Your ' . SITE_NAME . ' receipt — ' . $o['order_number'], $html);
        $flash = $ok ? ('Receipt emailed to ' . $to . '.') : 'Email could not be sent (mail server issue).';
    }
}

// WhatsApp share link (uses the shipping phone if present)
$waPhone = preg_replace('/\D+/', '', (string)($o['ship_phone'] ?? ''));
$waMsg   = rawurlencode(SITE_NAME . " — Order " . $o['order_number'] . "\nTotal: " . $j($o['total']) . "\nTrack: " . $trackUrl);
$waUrl   = 'https://wa.me/' . $waPhone . '?text=' . $waMsg;

$isText  = ($type === 'receipt_text');
$isLabel = ($type === 'label');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $titles[$type] ?> · <?= h($o['order_number']) ?></title>
<script src="../assets/vendor/jsbarcode.min.js"></script>
<script src="../assets/vendor/qrcode.min.js"></script>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; background: #f0f0f0; }
  .toolbar { padding: 12px 16px; background: #1a1a1a; color: #fff; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .toolbar a, .toolbar button { font-size: 0.82rem; padding: 7px 11px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; color: #fff; }
  .toolbar .b-grey { background: #444; } .toolbar .b-go { background: #c9956c; } .toolbar .b-wa { background: #25d366; color:#04210f; }
  .toolbar .tabs { display: flex; gap: 5px; margin-left: auto; flex-wrap: wrap; }
  .toolbar .tab { background: #333; color: #ddd; } .toolbar .tab.active { background: #c9956c; color: #fff; }
  .flash { background:#e7f6ec; color:#176b3a; padding:10px 16px; font-size:0.9rem; }
  .flash.err { background:#fdecec; color:#9c2b2b; }

  /* shared doc bits */
  .doc { background:#fff; margin:20px auto; box-shadow:0 2px 12px rgba(0,0,0,.1); }
  .muted { color:#666; font-size:0.85rem; }
  .right { text-align:right; }
  .codes { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-top:18px; padding-top:12px; border-top:1px dashed #bbb; }
  .codes .qrcode img { display:block; }
  table { width:100%; border-collapse:collapse; }

  /* graphic docs (invoice/pick/packing/receipt) */
  .graphic { max-width:720px; padding:36px; }
  .graphic.narrow { max-width:420px; padding:26px; }
  .graphic h1 { font-size:1.4rem; margin:0; }
  .graphic .head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #222; padding-bottom:14px; margin-bottom:18px; }
  .graphic th { text-align:left; border-bottom:2px solid #ddd; padding:8px 6px; font-size:0.74rem; text-transform:uppercase; color:#888; }
  .graphic td { padding:9px 6px; border-bottom:1px solid #eee; font-size:0.9rem; }
  .totals { max-width:280px; margin-left:auto; font-size:0.9rem; }
  .totals .row { display:flex; justify-content:space-between; padding:3px 0; }
  .totals .grand { font-weight:700; font-size:1.05rem; border-top:2px solid #222; margin-top:6px; padding-top:8px; }
  .box { border:1px solid #ddd; border-radius:8px; padding:14px; margin-top:14px; }
  .sign { margin-top:40px; display:flex; gap:40px; }
  .sign div { flex:1; border-top:1px solid #999; padding-top:6px; font-size:0.8rem; color:#666; }

  /* text receipt — narrow monospace for dot-matrix / thermal */
  .ticket { width:80mm; max-width:340px; padding:8px 10px; font-family:"Courier New",monospace; font-size:12px; line-height:1.45; color:#000; }
  .ticket .c { text-align:center; } .ticket .b { font-weight:bold; }
  .ticket hr { border:none; border-top:1px dashed #000; margin:6px 0; }
  .ticket table { font-size:12px; } .ticket td { padding:1px 0; vertical-align:top; }
  .ticket .codes { border-top:1px dashed #000; }

  /* shipping label */
  .label { width:384px; padding:16px; border:2px solid #000; }
  .label .from { font-size:0.72rem; color:#444; border-bottom:1px solid #999; padding-bottom:6px; margin-bottom:8px; }
  .label .to { font-size:0.7rem; text-transform:uppercase; color:#666; }
  .label .to-name { font-size:1.35rem; font-weight:800; line-height:1.2; }
  .label .to-addr { font-size:1rem; line-height:1.4; margin-top:2px; }

  @media print {
    .toolbar, .flash { display:none; }
    body { background:#fff; }
    .doc { box-shadow:none; margin:0; }
    @page { margin:8mm; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <a class="b-grey" href="orders.php?id=<?= (int)$o['id'] ?>">&larr; Order</a>
  <button class="b-go" onclick="window.print()">🖨 Print</button>
  <?php if ($isText): ?>
  <button class="b-grey" onclick="downloadTxt()">⬇ Save .txt</button>
  <?php else: ?>
  <button class="b-grey" onclick="window.print()" title="Use 'Save as PDF' in the print dialog">⬇ Save PDF</button>
  <?php endif; ?>
  <form method="post" style="display:inline" onsubmit="return confirm('Email this receipt to <?= h($o['ship_email'] ?: 'the customer') ?>?')">
    <?= csrf_field() ?><input type="hidden" name="action" value="email">
    <button class="b-grey" type="submit">✉ Email</button>
  </form>
  <a class="b-wa" href="<?= h($waUrl) ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
  <div class="tabs">
    <?php foreach ($titles as $k => $lbl): ?>
    <a class="tab <?= $k === $type ? 'active' : '' ?>" href="slip.php?id=<?= (int)$o['id'] ?>&type=<?= $k ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php if ($flash): ?><div class="flash <?= str_contains($flash, 'emailed') ? '' : 'err' ?>"><?= h($flash) ?></div><?php endif; ?>

<?php
// reusable codes block (barcode + QR)
$codesBlock = '<div class="codes"><svg id="bc"></svg><div class="qrcode" style="text-align:right"><div id="qr"></div><span class="muted" style="font-size:0.68rem">Scan to track</span></div></div>';
?>

<?php if ($isText): /* ── BASIC TEXT RECEIPT (dot-matrix / thermal) ── */ ?>
<div class="doc ticket" id="doc">
  <div class="c b"><?= strtoupper(h(SITE_NAME)) ?></div>
  <div class="c"><?= h(SITE_ADDRESS) ?></div>
  <hr>
  <div>Order : <?= h($o['order_number']) ?></div>
  <div>Date  : <?= date('d M Y H:i', strtotime($o['created_at'])) ?></div>
  <div>To    : <?= h($o['ship_name']) ?></div>
  <hr>
  <table>
    <?php foreach ($items as $i): ?>
    <tr><td><?= (int)$i['quantity'] ?>x <?= h($i['name']) ?></td><td class="right"><?= $j($i['price'] * $i['quantity']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table>
    <tr><td>Subtotal</td><td class="right"><?= $j($o['subtotal']) ?></td></tr>
    <?php if (($o['discount'] ?? 0) > 0): ?><tr><td>Discount</td><td class="right">-<?= $j($o['discount']) ?></td></tr><?php endif; ?>
    <tr><td>Shipping</td><td class="right"><?= $o['shipping'] > 0 ? $j($o['shipping']) : 'FREE' ?></td></tr>
    <tr><td>GCT 15%</td><td class="right"><?= $j($o['tax']) ?></td></tr>
    <tr class="b"><td>TOTAL</td><td class="right"><?= $j($o['total']) ?></td></tr>
    <?php if ($o['amount_paid'] !== null): ?><tr><td>Paid</td><td class="right"><?= $j($o['amount_paid']) ?></td></tr><?php endif; ?>
  </table>
  <hr>
  <div class="c">Thank you!</div>
  <?= $codesBlock ?>
</div>

<?php elseif ($isLabel): /* ── SHIPPING / PACKING LABEL ── */ ?>
<div class="doc label" id="doc">
  <div class="from">From: <?= h(SITE_NAME) ?> · <?= h(SITE_ADDRESS) ?></div>
  <div class="to">Ship to</div>
  <div class="to-name"><?= h($o['ship_name']) ?></div>
  <div class="to-addr">
    <?php if ($o['ship_address']): ?><?= nl2br(h($o['ship_address'])) ?><br><?php endif; ?>
    <?= h($o['ship_city']) ?><?= $o['ship_parish'] ? ', ' . h($o['ship_parish']) : '' ?><br>
    <?= h($o['ship_country'] ?? 'Jamaica') ?>
    <?php if ($o['ship_phone']): ?><br>📞 <?= h($o['ship_phone']) ?><?php endif; ?>
  </div>
  <div style="margin-top:8px;font-size:0.8rem"><strong>Order:</strong> <?= h($o['order_number']) ?> · <?= (int)array_sum(array_column($items, 'quantity')) ?> item(s)</div>
  <?= $codesBlock ?>
</div>

<?php else: /* ── GRAPHIC DOCS: invoice / pick / packing / receipt ── */ ?>
<div class="doc graphic <?= $type === 'receipt' ? 'narrow' : '' ?>" id="doc">
  <div class="head">
    <div>
      <h1><?= h(SITE_NAME) ?></h1>
      <div class="muted"><?= h(SITE_ADDRESS) ?></div>
    </div>
    <div class="right">
      <div style="font-size:1.1rem;font-weight:700"><?= $titles[$type] ?></div>
      <div class="muted"><?= h($o['order_number']) ?></div>
      <div class="muted"><?= date('d M Y', strtotime($o['created_at'])) ?></div>
    </div>
  </div>

  <div style="display:flex;justify-content:space-between;gap:20px;font-size:0.88rem">
    <div>
      <strong>Ship to</strong><br>
      <?= h($o['ship_name']) ?><br>
      <?php if ($o['ship_address']): ?><?= nl2br(h($o['ship_address'])) ?><br><?php endif; ?>
      <?= h($o['ship_city']) ?><?= $o['ship_parish'] ? ', ' . h($o['ship_parish']) : '' ?><br>
      <?= h($o['ship_country'] ?? 'Jamaica') ?>
      <?php if ($o['ship_phone']): ?><br>📞 <?= h($o['ship_phone']) ?><?php endif; ?>
    </div>
    <div class="right">
      <strong>Order</strong><br>
      <span class="muted">Status: <?= ucfirst(h($o['status'])) ?></span><br>
      <span class="muted">Channel: <?= strtoupper(h($o['channel'] ?? 'online')) ?></span>
      <?php if ($type === 'invoice' || $type === 'receipt'): ?><br><span class="muted">Payment: <?= strtoupper(h($o['payment_method'] ?? 'cod')) ?></span><?php endif; ?>
    </div>
  </div>

  <table style="margin:14px 0">
    <thead>
      <tr>
        <th style="width:46px">Qty</th>
        <?php if ($type !== 'packing'): ?><th>SKU</th><?php endif; ?>
        <th>Item</th>
        <?php if ($type === 'invoice' || $type === 'receipt'): ?><th class="right">Price</th><th class="right">Total</th><?php endif; ?>
        <?php if ($type === 'pick'): ?><th style="width:80px">Picked ✓</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i): ?>
      <tr>
        <td><strong><?= (int)$i['quantity'] ?></strong></td>
        <?php if ($type !== 'packing'): ?><td class="muted"><?= h($i['sku'] ?: '—') ?></td><?php endif; ?>
        <td><?= h($i['name']) ?><?php if ($i['brand']): ?> <span class="muted">· <?= h($i['brand']) ?></span><?php endif; ?></td>
        <?php if ($type === 'invoice' || $type === 'receipt'): ?>
          <td class="right"><?= $j($i['price']) ?></td>
          <td class="right"><?= $j($i['price'] * $i['quantity']) ?></td>
        <?php endif; ?>
        <?php if ($type === 'pick'): ?><td style="border:1px solid #ccc"></td><?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($type === 'invoice' || $type === 'receipt'): ?>
  <div class="totals">
    <div class="row"><span>Subtotal</span><span><?= $j($o['subtotal']) ?></span></div>
    <?php if (($o['discount'] ?? 0) > 0): ?><div class="row"><span>Discount</span><span>−<?= $j($o['discount']) ?></span></div><?php endif; ?>
    <div class="row"><span>Shipping</span><span><?= $o['shipping'] > 0 ? $j($o['shipping']) : 'FREE' ?></span></div>
    <?php if (($o['tax'] ?? 0) > 0): ?><div class="row"><span><?= h(tax_display_label()) ?></span><span><?= $j($o['tax']) ?></span></div><?php endif; ?>
    <div class="row grand"><span>Total</span><span><?= $j($o['total']) ?></span></div>
    <?php if ($o['amount_paid'] !== null): ?>
    <div class="row" style="margin-top:6px"><span>Paid</span><span><?= $j($o['amount_paid']) ?></span></div>
    <?php $change = (float)$o['amount_paid'] - (float)$o['total']; if ($change > 0): ?>
    <div class="row"><span>Change</span><span><?= $j($change) ?></span></div>
    <?php endif; endif; ?>
  </div>
  <p class="muted" style="text-align:center;margin-top:18px">Thank you for shopping with <?= h(SITE_NAME) ?>!</p>
  <?php endif; ?>

  <?php if ($type === 'packing' && $o['notes']): ?><div class="box"><strong>Note:</strong> <?= h($o['notes']) ?></div><?php endif; ?>
  <?php if ($type === 'pick'): ?><div class="sign"><div>Picked by</div><div>Checked by</div></div><?php endif; ?>
  <?php if ($type === 'packing'): ?><div class="sign"><div>Packed by</div><div>Date</div></div><?php endif; ?>

  <?= $codesBlock ?>
</div>
<?php endif; ?>

<script>
  window.addEventListener('load', function () {
    try {
      JsBarcode('#bc', <?= json_encode($o['order_number']) ?>, { format: 'CODE128', displayValue: true, fontSize: 12, height: 38, margin: 0 });
    } catch (e) {}
    try {
      var qr = qrcode(0, 'M'); qr.addData(<?= json_encode($trackUrl) ?>); qr.make();
      var el = document.getElementById('qr'); if (el) el.innerHTML = qr.createImgTag(3, 0);
    } catch (e) {}
  });
  function downloadTxt() {
    var t = (document.getElementById('doc').innerText || '').replace(/\n{3,}/g, '\n\n');
    var blob = new Blob([t], { type: 'text/plain' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = <?= json_encode($o['order_number']) ?> + '.txt';
    a.click();
  }
</script>
</body>
</html>
