<?php
$pageTitle = 'Subscribers';
require_once __DIR__ . '/header.php';   // runs require_admin()

$pdo = db();
$subs = $pdo->query('SELECT id, email, created_at FROM newsletter_subscribers ORDER BY created_at DESC')->fetchAll();
?>
<div style="display:flex;align-items:center;margin-bottom:18px">
  <h1 style="margin:0">Newsletter Subscribers</h1>
  <span style="color:#888;font-size:0.82rem;margin-left:auto"><?= count($subs) ?> subscriber(s)</span>
</div>

<table class="admin-table" style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden">
  <thead><tr>
    <th style="text-align:left;padding:10px 14px">Email</th>
    <th style="text-align:left;padding:10px 14px">Subscribed</th>
  </tr></thead>
  <tbody>
  <?php foreach ($subs as $s): ?>
    <tr style="border-top:1px solid var(--grey-light)">
      <td style="padding:10px 14px"><a href="mailto:<?= h($s['email']) ?>" style="color:inherit"><?= h($s['email']) ?></a></td>
      <td style="padding:10px 14px;color:#888;font-size:0.86rem"><?= date('d M Y, g:ia', strtotime($s['created_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($subs)): ?>
    <tr><td colspan="2" style="text-align:center;color:#999;padding:28px">No subscribers yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/footer.php'; ?>
