<?php
$pageTitle = 'Inventory';
require_once __DIR__ . '/header.php';

$pdo = db();

$ready = (function () { try { db()->query('SELECT 1 FROM inventory_receipts LIMIT 1'); return true; } catch (Throwable $e) { return false; } })();
if (!$ready) {
    echo '<div class="badge-warning" style="padding:14px 18px;border-radius:8px;display:block">Inventory tables aren\'t set up yet. Go to <a href="settings.php#data" style="color:inherit;text-decoration:underline">Settings → Database updates</a> and click <strong>Apply database updates</strong>.</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── Receive stock ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_stock'])) {
    csrf_check();
    $lines    = json_decode($_POST['lines_json'] ?? '[]', true) ?: [];
    $vendorId = (int)($_POST['vendor_id'] ?? 0) ?: null;
    $ref      = trim($_POST['reference'] ?? '');
    $note     = trim($_POST['note'] ?? '');

    $resolved = []; $total = 0.0; $err = '';
    foreach ($lines as $ln) {
        $pid  = (int)($ln['id'] ?? 0);
        $qty  = (int)($ln['qty'] ?? 0);
        $cost = max(0, (float)($ln['cost'] ?? 0));
        if ($pid <= 0 || $qty <= 0) continue;
        $st = $pdo->prepare('SELECT id, name FROM products WHERE id = ?');
        $st->execute([$pid]); $p = $st->fetch();
        if (!$p) { $err = 'A selected product no longer exists.'; break; }
        $resolved[] = ['id' => $pid, 'name' => $p['name'], 'qty' => $qty, 'cost' => $cost];
        $total += $qty * $cost;
    }
    if (!$err && empty($resolved)) $err = 'Add at least one product with a quantity.';

    if ($err) { flash('error', $err); redirect(asset_base() . '/admin/inventory.php'); }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO inventory_receipts (vendor_id, reference, note, total_cost, received_by) VALUES (?,?,?,?,?)')
            ->execute([$vendorId, $ref ?: null, $note ?: null, $total, current_user()['name']]);
        $rid = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO inventory_receipt_items (receipt_id, product_id, name, quantity, unit_cost) VALUES (?,?,?,?,?)');
        $upd = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        foreach ($resolved as $r) {
            $ins->execute([$rid, $r['id'], $r['name'], $r['qty'], $r['cost']]);
            $upd->execute([$r['qty'], $r['id']]);
        }
        $pdo->commit();
        flash('success', 'Stock received — ' . count($resolved) . ' item(s) updated.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Receiving failed: ' . $e->getMessage());
    }
    redirect(asset_base() . '/admin/inventory.php');
}

$vendors  = get_vendors(true);
$products = $pdo->query("SELECT id, name, sku, stock FROM products WHERE active = 1 ORDER BY name")->fetchAll();
$jsProducts = array_map(fn($p) => ['id'=>(int)$p['id'],'name'=>$p['name'],'sku'=>$p['sku'] ?? '','stock'=>(int)$p['stock']], $products);

$receipts = $pdo->query("SELECT r.*, v.name AS vendor_name,
    (SELECT COALESCE(SUM(quantity),0) FROM inventory_receipt_items i WHERE i.receipt_id=r.id) AS qty
    FROM inventory_receipts r LEFT JOIN vendors v ON r.vendor_id=v.id ORDER BY r.id DESC LIMIT 50")->fetchAll();

$ok = flash('success'); $err = flash('error');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

<div class="admin-card" style="max-width:820px">
  <h2>Receive Stock</h2>
  <div class="admin-form-grid">
    <div class="form-group"><label class="form-label">Vendor / Supplier</label>
      <select id="invVendor" class="form-control">
        <option value="">— none —</option>
        <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>"><?= h($v['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label class="form-label">Reference / Invoice #</label>
      <input type="text" id="invRef" class="form-control" placeholder="optional"></div>
    <div class="form-group full"><label class="form-label">Note</label>
      <input type="text" id="invNote" class="form-control" placeholder="optional"></div>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:4px">
    <div style="flex:2;min-width:200px">
      <label class="form-label">Product</label>
      <select id="invProduct" class="form-control">
        <option value="">Choose a product…</option>
        <?php foreach ($jsProducts as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['name']) ?> (stock <?= $p['stock'] ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div style="width:90px"><label class="form-label">Qty</label><input type="number" id="invQty" class="form-control" value="1" min="1"></div>
    <div style="width:120px"><label class="form-label">Unit cost</label><input type="number" id="invCost" class="form-control" value="0" min="0" step="0.01"></div>
    <button type="button" class="btn btn-outline" id="invAdd">+ Add</button>
  </div>

  <table class="admin-table" style="margin-top:16px">
    <thead><tr><th>Product</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit cost</th><th style="text-align:right">Line</th><th></th></tr></thead>
    <tbody id="invRows"><tr id="invEmpty"><td colspan="5" style="text-align:center;color:#999;padding:18px">No items added yet.</td></tr></tbody>
    <tfoot><tr><td colspan="3" style="text-align:right;font-weight:700">Total cost</td><td style="text-align:right;font-weight:700" id="invTotal">J$0.00</td><td></td></tr></tfoot>
  </table>

  <form method="post" id="invForm" style="margin-top:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="receive_stock" value="1">
    <input type="hidden" name="vendor_id" id="fVendor">
    <input type="hidden" name="reference" id="fRef">
    <input type="hidden" name="note" id="fNote">
    <input type="hidden" name="lines_json" id="fLines">
    <button type="submit" class="btn btn-primary" id="invSubmit" disabled>Receive Stock</button>
  </form>
</div>

<div class="admin-card" style="padding:0;overflow:hidden;max-width:820px">
  <div style="padding:14px 18px;font-weight:700;border-bottom:1px solid var(--grey-light)">Recent Receipts</div>
  <table class="admin-table">
    <thead><tr><th>Date</th><th>Vendor</th><th>Reference</th><th style="text-align:center">Items</th><th style="text-align:right">Cost</th><th>By</th></tr></thead>
    <tbody>
    <?php foreach ($receipts as $r): ?>
    <tr>
      <td style="font-size:0.82rem"><?= date('d M y, g:ia', strtotime($r['created_at'])) ?></td>
      <td><?= h($r['vendor_name'] ?: '—') ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($r['reference'] ?: '—') ?></td>
      <td style="text-align:center"><?= (int)$r['qty'] ?></td>
      <td style="text-align:right"><?= money($r['total_cost']) ?></td>
      <td style="color:#888;font-size:0.82rem"><?= h($r['received_by'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($receipts)): ?><tr><td colspan="6" style="text-align:center;color:#999;padding:24px">No receipts yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<script>
const INV_PRODUCTS = <?= json_encode($jsProducts) ?>;
const money = n => 'J$' + (Math.round(n*100)/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
let lines = [];
function render(){
  const tb = document.getElementById('invRows');
  document.getElementById('invEmpty').style.display = lines.length ? 'none' : '';
  tb.querySelectorAll('tr:not(#invEmpty)').forEach(r => r.remove());
  let total = 0;
  lines.forEach((l, i) => {
    total += l.qty * l.cost;
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${l.name.replace(/</g,'&lt;')}</td><td style="text-align:center">${l.qty}</td>
      <td style="text-align:right">${money(l.cost)}</td><td style="text-align:right">${money(l.qty*l.cost)}</td>
      <td><button type="button" class="tk-rm" data-i="${i}" style="color:#c0392b;background:none;border:none;cursor:pointer">✕</button></td>`;
    tb.appendChild(tr);
  });
  document.getElementById('invTotal').textContent = money(total);
  document.getElementById('invSubmit').disabled = lines.length === 0;
  tb.querySelectorAll('button[data-i]').forEach(b => b.onclick = () => { lines.splice(+b.dataset.i,1); render(); });
}
document.getElementById('invAdd').onclick = () => {
  const pid = +document.getElementById('invProduct').value;
  const qty = Math.max(1, +document.getElementById('invQty').value || 0);
  const cost = Math.max(0, +document.getElementById('invCost').value || 0);
  const p = INV_PRODUCTS.find(x => x.id === pid);
  if (!p) return;
  const ex = lines.find(l => l.id === pid);
  if (ex) { ex.qty += qty; ex.cost = cost; } else lines.push({id:pid, name:p.name, qty, cost});
  document.getElementById('invQty').value = 1; document.getElementById('invCost').value = 0;
  render();
};
document.getElementById('invForm').addEventListener('submit', e => {
  if (!lines.length) { e.preventDefault(); return; }
  document.getElementById('fVendor').value = document.getElementById('invVendor').value;
  document.getElementById('fRef').value = document.getElementById('invRef').value.trim();
  document.getElementById('fNote').value = document.getElementById('invNote').value.trim();
  document.getElementById('fLines').value = JSON.stringify(lines);
});
render();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
