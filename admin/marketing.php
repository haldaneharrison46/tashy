<?php
$pageTitle = 'Marketing';
require_once __DIR__ . '/header.php';
require_once dirname(__DIR__) . '/includes/marketing.php';

$pdo    = db();
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

$PLATFORMS = ['facebook'=>'Facebook','instagram'=>'Instagram','twitter'=>'Twitter / X','whatsapp'=>'WhatsApp','webhook'=>'Automation Webhook'];

/* ── Save AI & connection settings ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_check();
    // Provider + free (OpenAI-compatible) settings
    set_setting('ai_provider', in_array($_POST['ai_provider'] ?? '', ['openai','anthropic'], true) ? $_POST['ai_provider'] : 'openai');
    set_setting('ai_base_url', trim($_POST['ai_base_url'] ?? '') ?: 'https://api.groq.com/openai/v1');
    set_setting('ai_model',    trim($_POST['ai_model'] ?? '') ?: 'llama-3.3-70b-versatile');
    $newAiKey = trim($_POST['ai_api_key'] ?? '');           // blank = keep current
    if ($newAiKey !== '') set_setting('ai_api_key', $newAiKey);
    // keep existing Anthropic key if the field is left blank (so it isn't wiped)
    $newKey = trim($_POST['anthropic_api_key'] ?? '');
    if ($newKey !== '') set_setting('anthropic_api_key', $newKey);
    set_setting('marketing_ai_model', in_array($_POST['marketing_ai_model'] ?? '', ['claude-opus-4-8','claude-sonnet-4-6','claude-haiku-4-5'], true) ? $_POST['marketing_ai_model'] : 'claude-opus-4-8');
    set_setting('brand_voice',        trim($_POST['brand_voice'] ?? ''));
    set_setting('fb_page_id',         trim($_POST['fb_page_id'] ?? ''));
    $newTok = trim($_POST['fb_page_token'] ?? '');
    if ($newTok !== '') set_setting('fb_page_token', $newTok);
    set_setting('social_webhook_url', trim($_POST['social_webhook_url'] ?? ''));
    flash('success', 'Marketing settings saved.');
    redirect(SITE_URL . '/admin/marketing.php');
}

/* ── Save post (draft / schedule / publish) ────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
    csrf_check();
    $id        = (int)($_POST['post_id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $body      = trim($_POST['body'] ?? '');
    $hashtags  = trim($_POST['hashtags'] ?? '');
    $image     = trim($_POST['image'] ?? '');
    $link      = trim($_POST['link_url'] ?? '');
    $prodIdArr = array_values(array_filter(array_map('intval', (array)($_POST['product_ids'] ?? []))));
    $prodIds   = implode(',', $prodIdArr);
    $productId  = $prodIdArr[0] ?? null;
    $platforms = implode(',', array_values(array_intersect(array_keys($PLATFORMS), (array)($_POST['platforms'] ?? []))));
    $mode      = $_POST['mode'] ?? 'draft'; // draft | schedule | publish
    $schedRaw  = trim($_POST['scheduled_at'] ?? '');
    $schedAt   = ($mode === 'schedule' && $schedRaw !== '') ? date('Y-m-d H:i:s', strtotime($schedRaw)) : null;

    if ($body === '') { flash('error', 'Post text is required.'); redirect(SITE_URL . '/admin/marketing.php?action=new'); }

    $status = $mode === 'schedule' ? 'scheduled' : 'draft';
    if ($id) {
        $pdo->prepare('UPDATE marketing_posts SET title=?,body=?,hashtags=?,image=?,link_url=?,platforms=?,product_id=?,product_ids=?,status=?,scheduled_at=? WHERE id=?')
            ->execute([$title,$body,$hashtags,$image,$link,$platforms,$productId,$prodIds,$status,$schedAt,$id]);
    } else {
        $pdo->prepare('INSERT INTO marketing_posts (title,body,hashtags,image,link_url,platforms,product_id,product_ids,status,scheduled_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$title,$body,$hashtags,$image,$link,$platforms,$productId,$prodIds,$status,$schedAt,current_user()['id']]);
        $id = (int)$pdo->lastInsertId();
    }

    if ($mode === 'publish') {
        $st = $pdo->prepare('SELECT * FROM marketing_posts WHERE id=?'); $st->execute([$id]); $post = $st->fetch();
        $pub = marketing_publish($post);
        $pdo->prepare('UPDATE marketing_posts SET status=?, published_at=NOW(), result=? WHERE id=?')
            ->execute([$pub['ok'] ? 'published' : 'failed', json_encode($pub['results']), $id]);
        flash($pub['ok'] ? 'success' : 'error', $pub['ok'] ? 'Post published.' : 'Publishing finished with errors — see the post for details.');
        redirect(SITE_URL . '/admin/marketing.php?id=' . $id);
    }
    flash('success', $mode === 'schedule' ? 'Post scheduled.' : 'Draft saved.');
    redirect(SITE_URL . '/admin/marketing.php?id=' . $id);
}

/* ── Publish an existing post now ──────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_now'])) {
    csrf_check();
    $id = (int)$_POST['post_id'];
    $st = $pdo->prepare('SELECT * FROM marketing_posts WHERE id=?'); $st->execute([$id]); $post = $st->fetch();
    if ($post) {
        $pub = marketing_publish($post);
        $pdo->prepare('UPDATE marketing_posts SET status=?, published_at=NOW(), result=? WHERE id=?')
            ->execute([$pub['ok'] ? 'published' : 'failed', json_encode($pub['results']), $id]);
        flash($pub['ok'] ? 'success' : 'error', $pub['ok'] ? 'Post published.' : 'Publishing finished with errors.');
    }
    redirect(SITE_URL . '/admin/marketing.php?id=' . $id);
}

/* ── Delete ────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM marketing_posts WHERE id=?')->execute([(int)$_POST['post_id']]);
    flash('success', 'Post deleted.');
    redirect(SITE_URL . '/admin/marketing.php');
}

$products = $pdo->query("SELECT id, name, brand, price, image FROM products WHERE active=1 ORDER BY name")->fetchAll();
$aiReady  = ai_available();
$statusColors = ['draft'=>'grey','scheduled'=>'warning','published'=>'success','failed'=>'danger'];

/* ════════════════ COMPOSER (new / edit) ════════════════ */
if ($action === 'new' || ($editId && ($action === 'edit'))) {
    $p = ['id'=>0,'title'=>'','body'=>'','hashtags'=>'','image'=>'','link_url'=>'','platforms'=>'','product_ids'=>'','scheduled_at'=>''];
    if ($editId) { $st = $pdo->prepare('SELECT * FROM marketing_posts WHERE id=?'); $st->execute([$editId]); $p = $st->fetch() ?: $p; }
    $sel     = array_filter(array_map('trim', explode(',', $p['platforms'] ?? '')));
    $selProd = array_filter(array_map('trim', explode(',', $p['product_ids'] ?? '')));
    $err = flash('error');
?>
<div style="margin-bottom:18px"><a href="marketing.php" style="color:var(--rose-gold)">&larr; Back to Marketing</a></div>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:start">
  <!-- Composer -->
  <div class="admin-card">
    <h2><?= $editId ? 'Edit Post' : 'New Marketing Post' ?></h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="save_post" value="1">
      <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">

      <!-- Product picker -->
      <div class="form-group">
        <label class="form-label">Featured products <span style="color:#888;font-weight:400">(pick any to build an ad around)</span></label>
        <input type="text" id="prodSearch" class="form-control" placeholder="🔍 filter products…" style="margin-bottom:6px">
        <div id="prodList" style="max-height:170px;overflow:auto;border:1px solid var(--grey-light);border-radius:8px;padding:8px;display:grid;grid-template-columns:1fr 1fr;gap:4px">
          <?php foreach ($products as $pr): ?>
          <label class="prodRow" data-name="<?= h(mb_strtolower($pr['name'])) ?>" style="display:flex;align-items:center;gap:6px;font-size:0.82rem;cursor:pointer;padding:2px 4px;border-radius:5px">
            <input type="checkbox" class="prodCb" name="product_ids[]" value="<?= $pr['id'] ?>" data-image="<?= h($pr['image']) ?>" <?= in_array((string)$pr['id'],$selProd,true)?'checked':'' ?>>
            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($pr['name']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group"><label class="form-label">Internal title (optional)</label>
        <input type="text" name="title" class="form-control" value="<?= h($p['title']) ?>" placeholder="e.g. Weekend candle promo"></div>
      <div class="form-group"><label class="form-label">Post text *</label>
        <textarea name="body" id="mBody" class="form-control" rows="5" required placeholder="Write your caption, or generate with AI →"><?= h($p['body']) ?></textarea></div>
      <div class="form-group"><label class="form-label">Hashtags</label>
        <input type="text" name="hashtags" id="mTags" class="form-control" value="<?= h($p['hashtags']) ?>" placeholder="#homedecor #jamaica"></div>
      <div class="admin-form-grid">
        <div class="form-group"><label class="form-label">Image (filename in assets/images, or full URL)</label>
          <input type="text" name="image" id="mImage" class="form-control" value="<?= h($p['image']) ?>" placeholder="candle-1.jpg"></div>
        <div class="form-group"><label class="form-label">Link URL</label>
          <input type="text" name="link_url" class="form-control" value="<?= h($p['link_url'] ?: SITE_URL) ?>"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Publish to</label>
        <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:4px">
          <?php foreach ($PLATFORMS as $k=>$label): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.86rem">
            <input type="checkbox" name="platforms[]" value="<?= $k ?>" <?= in_array($k,$sel,true)?'checked':'' ?>> <?= h($label) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Schedule for (optional)</label>
        <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= $p['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($p['scheduled_at'])) : '' ?>" style="max-width:240px"></div>
      <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap">
        <button type="submit" name="mode" value="draft" class="btn btn-outline">Save Draft</button>
        <button type="submit" name="mode" value="schedule" class="btn btn-outline">Schedule</button>
        <button type="submit" name="mode" value="publish" class="btn btn-primary">Publish Now</button>
      </div>
    </form>
  </div>

  <div style="display:flex;flex-direction:column;gap:22px">
  <!-- AI assistant -->
  <div class="admin-card">
    <h2>✨ AI Ad Builder</h2>
    <?php if (!$aiReady): ?>
      <p style="font-size:0.84rem;color:#c0392b">Configure a (free) AI model under <a href="marketing.php#connections" style="color:var(--rose-gold)">AI &amp; Connections</a> to enable generation.</p>
    <?php else: ?>
    <p style="font-size:0.82rem;color:#888;margin-bottom:10px">Tick products on the left, then build an ad — or let AI pick what to promote.</p>
    <button type="button" class="btn btn-outline" id="aiSuggest" style="width:100%;margin-bottom:8px">💡 Suggest products to promote</button>
    <div id="aiSuggestOut" style="margin-bottom:10px"></div>
    <div class="form-group"><label class="form-label">Or a topic / promo (if no products picked)</label>
      <input type="text" id="aiTopic" class="form-control" placeholder="e.g. Mother's Day gift sets, 10% off"></div>
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Platform</label>
        <select id="aiPlatform" class="form-control">
          <option value="instagram">Instagram</option><option value="facebook">Facebook</option>
          <option value="twitter">Twitter / X</option><option value="whatsapp">WhatsApp</option>
        </select></div>
      <div class="form-group"><label class="form-label">Tone</label>
        <select id="aiTone" class="form-control">
          <option value="warm and inviting">Warm &amp; inviting</option>
          <option value="playful and fun">Playful</option>
          <option value="elegant and premium">Elegant</option>
          <option value="urgent, sale-focused">Sale / urgent</option>
        </select></div>
    </div>
    <button type="button" class="btn btn-primary" id="aiGen" style="width:100%">⚡ Build ad (3 options)</button>
    <div id="aiOut" style="margin-top:14px"></div>
    <?php endif; ?>
  </div>

  <!-- Live ad preview -->
  <div class="admin-card">
    <h2>Preview <span id="pvPlat" style="font-size:0.72rem;color:#888;font-weight:400"></span></h2>
    <div style="border:1px solid var(--grey-light);border-radius:10px;overflow:hidden">
      <div style="padding:8px 10px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--grey-light)">
        <div style="width:28px;height:28px;border-radius:50%;background:var(--rose-gold)"></div>
        <strong style="font-size:0.82rem"><?= h(SITE_NAME) ?></strong>
      </div>
      <img id="pvImg" alt="" style="width:100%;max-height:220px;object-fit:cover;display:none">
      <div style="padding:10px">
        <div id="pvBody" style="font-size:0.85rem;white-space:pre-wrap;color:#333">Your caption preview…</div>
        <div id="pvTags" style="font-size:0.78rem;color:#1d4ed8;margin-top:6px"></div>
      </div>
    </div>
  </div>
  </div><!-- /right column -->
</div>

<script>
function pvImgSrc(v) { return !v ? '' : (/^https?:\/\//i.test(v) ? v : '<?= SITE_URL ?>/assets/images/' + v); }
function updatePreview() {
  document.getElementById('pvBody').textContent = document.getElementById('mBody').value || 'Your caption preview…';
  document.getElementById('pvTags').textContent = document.getElementById('mTags').value || '';
  const img = document.getElementById('pvImg'), src = pvImgSrc(document.getElementById('mImage').value.trim());
  if (src) { img.src = src; img.style.display = ''; } else { img.style.display = 'none'; }
  const plat = document.getElementById('aiPlatform');
  document.getElementById('pvPlat').textContent = plat ? ('· ' + plat.options[plat.selectedIndex].text) : '';
}
const CSRF = <?= json_encode(csrf_token()) ?>;
const API  = '<?= SITE_URL ?>/api/marketing_ai.php';
const checkedProductIds = () => [...document.querySelectorAll('.prodCb:checked')].map(c => c.value);

// product list filter
const ps = document.getElementById('prodSearch');
if (ps) ps.addEventListener('input', e => {
  const f = e.target.value.trim().toLowerCase();
  document.querySelectorAll('#prodList .prodRow').forEach(r => {
    r.style.display = (!f || r.dataset.name.includes(f)) ? '' : 'none';
  });
});

// when picking products, auto-fill image from the first checked product if image is empty
document.querySelectorAll('.prodCb').forEach(cb => cb.addEventListener('change', () => {
  const img = document.getElementById('mImage');
  const first = document.querySelector('.prodCb:checked');
  if (img && !img.value && first && first.dataset.image) img.value = first.dataset.image;
  updatePreview();
}));

async function callAI(payload, btn, busyText) {
  const orig = btn.textContent; btn.disabled = true; btn.textContent = busyText;
  try {
    const r = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(Object.assign({csrf: CSRF}, payload)) });
    return await r.json();
  } catch (e) { return {ok:false, error:e.message}; }
  finally { btn.disabled = false; btn.textContent = orig; }
}

const sugBtn = document.getElementById('aiSuggest');
if (sugBtn) sugBtn.addEventListener('click', async () => {
  const out = document.getElementById('aiSuggestOut'); out.innerHTML = '';
  const data = await callAI({mode:'suggest'}, sugBtn, 'Thinking…');
  if (!data.ok) { out.innerHTML = '<div style="color:#c0392b;font-size:0.82rem">'+(data.error||'Failed')+'</div>'; return; }
  // tick suggested products & show reasons
  document.querySelectorAll('.prodCb').forEach(c => c.checked = false);
  out.innerHTML = data.picks.map(pk => {
    const cb = document.querySelector('.prodCb[value="'+pk.id+'"]');
    if (cb) { cb.checked = true; }
    const nm = cb ? cb.parentElement.querySelector('span').textContent : ('#'+pk.id);
    return '<div style="font-size:0.8rem;padding:4px 0;border-bottom:1px solid var(--grey-light)"><strong>'+nm.replace(/</g,'&lt;')+'</strong> — '+(pk.reason||'').replace(/</g,'&lt;')+'</div>';
  }).join('');
  const img = document.getElementById('mImage');
  const first = document.querySelector('.prodCb:checked');
  if (img && !img.value && first && first.dataset.image) img.value = first.dataset.image;
  updatePreview();
});

const genBtn = document.getElementById('aiGen');
if (genBtn) genBtn.addEventListener('click', async () => {
  const out = document.getElementById('aiOut'); out.innerHTML = '';
  const data = await callAI({
    mode:'generate', product_ids: checkedProductIds(),
    topic: document.getElementById('aiTopic').value,
    platform: document.getElementById('aiPlatform').value,
    tone: document.getElementById('aiTone').value, count: 3
  }, genBtn, 'Building…');
  if (!data.ok) { out.innerHTML = '<div style="color:#c0392b;font-size:0.85rem">'+(data.error||'Generation failed')+'</div>'; return; }
  out.innerHTML = data.variants.map((v,i) => {
    const tags = (v.hashtags||[]).map(t => t.startsWith('#')?t:('#'+t)).join(' ');
    const cap = (v.caption||'').replace(/</g,'&lt;');
    return `<div style="border:1px solid var(--grey-light);border-radius:8px;padding:10px;margin-bottom:10px">
      <div style="font-size:0.85rem;white-space:pre-wrap">${cap}</div>
      <div style="font-size:0.78rem;color:#1d4ed8;margin-top:6px">${tags}</div>
      <button type="button" class="btn btn-outline btn-sm" style="margin-top:8px" data-i="${i}">Use this</button>
    </div>`;
  }).join('');
  out.querySelectorAll('button[data-i]').forEach(b => b.addEventListener('click', () => {
    const v = data.variants[b.dataset.i];
    document.getElementById('mBody').value = v.caption || '';
    document.getElementById('mTags').value = (v.hashtags||[]).map(t => t.startsWith('#')?t:('#'+t)).join(' ');
    const img = document.getElementById('mImage');
    const first = document.querySelector('.prodCb:checked');
    if (img && !img.value && first && first.dataset.image) img.value = first.dataset.image;
    updatePreview();
    window.scrollTo({top:0, behavior:'smooth'});
  }));
});
// Live preview bindings
['mBody','mTags','mImage'].forEach(id => document.getElementById(id).addEventListener('input', updatePreview));
(function(){ const pp = document.getElementById('aiPlatform'); if (pp) pp.addEventListener('change', updatePreview); })();
updatePreview();
</script>
<?php require_once __DIR__ . '/footer.php'; exit; }

/* ════════════════ SINGLE POST VIEW ════════════════ */
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM marketing_posts WHERE id=?'); $st->execute([$editId]); $post = $st->fetch();
    if (!$post) { flash('error','Post not found.'); redirect(SITE_URL.'/admin/marketing.php'); }
    $results = $post['result'] ? json_decode($post['result'], true) : [];
    $links = social_share_links(trim($post['body'].' '.$post['hashtags']), $post['link_url'] ?: SITE_URL);
    $ok = flash('success'); $err = flash('error');
?>
<div style="margin-bottom:18px"><a href="marketing.php" style="color:var(--rose-gold)">&larr; Back to Marketing</a></div>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start">
  <div class="admin-card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <h2 style="margin:0"><?= h($post['title'] ?: 'Marketing Post') ?></h2>
      <span class="badge badge-<?= $statusColors[$post['status']] ?? 'grey' ?>"><?= ucfirst($post['status']) ?></span>
    </div>
    <p style="white-space:pre-wrap;font-size:0.92rem;line-height:1.6"><?= h($post['body']) ?></p>
    <?php if ($post['hashtags']): ?><p style="color:#1d4ed8;margin-top:8px;font-size:0.85rem"><?= h($post['hashtags']) ?></p><?php endif; ?>
    <?php if ($post['image']): ?><img src="<?= preg_match('~^https?://~i',$post['image']) ? h($post['image']) : product_img($post['image']) ?>" style="max-width:260px;margin-top:12px;border-radius:8px" alt=""><?php endif; ?>
    <p style="font-size:0.8rem;color:#888;margin-top:12px">Platforms: <?= h($post['platforms'] ?: '—') ?>
      <?php if ($post['scheduled_at']): ?> · Scheduled <?= date('d M Y, g:ia', strtotime($post['scheduled_at'])) ?><?php endif; ?>
      <?php if ($post['published_at']): ?> · Published <?= date('d M Y, g:ia', strtotime($post['published_at'])) ?><?php endif; ?>
    </p>
    <?php if ($results): ?>
    <div style="margin-top:14px;border-top:1px solid var(--grey-light);padding-top:12px">
      <strong style="font-size:0.85rem">Publish results</strong>
      <?php foreach ($results as $ch => $r): ?>
      <div style="font-size:0.82rem;margin-top:4px"><?= h(ucfirst($ch)) ?>:
        <span style="color:<?= !empty($r['ok'])?'#3a9e6d':'#c0392b' ?>"><?= !empty($r['ok'])?'✓':'✕' ?> <?= h($r['info'] ?? '') ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
      <a href="marketing.php?action=edit&id=<?= (int)$post['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="publish_now" value="1"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
        <button type="submit" class="btn btn-primary btn-sm">Publish Now</button></form>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete this post?')"><?= csrf_field() ?><input type="hidden" name="delete_post" value="1"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
        <button type="submit" class="btn btn-sm" style="background:#fef2f2;color:#c0392b;border:1px solid #fca5a5">Delete</button></form>
    </div>
  </div>
  <div class="admin-card">
    <h2>Share manually</h2>
    <p style="font-size:0.82rem;color:#888;margin-bottom:10px">Open a pre-filled share dialog for any network.</p>
    <div style="display:flex;flex-direction:column;gap:8px">
      <a href="<?= h($links['facebook']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Share to Facebook</a>
      <a href="<?= h($links['twitter']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Share to Twitter / X</a>
      <a href="<?= h($links['whatsapp']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Share to WhatsApp</a>
      <a href="<?= h($links['linkedin']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Share to LinkedIn</a>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; exit; }

/* ════════════════ LIST + SETTINGS ════════════════ */
$posts = $pdo->query("SELECT * FROM marketing_posts ORDER BY id DESC LIMIT 200")->fetchAll();
$ok = flash('success'); $err = flash('error');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;margin-bottom:18px">
  <h2 style="font-size:1.05rem">Marketing Posts</h2>
  <a href="marketing.php?action=new" class="btn btn-primary" style="margin-left:auto">+ New Post</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden;margin-bottom:28px">
  <table class="admin-table">
    <thead><tr><th>Post</th><th>Platforms</th><th>Status</th><th>When</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $po): ?>
    <tr>
      <td><div style="font-weight:600"><?= h($po['title'] ?: mb_substr($po['body'],0,40).'…') ?></div>
        <div style="font-size:0.78rem;color:#888"><?= h(mb_substr($po['body'],0,60)) ?></div></td>
      <td style="font-size:0.8rem;color:#888"><?= h($po['platforms'] ?: '—') ?></td>
      <td><span class="badge badge-<?= $statusColors[$po['status']] ?? 'grey' ?>"><?= ucfirst($po['status']) ?></span></td>
      <td style="font-size:0.8rem;color:#888"><?= $po['published_at'] ? date('d M y',strtotime($po['published_at'])) : ($po['scheduled_at'] ? '⏰ '.date('d M y H:i',strtotime($po['scheduled_at'])) : date('d M y',strtotime($po['created_at']))) ?></td>
      <td><a href="marketing.php?id=<?= (int)$po['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem">Open →</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($posts)): ?><tr><td colspan="5" style="text-align:center;color:#999;padding:28px">No posts yet — create your first one.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="admin-card" id="connections" style="max-width:680px">
  <h2>AI &amp; Connections</h2>
  <p style="color:#888;font-size:0.84rem;margin-bottom:14px">Credentials are stored on the server and never shown again once saved. Leave a secret field blank to keep the current value.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="save_settings" value="1">
    <?php $prov = get_setting('ai_provider','openai'); $aiKeySet = trim((string)get_setting('ai_api_key',''))!==''; $antKeySet = trim((string)get_setting('anthropic_api_key',''))!==''; ?>
    <div class="form-group"><label class="form-label">AI Provider</label>
      <select name="ai_provider" class="form-control" style="max-width:360px">
        <option value="openai" <?= $prov==='openai'?'selected':'' ?>>Free / OpenAI-compatible (Groq, OpenRouter…)</option>
        <option value="anthropic" <?= $prov==='anthropic'?'selected':'' ?>>Anthropic Claude (paid key)</option>
      </select>
      <p style="font-size:0.78rem;color:#888;margin-top:4px">Free option: grab a no-cost key at <strong>console.groq.com</strong> (default), or point the base URL at any OpenAI-compatible endpoint. Leave the key blank for a keyless endpoint.</p>
    </div>
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Free API base URL</label>
        <input type="text" name="ai_base_url" class="form-control" value="<?= h(get_setting('ai_base_url','https://api.groq.com/openai/v1')) ?>" placeholder="https://api.groq.com/openai/v1"></div>
      <div class="form-group"><label class="form-label">Free model</label>
        <input type="text" name="ai_model" class="form-control" value="<?= h(get_setting('ai_model','llama-3.3-70b-versatile')) ?>" placeholder="llama-3.3-70b-versatile"></div>
    </div>
    <div class="form-group"><label class="form-label">Free API key <?= $aiKeySet?'<span class="badge badge-success">set</span>':'<span class="badge badge-grey">not set</span>' ?></label>
      <input type="password" name="ai_api_key" class="form-control" placeholder="<?= $aiKeySet?'•••••• (leave blank to keep)':'gsk_... (free Groq key)' ?>" autocomplete="off"></div>
    <hr style="border:none;border-top:1px dashed var(--grey-light);margin:14px 0">
    <div class="form-group"><label class="form-label">Anthropic API Key — only if using Claude <?= $antKeySet ? '<span class="badge badge-success">set</span>' : '' ?></label>
      <input type="password" name="anthropic_api_key" class="form-control" placeholder="<?= $antKeySet ? '•••••• (leave blank to keep)' : 'sk-ant-...' ?>" autocomplete="off"></div>
    <div class="form-group"><label class="form-label">Claude Model</label>
      <select name="marketing_ai_model" class="form-control" style="max-width:280px">
        <?php $cm = get_setting('marketing_ai_model','claude-opus-4-8'); foreach (['claude-opus-4-8'=>'Claude Opus 4.8','claude-sonnet-4-6'=>'Claude Sonnet 4.6','claude-haiku-4-5'=>'Claude Haiku 4.5'] as $mid=>$ml): ?>
        <option value="<?= $mid ?>" <?= $cm===$mid?'selected':'' ?>><?= h($ml) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-group"><label class="form-label">Brand voice notes (optional)</label>
      <input type="text" name="brand_voice" class="form-control" value="<?= h(get_setting('brand_voice','')) ?>" placeholder="e.g. friendly, island-warm, never pushy"></div>
    <hr style="border:none;border-top:1px solid var(--grey-light);margin:16px 0">
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Facebook Page ID</label>
        <input type="text" name="fb_page_id" class="form-control" value="<?= h(get_setting('fb_page_id','')) ?>" placeholder="for auto-posting"></div>
      <div class="form-group"><label class="form-label">Facebook Page Token <?= trim((string)get_setting('fb_page_token',''))!==''?'<span class="badge badge-success">set</span>':'' ?></label>
        <input type="password" name="fb_page_token" class="form-control" placeholder="leave blank to keep" autocomplete="off"></div>
    </div>
    <div class="form-group"><label class="form-label">Automation Webhook URL (Zapier / Make / Buffer)</label>
      <input type="text" name="social_webhook_url" class="form-control" value="<?= h(get_setting('social_webhook_url','')) ?>" placeholder="https://hooks.zapier.com/..."></div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">Save Settings</button>
  </form>
  <p style="font-size:0.78rem;color:#999;margin-top:14px">Instagram &amp; Twitter/X auto-posting requires the Automation Webhook (route through Zapier/Make/Buffer) — or use the per-post <strong>Share</strong> links. Scheduled posts publish automatically only if the cron job <code>cron/publish_due.php</code> is enabled in your IONOS panel.</p>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
