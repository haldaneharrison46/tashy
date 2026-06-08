<?php
$pageTitle = 'Order Board';
require_once __DIR__ . '/header.php';
require_once dirname(__DIR__) . '/includes/orders.php';

$pdo = db();
$statuses = order_statuses();
$colors   = order_status_colors();
$labels   = ['pending'=>'New','processing'=>'Processing','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled'];

// Recent orders grouped by status (cap so the board stays light).
$rows = $pdo->query("SELECT o.*, u.name AS uname FROM orders o LEFT JOIN users u ON o.user_id=u.id
                     ORDER BY o.created_at DESC LIMIT 300")->fetchAll();
$byStatus = array_fill_keys($statuses, []);
foreach ($rows as $r) { $byStatus[$r['status']][] = $r; }
?>
<style>
.kb-wrap { display:flex; gap:14px; overflow-x:auto; padding-bottom:8px; align-items:flex-start; }
.kb-col { background:#f4f4f6; border-radius:12px; padding:10px; width:250px; flex:0 0 250px; min-height:120px; }
.kb-col.dragover { outline:2px dashed var(--rose-gold); outline-offset:-4px; background:#faf3ec; }
.kb-col-head { display:flex;align-items:center;justify-content:space-between; font-weight:700; font-size:0.82rem; padding:4px 6px 10px; }
.kb-count { background:#fff; border-radius:20px; padding:1px 9px; font-size:0.72rem; color:#666; }
.kb-card { background:#fff; border:1px solid var(--grey-light); border-radius:9px; padding:10px; margin-bottom:9px; cursor:grab; font-size:0.82rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
.kb-card:active { cursor:grabbing; }
.kb-card.dragging { opacity:.5; }
.kb-card .kb-num { font-weight:700; }
.kb-card .kb-meta { color:#888; font-size:0.74rem; margin-top:2px; }
.kb-card a { color:inherit; text-decoration:none; }
</style>

<p style="color:#888;font-size:0.85rem;margin-bottom:14px">Drag an order between columns to update its status. The customer is emailed automatically on every move.</p>

<div class="kb-wrap" id="board">
  <?php foreach ($statuses as $st): ?>
  <div class="kb-col" data-status="<?= $st ?>">
    <div class="kb-col-head">
      <span><span class="badge badge-<?= $colors[$st] ?? 'grey' ?>" style="margin-right:6px"><?= h($labels[$st] ?? ucfirst($st)) ?></span></span>
      <span class="kb-count"><?= count($byStatus[$st]) ?></span>
    </div>
    <div class="kb-list" data-status="<?= $st ?>">
      <?php foreach ($byStatus[$st] as $o): ?>
      <div class="kb-card" draggable="true" data-id="<?= (int)$o['id'] ?>">
        <div class="kb-num"><a href="orders.php?id=<?= (int)$o['id'] ?>"><?= h($o['order_number']) ?></a>
          <?php if (($o['channel'] ?? '') === 'pos'): ?><span class="badge badge-info" style="font-size:0.6rem">POS</span><?php endif; ?>
          <?php if (!empty($o['followup_channel'])): ?><span title="Tagged for follow-up">🏷️</span><?php endif; ?>
        </div>
        <div class="kb-meta"><?= h($o['uname'] ?? $o['ship_name']) ?> · <?= money($o['total']) ?></div>
        <div class="kb-meta"><?= date('d M, g:ia', strtotime($o['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
let dragId = null;

document.querySelectorAll('.kb-card').forEach(initCard);
function initCard(card){
  card.addEventListener('dragstart', e => { dragId = card.dataset.id; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
  card.addEventListener('dragend',   () => card.classList.remove('dragging'));
}

document.querySelectorAll('.kb-col').forEach(col => {
  const list = col.querySelector('.kb-list');
  col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('dragover'); });
  col.addEventListener('dragleave', () => col.classList.remove('dragover'));
  col.addEventListener('drop', async e => {
    e.preventDefault(); col.classList.remove('dragover');
    const status = col.dataset.status;
    const card = document.querySelector('.kb-card[data-id="'+dragId+'"]');
    if (!card) return;
    const fromList = card.parentElement;
    list.appendChild(card);                         // optimistic move
    updateCounts();
    try {
      const r = await fetch('<?= SITE_URL ?>/api/order_status.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ csrf: CSRF, order_id: dragId, status })
      });
      const d = await r.json();
      if (!d.ok) { fromList.appendChild(card); updateCounts(); alert(d.error || 'Could not update.'); }
    } catch (err) { fromList.appendChild(card); updateCounts(); alert('Connection error.'); }
  });
});

function updateCounts(){
  document.querySelectorAll('.kb-col').forEach(c => {
    c.querySelector('.kb-count').textContent = c.querySelectorAll('.kb-card').length;
  });
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
