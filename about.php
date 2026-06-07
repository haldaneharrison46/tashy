<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'About Us | ' . SITE_NAME;
$metaDesc  = 'Tashy Kollections — premium beauty for melanin-rich skin, based in Falmouth, Trelawny. Authentic brands, expert advice, island-wide delivery.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ░░ INTRO ░░ -->
<section class="section">
  <div class="container">
    <div class="text-center" style="max-width:720px;margin:0 auto 3rem">
      <span class="policy-tag">Our Story</span>
      <h1 class="section-title">Beauty Made for Your Skin's Real Needs</h1>
      <p class="section-sub">Tashy Kollections is a Jamaican beauty destination curating premium skincare, hair care, fragrance &amp; makeup for melanin-rich skin — backed by real expertise and genuine care.</p>
    </div>

    <div class="about-story">
      <div class="about-img">
        <img src="<?= SITE_URL ?>/assets/images/aestheticjourney-cream-8293579_1920.jpg" alt="Premium skincare products" loading="lazy" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div>
        <h2 style="margin-bottom:16px">Rooted in Falmouth, serving all of Jamaica</h2>
        <p style="margin-bottom:14px;line-height:1.75;color:var(--grey-dark)">
          From our home at 37 Cornwall Street in historic Falmouth, Trelawny, Tashy Kollections began with a simple belief:
          everyone deserves access to authentic, results-driven beauty products formulated to work <em>with</em> melanin-rich skin —
          not against it.
        </p>
        <p style="margin-bottom:14px;line-height:1.75;color:var(--grey-dark)">
          We hand-pick every brand we carry, from globally trusted names to local Jamaican favourites like Tropic Isle Living.
          No counterfeits, no compromises — just pro-grade products our community can trust.
        </p>
        <p style="line-height:1.75;color:var(--grey-dark)">
          Whether you're refining a skincare routine, growing your edges, or finding your signature scent,
          our team is here with honest, expert advice every step of the way.
        </p>
        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary" style="margin-top:24px">Shop the Collection</a>
      </div>
    </div>
  </div>
</section>

<!-- ░░ VALUES ░░ -->
<section class="section section--pale">
  <div class="container">
    <div class="text-center" style="margin-bottom:2.5rem">
      <h2 class="section-title">What We Stand For</h2>
      <p class="section-sub">The promises behind every order.</p>
    </div>
    <div class="value-grid">
      <div class="value-card">
        <div class="icon">✅</div>
        <h4>100% Authentic</h4>
        <p>Every product is sourced from authorised suppliers — guaranteed genuine, every single time.</p>
      </div>
      <div class="value-card">
        <div class="icon">🌍</div>
        <h4>Made for Melanin</h4>
        <p>Our range is curated specifically for the needs of melanin-rich skin and textured hair.</p>
      </div>
      <div class="value-card">
        <div class="icon">🚚</div>
        <h4>Island-Wide Delivery</h4>
        <p>Fast, reliable delivery to all 14 parishes — with free shipping on orders over J$5,000.</p>
      </div>
      <div class="value-card">
        <div class="icon">💬</div>
        <h4>Expert Advice</h4>
        <p>Real guidance from people who know beauty — no pushy sales, just honest help.</p>
      </div>
      <div class="value-card">
        <div class="icon">🇯🇲</div>
        <h4>Proudly Jamaican</h4>
        <p>A local business championing both global brands and homegrown Jamaican beauty.</p>
      </div>
      <div class="value-card">
        <div class="icon">🤝</div>
        <h4>Wholesale Ready</h4>
        <p>Salons, spas &amp; resellers welcome — partner with us for trade pricing and support.</p>
      </div>
    </div>
  </div>
</section>

<!-- ░░ STATS / CTA ░░ -->
<section class="wholesale-hero">
  <div class="container">
    <h2 style="color:var(--white);margin-bottom:12px">Beauty that <span>delivers</span>.</h2>
    <p>Join the growing community shopping smarter for melanin-rich beauty.</p>
    <div class="stat-row">
      <div class="stat-item"><strong>500+</strong><span>Curated Products</span></div>
      <div class="stat-item"><strong>14</strong><span>Parishes Served</span></div>
      <div class="stat-item"><strong>100%</strong><span>Authentic Guarantee</span></div>
      <div class="stat-item"><strong>24h</strong><span>Support Response</span></div>
    </div>
    <div style="margin-top:36px;display:flex;gap:12px;flex-wrap:wrap">
      <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary btn-lg">Shop Now</a>
      <a href="<?= SITE_URL ?>/contact.php" class="btn btn-outline-white btn-lg">Contact Us</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
