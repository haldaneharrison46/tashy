<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/header.php';

$pdo = db();

/* ── Run database updates (idempotent migrations) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    csrf_check();
    $_SESSION['migrate_log'] = tk_run_migrations();
    flash('success', 'Database updates applied.');
    redirect(asset_base() . '/admin/settings.php#data');
}

/* ── Tax ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tax'])) {
    csrf_check();
    set_setting('tax_enabled', isset($_POST['tax_enabled']) ? '1' : '0');
    $rate = max(0, min(100, (float)($_POST['tax_rate'] ?? 15)));
    set_setting('tax_rate',    (string)$rate);
    set_setting('tax_label',   trim($_POST['tax_label'] ?? 'GCT') ?: 'GCT');
    set_setting('tax_country', trim($_POST['tax_country'] ?? 'Jamaica') ?: 'Jamaica');
    flash('success', 'Tax settings saved.');
    redirect(asset_base() . '/admin/settings.php#tax');
}

/* ── Store ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_store'])) {
    csrf_check();
    foreach (['store_phone','store_hours','store_slogan','receipt_header','receipt_footer'] as $k)
        set_setting($k, trim($_POST[$k] ?? ''));
    flash('success', 'Store settings saved.');
    redirect(asset_base() . '/admin/settings.php#store');
}

/* ── Printer ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_printer'])) {
    csrf_check();
    set_setting('receipt_width', ($_POST['receipt_width'] ?? '80') === '58' ? '58' : '80');
    set_setting('print_copies',  (string)max(1, min(5, (int)($_POST['print_copies'] ?? 1))));
    set_setting('auto_print',    isset($_POST['auto_print']) ? '1' : '0');
    flash('success', 'Printer settings saved.');
    redirect(asset_base() . '/admin/settings.php#printer');
}

/* ── Payment ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    csrf_check();
    foreach (payment_methods() as $k => $m) set_setting($m['setting'], isset($_POST[$m['setting']]) ? '1' : '0');
    set_setting('bank_transfer_details', trim($_POST['bank_transfer_details'] ?? ''));
    set_setting('card_instructions',     trim($_POST['card_instructions'] ?? ''));
    set_setting('paypal_me',             trim($_POST['paypal_me'] ?? ''));
    set_setting('paypal_email',          trim($_POST['paypal_email'] ?? ''));
    flash('success', 'Payment settings saved.');
    redirect(asset_base() . '/admin/settings.php#payment');
}

/* ── Email ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email'])) {
    csrf_check();
    set_setting('mail_from_name',       trim($_POST['mail_from_name'] ?? ''));
    set_setting('mail_from_email',      trim($_POST['mail_from_email'] ?? ''));
    set_setting('mail_reply_to',        trim($_POST['mail_reply_to'] ?? ''));
    set_setting('mail_admin_recipient', trim($_POST['mail_admin_recipient'] ?? ''));
    set_setting('mail_method',          ($_POST['mail_method'] ?? 'mail') === 'smtp' ? 'smtp' : 'mail');
    set_setting('smtp_host',            trim($_POST['smtp_host'] ?? ''));
    set_setting('smtp_port',            (string)max(1, (int)($_POST['smtp_port'] ?? 587)));
    set_setting('smtp_user',            trim($_POST['smtp_user'] ?? ''));
    set_setting('smtp_secure',          in_array($_POST['smtp_secure'] ?? 'tls', ['tls','ssl','none'], true) ? $_POST['smtp_secure'] : 'tls');
    if (trim($_POST['smtp_pass'] ?? '') !== '') set_setting('smtp_pass', trim($_POST['smtp_pass']));  // blank = keep
    flash('success', 'Email settings saved.');
    redirect(asset_base() . '/admin/settings.php#email');
}

/* ── Marketing / announcement ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marketing'])) {
    csrf_check();
    set_setting('announcement_enabled', isset($_POST['announcement_enabled']) ? '1' : '0');
    set_setting('announcement_text',    trim($_POST['announcement_text'] ?? ''));
    set_setting('announcement_link',    trim($_POST['announcement_link'] ?? ''));
    set_setting('announcement_speed',   (string)max(5, min(60, (int)($_POST['announcement_speed'] ?? 18))));
    flash('success', 'Marketing settings saved.');
    redirect(asset_base() . '/admin/settings.php#marketing');
}

/* ── Preset messages add/delete ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preset_add'])) {
    csrf_check();
    $scope = in_array($_POST['scope'] ?? '', ['pos','order','marketing'], true) ? $_POST['scope'] : 'pos';
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    if ($title !== '' && $body !== '') {
        try {
            $pdo->prepare('INSERT INTO preset_messages (scope, title, body) VALUES (?,?,?)')->execute([$scope, $title, $body]);
            flash('success', 'Preset message added.');
        } catch (Throwable $e) { flash('error', 'Could not add preset — run database updates first.'); }
    } else { flash('error', 'Title and message are required.'); }
    redirect(asset_base() . '/admin/settings.php#presets');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preset_delete'])) {
    csrf_check();
    try { $pdo->prepare('DELETE FROM preset_messages WHERE id=?')->execute([(int)$_POST['preset_id']]); } catch (Throwable $e) {}
    flash('success', 'Preset message deleted.');
    redirect(asset_base() . '/admin/settings.php#presets');
}

$ok  = flash('success');
$err = flash('error');
$migrateLog = $_SESSION['migrate_log'] ?? null;
unset($_SESSION['migrate_log']);

$haveTaxable = tk_column_exists('products', 'taxable');
$haveExempt  = tk_column_exists('users', 'tax_exempt');
$haveVendor  = tk_column_exists('products', 'vendor_id');
$tableReady  = function (string $t): bool { try { db()->query("SELECT 1 FROM `$t` LIMIT 1"); return true; } catch (Throwable $e) { return false; } };
$haveBlog    = $tableReady('blog_posts');
$haveVendors = $tableReady('vendors');
$haveInv     = $tableReady('inventory_receipts');
$havePresets = $tableReady('preset_messages');
$allReady    = $haveTaxable && $haveExempt && $haveVendor && $haveBlog && $haveVendors && $haveInv && $havePresets;

$scopeLabels = ['pos' => 'POS receipt share', 'order' => 'Order status update', 'marketing' => 'Marketing caption'];
?>
<style>
.settings-nav { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; }
.settings-nav a { font-size:0.82rem; padding:6px 12px; border:1px solid var(--grey-light); border-radius:20px; color:#555; text-decoration:none; background:#fff; }
.settings-nav a:hover { border-color:var(--rose-gold); color:var(--rose-gold); }
</style>

<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

<div class="settings-nav">
  <a href="#store">Store</a>
  <a href="#printer">Printer</a>
  <a href="#payment">Payment</a>
  <a href="#email">Email</a>
  <a href="#tax">Tax</a>
  <a href="#marketing">Announcement</a>
  <a href="#presets">Preset Messages</a>
  <a href="#data">Database</a>
</div>

<!-- ═══ STORE ═══ -->
<div class="admin-card" id="store" style="max-width:680px">
  <h2>Store</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_store" value="1">
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Phone</label>
        <input type="text" name="store_phone" class="form-control" value="<?= h(get_setting('store_phone','')) ?>"></div>
      <div class="form-group"><label class="form-label">Opening hours</label>
        <input type="text" name="store_hours" class="form-control" value="<?= h(get_setting('store_hours','')) ?>"></div>
      <div class="form-group full"><label class="form-label">Slogan <span style="color:#888;font-weight:400">(shown in the footer & receipts)</span></label>
        <input type="text" name="store_slogan" class="form-control" value="<?= h(get_setting('store_slogan','')) ?>"></div>
      <div class="form-group full"><label class="form-label">Receipt header line (optional)</label>
        <input type="text" name="receipt_header" class="form-control" value="<?= h(get_setting('receipt_header','')) ?>" placeholder="e.g. Returns within 7 days with receipt"></div>
      <div class="form-group full"><label class="form-label">Receipt footer message</label>
        <input type="text" name="receipt_footer" class="form-control" value="<?= h(get_setting('receipt_footer','')) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">Save Store</button>
  </form>
</div>

<!-- ═══ PRINTER ═══ -->
<div class="admin-card" id="printer" style="max-width:680px">
  <h2>Printer / Receipts</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_printer" value="1">
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Receipt width</label>
        <?php $rw = get_setting('receipt_width','80'); ?>
        <select name="receipt_width" class="form-control">
          <option value="80" <?= $rw==='80'?'selected':'' ?>>80 mm (standard)</option>
          <option value="58" <?= $rw==='58'?'selected':'' ?>>58 mm (compact)</option>
        </select></div>
      <div class="form-group"><label class="form-label">Copies to print</label>
        <input type="number" name="print_copies" min="1" max="5" class="form-control" value="<?= h(get_setting('print_copies','1')) ?>"></div>
      <div class="form-group full">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="auto_print" value="1" <?= setting_bool('auto_print') ? 'checked' : '' ?>> Auto-open the print dialog after completing a POS sale
        </label>
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">Save Printer</button>
  </form>
</div>

<!-- ═══ PAYMENT ═══ -->
<div class="admin-card" id="payment" style="max-width:680px">
  <h2>Payment</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_payment" value="1">
    <div class="form-group full" style="margin-bottom:14px">
      <label class="form-label">Accepted methods (online checkout &amp; POS)</label>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px">
        <?php foreach (payment_methods() as $k => $m): ?>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="<?= h($m['setting']) ?>" value="1" <?= setting_bool($m['setting'], $m['default']) ? 'checked' : '' ?>> <?= h($m['label']) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-group full"><label class="form-label">Bank transfer details <span style="color:#888;font-weight:400">(shown to customers who choose transfer)</span></label>
      <textarea name="bank_transfer_details" class="form-control" rows="3" placeholder="Bank, account name, account #, branch"><?= h(get_setting('bank_transfer_details','')) ?></textarea></div>
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">PayPal.me handle <span style="color:#888;font-weight:400">(for a pay link)</span></label>
        <input type="text" name="paypal_me" class="form-control" value="<?= h(get_setting('paypal_me','')) ?>" placeholder="e.g. tashykollections"></div>
      <div class="form-group"><label class="form-label">PayPal email</label>
        <input type="text" name="paypal_email" class="form-control" value="<?= h(get_setting('paypal_email','')) ?>" placeholder="payments@…"></div>
    </div>
    <div class="form-group full"><label class="form-label">Card instructions (optional)</label>
      <input type="text" name="card_instructions" class="form-control" value="<?= h(get_setting('card_instructions','')) ?>" placeholder="e.g. We'll send a secure card link"></div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">Save Payment</button>
  </form>
</div>

<!-- ═══ EMAIL ═══ -->
<div class="admin-card" id="email" style="max-width:680px">
  <h2>Email</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_email" value="1">
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">From name</label>
        <input type="text" name="mail_from_name" class="form-control" value="<?= h(get_setting('mail_from_name', SITE_NAME)) ?>"></div>
      <div class="form-group"><label class="form-label">From email</label>
        <input type="text" name="mail_from_email" class="form-control" value="<?= h(get_setting('mail_from_email', defined('SITE_EMAIL')?SITE_EMAIL:'')) ?>"></div>
      <div class="form-group"><label class="form-label">Reply-to</label>
        <input type="text" name="mail_reply_to" class="form-control" value="<?= h(get_setting('mail_reply_to', defined('SITE_EMAIL')?SITE_EMAIL:'')) ?>"></div>
      <div class="form-group"><label class="form-label">Store notifications to</label>
        <input type="text" name="mail_admin_recipient" class="form-control" value="<?= h(get_setting('mail_admin_recipient', defined('SITE_EMAIL')?SITE_EMAIL:'')) ?>"></div>
    </div>
    <div class="form-group full"><label class="form-label">Sending method</label>
      <?php $mm = get_setting('mail_method','mail'); ?>
      <select name="mail_method" class="form-control" style="max-width:320px">
        <option value="mail" <?= $mm==='mail'?'selected':'' ?>>Server mail() — simplest</option>
        <option value="smtp" <?= $mm==='smtp'?'selected':'' ?>>SMTP relay — better deliverability</option>
      </select>
      <p style="font-size:0.78rem;color:#888;margin-top:4px">SMTP fields below are used only when "SMTP relay" is selected.</p>
    </div>
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">SMTP host</label>
        <input type="text" name="smtp_host" class="form-control" value="<?= h(get_setting('smtp_host','')) ?>" placeholder="smtp.gmail.com"></div>
      <div class="form-group"><label class="form-label">SMTP port</label>
        <input type="number" name="smtp_port" class="form-control" value="<?= h(get_setting('smtp_port','587')) ?>"></div>
      <div class="form-group"><label class="form-label">SMTP username</label>
        <input type="text" name="smtp_user" class="form-control" value="<?= h(get_setting('smtp_user','')) ?>" autocomplete="off"></div>
      <div class="form-group"><label class="form-label">SMTP password <?= trim((string)get_setting('smtp_pass',''))!=='' ? '<span class="badge badge-success">set</span>' : '' ?></label>
        <input type="password" name="smtp_pass" class="form-control" placeholder="leave blank to keep" autocomplete="off"></div>
      <div class="form-group"><label class="form-label">Encryption</label>
        <?php $ss = get_setting('smtp_secure','tls'); ?>
        <select name="smtp_secure" class="form-control">
          <option value="tls"  <?= $ss==='tls'?'selected':'' ?>>STARTTLS (587)</option>
          <option value="ssl"  <?= $ss==='ssl'?'selected':'' ?>>SSL (465)</option>
          <option value="none" <?= $ss==='none'?'selected':'' ?>>None</option>
        </select></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">Save Email</button>
  </form>
</div>

<!-- ═══ TAX ═══ -->
<div class="admin-card" id="tax" style="max-width:680px">
  <h2>Tax (GCT)</h2>
  <p style="color:#888;font-size:0.84rem;margin-bottom:14px">Tax is only charged for the tax country below; orders shipping elsewhere are exempt. Products can opt out per item, and customers can be marked tax-exempt.</p>
  <?php if (!$haveTaxable || !$haveExempt): ?>
  <div class="badge-warning" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;display:block">⚠ Per-item / per-customer tax columns aren't installed. Run <a href="#data" style="color:inherit;text-decoration:underline">Database updates</a>.</div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_tax" value="1">
    <div class="form-group full" style="margin-bottom:14px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="tax_enabled" value="1" <?= tax_enabled() ? 'checked' : '' ?>> Charge tax on orders
      </label>
    </div>
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Tax rate (%)</label>
        <input type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control" value="<?= h(rtrim(rtrim(number_format(tax_rate_pct(), 2), '0'), '.')) ?>"></div>
      <div class="form-group"><label class="form-label">Tax label</label>
        <input type="text" name="tax_label" class="form-control" value="<?= h(tax_label()) ?>"></div>
      <div class="form-group full"><label class="form-label">Tax country <span style="color:#888;font-weight:400">(only this destination is taxed)</span></label>
        <input type="text" name="tax_country" class="form-control" value="<?= h(tax_country()) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">Save Tax</button>
  </form>
</div>

<!-- ═══ ANNOUNCEMENT ═══ -->
<div class="admin-card" id="marketing" style="max-width:680px">
  <h2>Scrolling announcement bar</h2>
  <p style="color:#888;font-size:0.84rem;margin-bottom:14px">A scrolling banner across the top of the storefront. Separate multiple messages with " | ".</p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_marketing" value="1">
    <div class="form-group full" style="margin-bottom:12px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="announcement_enabled" value="1" <?= setting_bool('announcement_enabled') ? 'checked' : '' ?>> Show the announcement bar
      </label>
    </div>
    <div class="form-group full"><label class="form-label">Message(s)</label>
      <input type="text" name="announcement_text" class="form-control" value="<?= h(get_setting('announcement_text','')) ?>" placeholder="Free delivery over J$5,000 | New arrivals every week"></div>
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Link (optional)</label>
        <input type="text" name="announcement_link" class="form-control" value="<?= h(get_setting('announcement_link','')) ?>" placeholder="/shop.php"></div>
      <div class="form-group"><label class="form-label">Scroll speed (seconds / loop)</label>
        <input type="number" name="announcement_speed" min="5" max="60" class="form-control" value="<?= h(get_setting('announcement_speed','18')) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">Save Announcement</button>
  </form>
</div>

<!-- ═══ PRESET MESSAGES ═══ -->
<div class="admin-card" id="presets" style="max-width:680px">
  <h2>Preset Messages</h2>
  <p style="color:#888;font-size:0.84rem;margin-bottom:14px">Reusable templates. Placeholders: <code>{name}</code>, <code>{order}</code>, <code>{total}</code>, <code>{store}</code>.</p>
  <?php if (!$havePresets): ?>
  <div class="badge-warning" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;display:block">⚠ Run <a href="#data" style="color:inherit;text-decoration:underline">Database updates</a> to enable preset messages.</div>
  <?php else: ?>
  <?php foreach (['pos','order','marketing'] as $sc): $list = get_preset_messages($sc); ?>
  <div style="margin-bottom:14px">
    <div style="font-size:0.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888;margin-bottom:6px"><?= h($scopeLabels[$sc]) ?></div>
    <?php if (empty($list)): ?><div style="font-size:0.82rem;color:#aaa;margin-bottom:6px">None yet.</div><?php endif; ?>
    <?php foreach ($list as $pm): ?>
    <div style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--grey-light);border-radius:8px;padding:8px 10px;margin-bottom:6px">
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:0.85rem"><?= h($pm['title']) ?></div>
        <div style="font-size:0.8rem;color:#777;white-space:pre-wrap"><?= h($pm['body']) ?></div>
      </div>
      <form method="post" onsubmit="return confirm('Delete this preset?')">
        <?= csrf_field() ?><input type="hidden" name="preset_delete" value="1"><input type="hidden" name="preset_id" value="<?= (int)$pm['id'] ?>">
        <button type="submit" class="btn btn-sm" style="background:#fef2f2;color:#c0392b;border:1px solid #fca5a5">✕</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <hr style="border:none;border-top:1px solid var(--grey-light);margin:14px 0">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="preset_add" value="1">
    <div class="admin-form-grid">
      <div class="form-group"><label class="form-label">Used for</label>
        <select name="scope" class="form-control">
          <option value="pos">POS receipt share</option>
          <option value="order">Order status update</option>
          <option value="marketing">Marketing caption</option>
        </select></div>
      <div class="form-group"><label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" placeholder="e.g. Ready for pickup"></div>
      <div class="form-group full"><label class="form-label">Message</label>
        <textarea name="body" class="form-control" rows="3" placeholder="Hi {name}, your order {order} is ready! Total {total}. — {store}"></textarea></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">+ Add Preset</button>
  </form>
  <?php endif; ?>
</div>

<!-- ═══ DATABASE ═══ -->
<div class="admin-card" id="data" style="max-width:680px">
  <h2>Database updates</h2>
  <p style="color:#888;font-size:0.84rem;margin-bottom:14px">Applies any new tables/columns this version needs. Safe to re-run — it never touches existing data.</p>
  <div style="display:flex;flex-direction:column;gap:6px;font-size:0.86rem;margin-bottom:14px">
    <?php
    $flags = [
      'Per-item tax (products.taxable)' => $haveTaxable,
      'Tax-exempt customers (users.tax_exempt)' => $haveExempt,
      'Vendor on products (products.vendor_id)' => $haveVendor,
      'Blog (blog_posts)' => $haveBlog,
      'Vendors (vendors)' => $haveVendors,
      'Inventory receiving (inventory_receipts)' => $haveInv,
      'Preset messages (preset_messages)' => $havePresets,
    ];
    foreach ($flags as $label => $ready): ?>
    <div><?= $ready ? '<span class="badge badge-success">ready</span>' : '<span class="badge badge-grey">pending</span>' ?> <?= h($label) ?></div>
    <?php endforeach; ?>
  </div>
  <?php if ($migrateLog): ?>
  <pre style="background:#f6f6f6;border:1px solid var(--grey-light);border-radius:8px;padding:12px;font-size:0.78rem;white-space:pre-wrap;margin-bottom:14px"><?= h(implode("\n", $migrateLog)) ?></pre>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="run_migrations" value="1">
    <button type="submit" class="btn <?= $allReady ? 'btn-outline' : 'btn-primary' ?>"><?= $allReady ? 'Re-run database updates' : 'Apply database updates' ?></button>
  </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
