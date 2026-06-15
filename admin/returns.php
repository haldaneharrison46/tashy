<?php
$pageTitle = 'Returns / RMA';
require_once __DIR__ . '/header.php';

$pdo    = db();
$action = $_GET['action'] ?? '';
$viewId = (int)($_GET['id'] ?? 0);
$rStatuses = ['requested','approved','received','refunded','rejected'];
$rColors   = ['requested'=>'warning','approved'=>'info','received'=>'info','refunded'=>'success','rejected'=>'danger'];

/* ── Create return ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_return'])) {
    csrf_check();
    $oid    = (int)($_POST['order_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $refund = max(0, (float)($_POST['refund_amount'] ?? 0));
    $qtys   = (array)($_POST['qty'] ?? []);

    $lines = [];
    if ($oid) {
        $oi = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
        $oi->execute([$oid]);
        foreach ($oi->fetchAll() as $row) {
            $q = (int)($qtys[$row['id']] ?? 0);
            if ($q > 0) $lines[] = ['product_id'=>$row['product_id'],'name'=>$row['name'],'price'=>$row['price'],'quantity'=>min($q,(int)$row['quantity'])];
        }
    }
    if (empty($lines)) { flash('error','Select at least one item (quantity) to return.'); redirect(asset_base() . '/admin/returns.php?action=new&order_id='.$oid); }

    $rma = 'RMA-' . strtoupper(substr(uniqid(), -6));
    $pdo->prepare("INSERT INTO returns (order_id, rma_number, reason, refund_amount, created_by) VALUES (?,?,?,?,?)")
        ->execute([$oid ?: null, $rma, $reason ?: null, $refund, current_user()['name']]);
    $rid = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare("INSERT INTO return_items (return_id, product_id, name, price, quantity) VALUES (?,?,?,?,?)");
    foreach ($lines as $l) $ins->execute([$rid, $l['product_id'], $l['name'], $l['price'], $l['quantity']]);
    flash('success', 'Return ' . $rma . ' created.');
    redirect(asset_base() . '/admin/returns.php?id='.$rid);
}

/* ── Update status ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_return'])) {
    csrf_check();
    $rid = (int)$_POST['return_id'];
    $stt = in_array($_POST['status'] ?? '', $rStatuses, true) ? $_POST['status'] : 'requested';
    $refund = max(0, (float)($_POST['refund_amount'] ?? 0));
    $pdo->prepare("UPDATE returns SET status=?, refund_amount=? WHERE id=?")->execute([$stt, $refund, $rid]);
    flash('success', 'Return updated.');
    redirect(asset_base() . '/admin/returns.php?id='.$rid);
}

/* ── Restock returned items ────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_return'])) {
    csrf_check();
    $rid = (int)$_POST['return_id'];
    $r = $pdo->prepare("SELECT * FROM returns WHERE id=?"); $r->execute([$rid]); $ret = $r->fetch();
    if ($ret && !$ret['restocked']) {
        $ri = $pdo->prepare("SELECT * FROM return_items WHERE return_id=?"); $ri->execute([$rid]);
        $upd = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        foreach ($ri->fetchAll() as $l) { if ($l['product_id']) $upd->execute([(int)$l['quantity'], (int)$l['product_id']]); }
        $pdo->prepare("UPDATE returns SET restocked=1 WHERE id=?")->execute([$rid]);
        flash('success', 'Items restocked to inventory.');
    }
    redirect(asset_base() . '/admin/returns.php?id='.$rid);
}

/* ── New return form ───────────────────────── */
if ($action === 'new') {
    $oid = (int)($_GET['order_id'] ?? 0);
    $onum = trim($_GET['order'] ?? '');
    if (!$oid && $onum) { $q=$pdo->prepare("SELECT id FROM orders WHERE order_number=?"); $q->execute([$onum]); $oid=(int)$q->fetchColumn(); }
    $order = null; $items = [];
    if ($oid) { $q=$pdo->prepare("SELECT * FROM orders WHERE id=?"); $q->execute([$oid]); $order=$q->fetch();
        if ($order) { $qi=$pdo->prepare("SELECT * FROM order_items WHERE order_id=?"); $qi->execute([$oid]); $items=$qi->fetchAll(); } }
    $err = flash('error');
    ?>
    <div style="margin-bottom:18px"><a href="returns.php" style="color:var(--rose-gold)">&larr; Back to Returns</a></div>
    <?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>
    <?php if (!$order): ?>
    <div class="admin-card" style="max-width:460px">
      <h2>Start a Return</h2>
      <form method="get"><input type="hidden" name="action" value="new">
        <div class="form-group"><label class="form-label">Order number</label>
          <input type="text" name="order" class="form-control" placeholder="TK-AB12CD-2026" required></div>
        <button class="btn btn-primary">Find order</button>
      </form>
    </div>
    <?php else: ?>
    <div class="admin-card" style="max-width:640px">
      <h2>New Return — <?= h($order['order_number']) ?></h2>
      <p style="color:#888;font-size:0.85rem;margin-bottom:14px"><?= h($order['ship_name']) ?> · placed <?= date('d M Y', strtotime($order['created_at'])) ?></p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="create_return" value="1">
        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
        <table class="admin-table">
          <thead><tr><th>Item</th><th>Ordered</th><th>Return qty</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
          <tr>
            <td><?= h($it['name']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><input type="number" name="qty[<?= (int)$it['id'] ?>]" min="0" max="<?= (int)$it['quantity'] ?>" value="0" class="form-control" style="width:80px"></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="admin-form-grid" style="margin-top:12px">
          <div class="form-group full"><label class="form-label">Reason</label>
            <textarea name="reason" class="form-control" rows="2" placeholder="Damaged, wrong item, changed mind…"></textarea></div>
          <div class="form-group"><label class="form-label">Refund amount (J$)</label>
            <input type="number" name="refund_amount" step="0.01" min="0" class="form-control" value="0"></div>
        </div>
        <button class="btn btn-primary" style="margin-top:12px">Create Return</button>
      </form>
    </div>
    <?php endif; ?>
    <?php require_once __DIR__ . '/footer.php'; exit;
}

/* ── Return detail ─────────────────────────── */
if ($viewId) {
    $q=$pdo->prepare("SELECT r.*, o.order_number FROM returns r LEFT JOIN orders o ON o.id=r.order_id WHERE r.id=?");
    $q->execute([$viewId]); $ret=$q->fetch();
    if (!$ret) { flash('error','Return not found.'); redirect(asset_base() . '/admin/returns.php'); }
    $ri=$pdo->prepare("SELECT * FROM return_items WHERE return_id=?"); $ri->execute([$viewId]); $rItems=$ri->fetchAll();
    $ok=flash('success');
    ?>
    <div style="margin-bottom:18px"><a href="returns.php" style="color:var(--rose-gold)">&larr; Back to Returns</a></div>
    <?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 280px;gap:24px;align-items:start">
      <div class="admin-card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
          <h2 style="margin:0"><?= h($ret['rma_number']) ?></h2>
          <span class="badge badge-<?= $rColors[$ret['status']] ?? 'grey' ?>"><?= ucfirst($ret['status']) ?></span>
          <?php if ($ret['restocked']): ?><span class="badge badge-success">Restocked</span><?php endif; ?>
        </div>
        <?php if ($ret['order_number']): ?><p style="color:#888;font-size:0.85rem"><a href="orders.php?id=<?= (int)$ret['order_id'] ?>" style="color:var(--rose-gold)"><?= h($ret['order_number']) ?></a> · <?= date('d M Y', strtotime($ret['created_at'])) ?></p><?php endif; ?>
        <table class="admin-table" style="margin-top:12px">
          <thead><tr><th>Item</th><th>Qty</th><th style="text-align:right">Value</th></tr></thead>
          <tbody>
          <?php foreach ($rItems as $l): ?>
          <tr><td><?= h($l['name']) ?></td><td>×<?= (int)$l['quantity'] ?></td><td style="text-align:right"><?= money($l['price']*$l['quantity']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($ret['reason']): ?><p style="margin-top:10px;font-size:0.85rem"><strong>Reason:</strong> <?= h($ret['reason']) ?></p><?php endif; ?>
      </div>
      <div>
        <div class="admin-card" style="margin-bottom:16px">
          <h2>Manage</h2>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="update_return" value="1">
            <input type="hidden" name="return_id" value="<?= (int)$ret['id'] ?>">
            <select name="status" class="form-control" style="margin-bottom:8px">
              <?php foreach ($rStatuses as $s): ?><option value="<?= $s ?>" <?= $ret['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select>
            <input type="number" name="refund_amount" step="0.01" min="0" class="form-control" value="<?= h(number_format($ret['refund_amount'],2,'.','')) ?>" style="margin-bottom:8px" placeholder="Refund J$">
            <button class="btn btn-primary btn-sm" style="width:100%">Update</button>
          </form>
        </div>
        <?php if (!$ret['restocked']): ?>
        <div class="admin-card">
          <h2>Restock</h2>
          <p style="font-size:0.82rem;color:#888;margin-bottom:10px">Add the returned quantities back into inventory.</p>
          <form method="post" onsubmit="return confirm('Add these items back to stock?')">
            <?= csrf_field() ?>
            <input type="hidden" name="restock_return" value="1">
            <input type="hidden" name="return_id" value="<?= (int)$ret['id'] ?>">
            <button class="btn btn-outline btn-sm" style="width:100%">↩ Restock items</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php require_once __DIR__ . '/footer.php'; exit;
}

/* ── Returns list ──────────────────────────── */
$returns = $pdo->query("SELECT r.*, o.order_number FROM returns r LEFT JOIN orders o ON o.id=r.order_id ORDER BY r.id DESC LIMIT 200")->fetchAll();
$ok=flash('success'); $err=flash('error');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>
<div style="display:flex;align-items:center;margin-bottom:18px">
  <h2 style="font-size:1.05rem">Returns</h2>
  <a href="returns.php?action=new" class="btn btn-primary" style="margin-left:auto">+ New Return</a>
</div>
<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr><th>RMA</th><th>Order</th><th>Status</th><th>Refund</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($returns as $r): ?>
    <tr>
      <td style="font-weight:600"><?= h($r['rma_number']) ?></td>
      <td><?= $r['order_number'] ? h($r['order_number']) : '—' ?></td>
      <td><span class="badge badge-<?= $rColors[$r['status']] ?? 'grey' ?>"><?= ucfirst($r['status']) ?></span><?php if ($r['restocked']): ?> <span class="badge badge-success" style="font-size:0.6rem">restocked</span><?php endif; ?></td>
      <td><?= money($r['refund_amount']) ?></td>
      <td style="color:#888;font-size:0.8rem"><?= date('d M y', strtotime($r['created_at'])) ?></td>
      <td><a href="returns.php?id=<?= (int)$r['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem">Open →</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($returns)): ?><tr><td colspan="6" style="text-align:center;color:#999;padding:28px">No returns yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
