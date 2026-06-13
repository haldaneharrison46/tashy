<?php
// ============================================================
// includes/header.php — Site header (shared across all pages)
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart.php';

$_user      = current_user();
$_cartCount = cart_count();
$_cats      = get_categories();
$_pageTitle = $pageTitle ?? SITE_NAME;
$_bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($_pageTitle) ?></title>
  <?php
    $_desc  = $metaDesc ?? 'Home décor, bedding, mats & fragrances — Tashy Kollections, Falmouth, Jamaica.';
    $_ogImg = $ogImage ?? (SITE_URL . '/assets/images/hero-home.jpg');
    $_ogType= $ogType ?? 'website';
    $_canon = site_origin() . ($_SERVER['REQUEST_URI'] ?? '/');
  ?>
  <meta name="description" content="<?= h($_desc) ?>">
  <link rel="canonical" href="<?= h($_canon) ?>">
  <link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/images/favicon.svg">
  <link rel="apple-touch-icon" href="<?= SITE_URL ?>/assets/images/favicon.svg">
  <!-- Open Graph / Twitter -->
  <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
  <meta property="og:title" content="<?= h($_pageTitle) ?>">
  <meta property="og:description" content="<?= h($_desc) ?>">
  <meta property="og:type" content="<?= h($_ogType) ?>">
  <meta property="og:url" content="<?= h($_canon) ?>">
  <meta property="og:image" content="<?= h($_ogImg) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= h($_pageTitle) ?>">
  <meta name="twitter:description" content="<?= h($_desc) ?>">
  <meta name="twitter:image" content="<?= h($_ogImg) ?>">
  <script type="application/ld+json">
  {"@context":"https://schema.org","@type":"Store","name":<?= json_encode(SITE_NAME) ?>,"url":<?= json_encode(SITE_URL) ?>,"image":<?= json_encode($_ogImg) ?>,"email":<?= json_encode(defined('SITE_EMAIL')?SITE_EMAIL:'') ?>,"telephone":"+1-876-487-0686","address":{"@type":"PostalAddress","streetAddress":"37 Cornwall Street","addressLocality":"Falmouth","addressRegion":"Trelawny","addressCountry":"JM"}}
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body class="<?= h($_bodyClass) ?>">

<!-- ░░ SITE HEADER ░░ -->
<header class="site-header" id="siteHeader">
  <div class="container">
    <div class="header-inner">

      <!-- Logo -->
      <a href="<?= SITE_URL ?>/index.php" class="header-logo">
        <svg class="logo-svg" width="250" height="44" viewBox="0 0 250 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Tashy Kollections">
          <!-- Emblem: roundel monogram -->
          <circle cx="22" cy="22" r="20" fill="none" stroke="#c9956c" stroke-width="1.4"/>
          <circle cx="22" cy="22" r="16.4" fill="none" stroke="#c9956c" stroke-width="0.7" opacity="0.55"/>
          <text x="22" y="30" text-anchor="middle" font-family="'Playfair Display', Georgia, serif" font-size="22" font-weight="700" fill="currentColor">T</text>
          <path d="M13 34 q9 3.6 18 0" fill="none" stroke="#c9956c" stroke-width="1" stroke-linecap="round"/>
          <circle cx="22" cy="4.6" r="1.1" fill="#c9956c"/>
          <!-- Wordmark -->
          <text x="52" y="28" font-family="'Playfair Display', Georgia, serif" font-size="25" font-weight="700" fill="currentColor">Tashy</text>
          <text x="53" y="40" font-family="'Inter', Arial, sans-serif" font-size="9" font-weight="600" fill="#c9956c" letter-spacing="0.24em">KOLLECTIONS</text>
        </svg>
      </a>

      <!-- Desktop Nav -->
      <nav class="header-nav" aria-label="Main navigation">
        <ul class="primary-nav">
          <li class="nav-item">
            <a href="<?= SITE_URL ?>/index.php" class="nav-link">Home</a>
          </li>
          <li class="nav-item">
            <a href="<?= SITE_URL ?>/shop.php" class="nav-link">Shop Now</a>
            <div class="dropdown">
              <?php foreach ($_cats as $cat): ?>
              <div class="dropdown-col">
                <a href="<?= SITE_URL ?>/shop.php?cat=<?= h($cat['slug']) ?>"><?= h($cat['name']) ?></a>
              </div>
              <?php endforeach; ?>
              <div class="dropdown-col">
                <a href="<?= SITE_URL ?>/shop.php?sort=new">New Arrivals</a>
                <a href="<?= SITE_URL ?>/shop.php?featured=1">Best Sellers</a>
              </div>
            </div>
          </li>
          <!-- Wholesale B2B hidden from menu (2026-06-08) — page still reachable directly at /wholesale.php
          <li class="nav-item"><a href="<?= SITE_URL ?>/wholesale.php" class="nav-link">Wholesale B2B <span class="wholesale-nav-badge">PRO</span></a></li>
          -->
          <li class="nav-item"><a href="<?= SITE_URL ?>/about.php" class="nav-link">About</a></li>
          <li class="nav-item"><a href="<?= SITE_URL ?>/contact.php" class="nav-link">Contact</a></li>
        </ul>
      </nav>

      <!-- Header Actions -->
      <div class="header-actions">

        <!-- Lang / Currency (desktop) — JS-injected on static, here simplified -->
        <div class="hdr-icon-pair" id="hdrIconPair">
          <div class="hdr-icon-wrap" id="hdrLangWrap" style="display:none"><!-- language switcher hidden until translations exist -->
            <button class="hdr-icon-btn" id="hdrLangBtn" aria-label="Language">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
              <span id="hdrLangLbl">EN</span>
            </button>
            <div class="hdr-icon-menu" id="hdrLangMenu">
              <button class="hdr-icon-opt active" data-pref-lang="en">🇬🇧 English</button>
              <button class="hdr-icon-opt" data-pref-lang="es">🇪🇸 Español</button>
              <button class="hdr-icon-opt" data-pref-lang="fr">🇫🇷 Français</button>
              <button class="hdr-icon-opt" data-pref-lang="zh">🇨🇳 中文</button>
            </div>
          </div>
          <div class="hdr-icon-wrap" id="hdrCurWrap">
            <button class="hdr-icon-btn" id="hdrCurBtn" aria-label="Currency">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              <span id="hdrCurLbl"><?= currency_config()[current_currency()]['label'] ?></span>
            </button>
            <div class="hdr-icon-menu" id="hdrCurMenu">
              <?php $_curNow = current_currency(); ?>
              <button class="hdr-icon-opt <?= $_curNow==='usd'?'active':'' ?>" data-pref-cur="usd">🇺🇸 USD — $</button>
              <button class="hdr-icon-opt <?= $_curNow==='jmd'?'active':'' ?>" data-pref-cur="jmd">🇯🇲 JMD — J$</button>
              <button class="hdr-icon-opt <?= $_curNow==='gbp'?'active':'' ?>" data-pref-cur="gbp">🇬🇧 GBP — £</button>
              <button class="hdr-icon-opt <?= $_curNow==='eur'?'active':'' ?>" data-pref-cur="eur">🇪🇺 EUR — €</button>
            </div>
          </div>
        </div>

        <!-- Search -->
        <form action="<?= SITE_URL ?>/shop.php" method="get" class="hdr-search-form" id="hdrSearchForm" role="search" style="display:none">
          <input type="text" name="q" placeholder="Search products…" class="hdr-search-input" autocomplete="off">
          <button type="submit" class="hdr-search-submit" aria-label="Submit search">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </button>
        </form>
        <button class="header-action-btn" aria-label="Search" id="searchToggle">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>

        <!-- Theme switcher -->
        <div class="theme-switch" tabindex="0">
          <button class="header-action-btn" type="button" aria-label="Choose theme" aria-haspopup="true">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2C6.49 2 2 6.04 2 11c0 3.87 3.13 7 7 7h1.5a1.5 1.5 0 0 1 1.06 2.56A1.5 1.5 0 0 0 12.5 22C17.75 22 22 18.2 22 13.5 22 7.15 17.51 2 12 2z"/></svg>
          </button>
          <div class="theme-switch-menu" role="menu">
            <div class="ts-title">Theme</div>
            <button class="theme-opt" data-theme-key="default" role="menuitem"><span class="theme-dot" style="background:#c9956c"></span> Classic Rose</button>
            <button class="theme-opt" data-theme-key="sandstone" role="menuitem"><span class="theme-dot" style="background:#b27a4f"></span> Sandstone</button>
            <button class="theme-opt" data-theme-key="sage" role="menuitem"><span class="theme-dot" style="background:#6f8463"></span> Sage</button>
            <button class="theme-opt" data-theme-key="blush" role="menuitem"><span class="theme-dot" style="background:#bd7d82"></span> Blush</button>
          </div>
        </div>

        <!-- Account -->
        <?php if ($_user): ?>
        <div class="hdr-icon-wrap" id="hdrAccountWrap">
          <button class="hdr-icon-btn" id="hdrAccountBtn" aria-label="Account">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span><?= h(explode(' ', $_user['name'])[0]) ?></span>
          </button>
          <div class="hdr-icon-menu">
            <a class="hdr-icon-opt" href="<?= SITE_URL ?>/account.php">My Account</a>
            <a class="hdr-icon-opt" href="<?= SITE_URL ?>/account.php?tab=orders">My Orders</a>
            <?php if ($_user['role'] === 'admin'): ?>
            <a class="hdr-icon-opt" href="<?= SITE_URL ?>/admin/index.php">Admin Panel</a>
            <?php endif; ?>
            <a class="hdr-icon-opt" href="<?= SITE_URL ?>/logout.php" style="color:#c0392b">Sign Out</a>
          </div>
        </div>
        <?php else: ?>
        <a href="<?= SITE_URL ?>/login.php" class="header-action-btn" aria-label="Account">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php endif; ?>

        <!-- Cart -->
        <button class="header-action-btn" aria-label="Cart" id="cartToggle">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          <span class="cart-count" id="cartCount"><?= $_cartCount > 0 ? $_cartCount : '' ?></span>
        </button>

        <!-- Mobile hamburger -->
        <button class="header-action-btn mobile-menu-btn" aria-label="Open menu" id="menuBtn">
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
      </div><!-- /.header-actions -->
    </div><!-- /.header-inner -->
  </div>
</header>

<!-- ░░ MOBILE DRAWER ░░ -->
<nav class="mobile-drawer" id="mobileDrawer" aria-label="Mobile navigation">
  <div class="mobile-drawer-header">
    <span style="font-weight:700;font-size:0.9rem;color:#c9956c;">TASHY KOLLECTIONS</span>
    <button class="header-action-btn" id="closeMenu">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="mobile-pref-bar" id="mobilePrefBar">
    <span class="mobile-pref-label" style="font-size:0.7rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--grey-mid);font-weight:700;">Theme</span>
    <div class="mobile-theme-row">
      <span class="theme-dot" data-theme-key="default"  title="Classic Rose" style="background:#c9956c"></span>
      <span class="theme-dot" data-theme-key="sandstone" title="Sandstone"    style="background:#b27a4f"></span>
      <span class="theme-dot" data-theme-key="sage"      title="Sage"         style="background:#6f8463"></span>
      <span class="theme-dot" data-theme-key="blush"     title="Blush"        style="background:#bd7d82"></span>
    </div>
  </div>
  <ul>
    <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/index.php" class="mobile-nav-link">Home</a></li>
    <li class="mobile-nav-item">
      <button class="mobile-nav-link" onclick="toggleMobileSub(this)"><span>Shop Now</span> <span>›</span></button>
      <div class="mobile-nav-sub">
        <?php foreach ($_cats as $c): ?>
        <a href="<?= SITE_URL ?>/shop.php?cat=<?= h($c['slug']) ?>"><?= h($c['name']) ?></a>
        <?php endforeach; ?>
        <a href="<?= SITE_URL ?>/shop.php?sort=new">New Arrivals</a>
        <a href="<?= SITE_URL ?>/shop.php?featured=1">Best Sellers</a>
      </div>
    </li>
    <!-- Wholesale B2B hidden from menu (2026-06-08)
    <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/wholesale.php" class="mobile-nav-link">Wholesale B2B</a></li>
    -->
    <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/about.php" class="mobile-nav-link">About</a></li>
    <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/contact.php" class="mobile-nav-link">Contact</a></li>
    <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/policy.php" class="mobile-nav-link">Shipping &amp; Returns</a></li>
    <?php if ($_user): ?>
    <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/account.php" class="mobile-nav-link">My Account</a></li>
    <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/logout.php" class="mobile-nav-link" style="color:#c0392b">Sign Out</a></li>
    <?php else: ?>
    <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/login.php" class="mobile-nav-link">Sign In / Register</a></li>
    <?php endif; ?>
  </ul>
</nav>
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- ░░ CART DRAWER ░░ -->
<div class="cart-overlay" id="cartOverlay">
  <div class="cart-drawer" id="cartDrawer">
    <div class="cart-drawer-head">
      <div class="cart-drawer-title">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
        Your Cart <span class="cart-drawer-count" id="drawerCount">(<?= $_cartCount ?>)</span>
      </div>
      <button class="cart-drawer-close" id="cartClose">✕</button>
    </div>
    <div class="cart-items" id="cartItems">
      <?php $cart = get_cart(); ?>
      <?php if (empty($cart)): ?>
      <div class="cart-empty">
        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <p>Your cart is empty.</p>
        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary" style="margin-top:8px">Shop Now</a>
      </div>
      <?php else: foreach ($cart as $item): ?>
      <div class="cart-item" data-item-id="<?= $item['id'] ?>">
        <div class="cart-item-img">
          <img src="<?= product_img($item['image']) ?>" alt="<?= h($item['name']) ?>">
        </div>
        <div class="cart-item-info">
          <div class="cart-item-name"><?= h($item['name']) ?></div>
          <div class="cart-item-brand"><?= h($item['brand']) ?></div>
          <div class="cart-item-price"><?= money($item['price']) ?></div>
        </div>
        <div class="cart-qty">
          <div class="cart-qty-row">
            <button class="cart-qty-btn" onclick="cartQty(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)">−</button>
            <span class="cart-qty-num"><?= $item['quantity'] ?></span>
            <button class="cart-qty-btn" onclick="cartQty(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)">+</button>
          </div>
          <button class="cart-remove-btn" onclick="cartRemove(<?= $item['id'] ?>)">Remove</button>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
    <?php if (!empty($cart)):
      $totals = cart_totals(); ?>
    <div class="cart-footer">
      <div class="cart-subtotal">
        <span>Subtotal</span><span><?= money($totals['subtotal']) ?></span>
      </div>
      <?php if ($totals['shipping'] > 0): ?>
      <div class="cart-subtotal"><span>Shipping</span><span><?= money($totals['shipping']) ?></span></div>
      <?php else: ?>
      <div class="cart-subtotal"><span>Shipping</span><span style="color:#3a9e6d">FREE</span></div>
      <?php endif; ?>
      <div class="cart-subtotal cart-total-row">
        <span><strong>Total (incl. GCT)</strong></span>
        <span><strong><?= money($totals['total']) ?></strong></span>
      </div>
      <a href="<?= SITE_URL ?>/checkout.php" class="cart-checkout-btn">Proceed to Checkout</a>
      <a href="<?= SITE_URL ?>/cart.php" class="cart-view-all">View full cart</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ░░ TOAST ░░ -->
<div class="cart-toast" id="cartToast"></div>

<!-- Flash message -->
<?php $flash = flash('success'); if ($flash): ?>
<div class="site-flash success"><?= h($flash) ?></div>
<?php endif; ?>
<?php $flash = flash('error'); if ($flash): ?>
<div class="site-flash error"><?= h($flash) ?></div>
<?php endif; ?>

<main>
