<?php
// Admin header — included by every admin page
// Expects $pageTitle to be set before including
// Buffer output so admin pages can still redirect() after this header is sent
// (this server has output_buffering = 0). The buffer flushes normally at page end.
if (!ob_get_level()) ob_start();
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin(); // redirects non-admins

$adminUser   = current_user();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Admin') ?> | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* ── Admin shell ────────────────────────────── */
body { display:flex; min-height:100vh; flex-direction:column; }
.admin-wrap { display:flex; flex:1; }
.admin-sidebar {
  width:220px; flex-shrink:0;
  background:#1a1a1a; color:#e0e0e0;
  padding:0; display:flex; flex-direction:column; min-height:100vh;
}
.admin-sidebar .brand {
  padding:22px 20px 18px;
  font-size:1.05rem; font-weight:700;
  color:#fff; border-bottom:1px solid #333;
  text-decoration:none; display:block;
}
.admin-sidebar nav a {
  display:flex; align-items:center; gap:10px;
  padding:11px 20px; color:#bbb; text-decoration:none;
  font-size:0.88rem; transition:background .15s;
}
.admin-sidebar nav a:hover, .admin-sidebar nav a.active {
  background:#2a2a2a; color:#fff;
}
.admin-sidebar nav a .icon { font-size:1rem; width:20px; text-align:center; }
.admin-sidebar nav .section-label {
  padding:14px 20px 6px; font-size:0.7rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.08em; color:#555;
}
.admin-sidebar .sidebar-footer {
  margin-top:auto; padding:16px 20px;
  font-size:0.8rem; color:#666; border-top:1px solid #2a2a2a;
}
.admin-content { flex:1; display:flex; flex-direction:column; min-width:0; }
.admin-topbar {
  background:var(--white); border-bottom:1px solid var(--grey-light);
  padding:14px 28px; display:flex; align-items:center; justify-content:space-between;
  position:sticky; top:0; z-index:100;
}
.admin-topbar h1 { font-size:1.15rem; font-weight:700; }
.admin-topbar .topbar-right { display:flex; align-items:center; gap:16px; font-size:0.85rem; color:#888; }
.admin-topbar .topbar-right a { color:var(--rose-gold); text-decoration:none; }
.admin-main { padding:28px; flex:1; }

/* ── Admin UI components ─────────────────────── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:18px; margin-bottom:28px; }
.stat-card {
  background:var(--white); border:1px solid var(--grey-light); border-radius:12px;
  padding:20px; text-align:center;
}
.stat-card .stat-val { font-size:1.6rem; font-weight:700; color:var(--rose-gold); }
.stat-card .stat-lbl { font-size:0.78rem; color:#888; margin-top:4px; }
.admin-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
.admin-table th { text-align:left; padding:8px 12px; border-bottom:2px solid var(--grey-light); font-size:0.78rem; text-transform:uppercase; letter-spacing:.04em; color:#888; }
.admin-table td { padding:10px 12px; border-bottom:1px solid var(--grey-light); vertical-align:middle; }
.admin-table tr:hover td { background:var(--rose-pale); }
.admin-card { background:var(--white); border:1px solid var(--grey-light); border-radius:12px; padding:24px; margin-bottom:24px; }
.admin-card h2 { font-size:1rem; margin-bottom:18px; }
.badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:0.72rem; font-weight:700; text-transform:uppercase; }
.badge-success { background:#d1fae5; color:#065f46; }
.badge-warning { background:#fef9c3; color:#a16207; }
.badge-info    { background:#dbeafe; color:#1d4ed8; }
.badge-danger  { background:#fee2e2; color:#991b1b; }
.badge-grey    { background:#f3f4f6; color:#6b7280; }
.admin-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.admin-form-grid .full { grid-column:1/-1; }

/* ── Mobile: stack so screens are full-width; sidebar becomes a compact top strip ── */
@media (max-width: 900px) {
  .admin-wrap { flex-direction:column; }
  .admin-content { width:100%; min-width:0; }
  .admin-sidebar {
    width:100%; min-height:0; flex-direction:row; align-items:center;
    overflow-x:auto; -webkit-overflow-scrolling:touch;
    position:sticky; top:0; z-index:200; padding:0; border-bottom:1px solid #333;
  }
  .admin-sidebar::-webkit-scrollbar { height:0; }
  .admin-sidebar .brand { padding:11px 12px; border:none; font-size:1rem; white-space:nowrap; flex-shrink:0; }
  .admin-sidebar .brand br, .admin-sidebar .brand span { display:none; }
  .admin-sidebar nav { display:flex; flex-direction:row; align-items:center; }
  .admin-sidebar nav .section-label { display:none; }
  .admin-sidebar nav a { padding:13px 11px; white-space:nowrap; font-size:0.8rem; gap:5px; }
  .admin-sidebar nav a .icon { width:auto; }
  .admin-sidebar .sidebar-footer { display:none; }
  .admin-topbar { position:static; padding:12px 16px; }
  .admin-topbar h1 { font-size:1rem; }
  .admin-topbar .tb-date, .admin-topbar .tb-newprod { display:none; }
  .admin-main { padding:16px; }
  .admin-form-grid { grid-template-columns:1fr; }
  /* Collapse the inner two-column page layouts so each screen is full-width */
  .admin-main [style*="grid-template-columns"],
  .admin-main .pos-grid { grid-template-columns:1fr !important; }
}
@media (max-width: 480px) {
  .admin-sidebar .brand { display:none; }   /* Dashboard is in the nav strip anyway */
}
</style>
</head>
<body>
<div class="admin-wrap">

  <!-- Sidebar -->
  <aside class="admin-sidebar">
    <a href="<?= asset_base() ?>/admin/index.php" class="brand" style="color:#fff"><?= tk_logo(170) ?><span style="display:block;font-size:0.7rem;font-weight:400;color:#777;margin-top:4px">Admin Panel</span></a>
    <nav>
      <div class="section-label">Main</div>
      <a href="index.php"    class="<?= $currentPage==='index.php'    ? 'active':'' ?>"><span class="icon">📊</span> Dashboard</a>
      <a href="pos.php"      class="<?= $currentPage==='pos.php'      ? 'active':'' ?>"><span class="icon">🧾</span> Point of Sale</a>
      <a href="pos_report.php" class="<?= $currentPage==='pos_report.php' ? 'active':'' ?>"><span class="icon">📈</span> POS Report</a>
      <a href="products.php" class="<?= $currentPage==='products.php' ? 'active':'' ?>"><span class="icon">🛍️</span> Products</a>
      <a href="orders.php"   class="<?= $currentPage==='orders.php'   ? 'active':'' ?>"><span class="icon">📦</span> Orders</a>
      <a href="kanban.php"   class="<?= $currentPage==='kanban.php'   ? 'active':'' ?>"><span class="icon">🗂️</span> Order Board</a>
      <a href="customers.php" class="<?= $currentPage==='customers.php' ? 'active':'' ?>"><span class="icon">🧑</span> Customers</a>
      <a href="returns.php"  class="<?= $currentPage==='returns.php'  ? 'active':'' ?>"><span class="icon">↩️</span> Returns</a>
      <a href="marketing.php" class="<?= $currentPage==='marketing.php' ? 'active':'' ?>"><span class="icon">📣</span> Marketing</a>
      <div class="section-label">Configuration</div>
      <a href="shipping.php" class="<?= $currentPage==='shipping.php' ? 'active':'' ?>"><span class="icon">🚚</span> Shipping</a>
      <a href="users.php"    class="<?= $currentPage==='users.php'    ? 'active':'' ?>"><span class="icon">👥</span> Staff &amp; Users</a>
      <a href="subscribers.php" class="<?= $currentPage==='subscribers.php' ? 'active':'' ?>"><span class="icon">✉️</span> Subscribers</a>
      <div class="section-label">Site</div>
      <a href="<?= asset_base() ?>" target="_blank"><span class="icon">🌐</span> View Store</a>
    </nav>
    <div class="sidebar-footer">
      Signed in as <strong><?= h($adminUser['name']) ?></strong><br>
      <a href="<?= asset_base() ?>/logout.php" style="color:#e07878">Sign out</a>
    </div>
  </aside>

  <!-- Content area -->
  <div class="admin-content">
    <div class="admin-topbar">
      <h1><?= $pageTitle ?? 'Admin' ?></h1>
      <div class="topbar-right">
        <span class="tb-date"><?= date('l, d M Y') ?></span>
        <a href="products.php?action=add" class="tb-newprod">+ New Product</a>
        <a href="<?= asset_base() ?>/logout.php" style="color:#e07878">Sign out</a>
      </div>
    </div>
    <div class="admin-main">
