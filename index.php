<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Tashy Kollections — Melanin Glow Specialists';
$metaDesc  = 'Premium skincare, hair care, fragrance & makeup for melanin-rich skin. Authentic brands. Pro-grade results. Delivered across Jamaica.';

$featuredProducts = get_products(['featured' => true, 'limit' => 8]);
$categories       = get_categories();
require_once __DIR__ . '/includes/header.php';
?>

<!-- ░░ HERO ░░ -->
<section class="hero hero--fw">
  <div class="hero-fw-inner">
    <div class="hero-fw-text">
      <p class="hero-eyebrow">Melanin Glow Specialists</p>
      <h1>Beauty Made for <span>Your Skin's</span> Real Needs</h1>
      <p class="hero-sub">Premium skincare, hair care, fragrance &amp; makeup — curated for melanin-rich skin. Authentic brands. Pro-grade results.</p>
    </div>
    <div class="hero-fw-images" aria-hidden="true">
      <div class="hero-img-row">
        <div class="hero-img-card"><img src="<?= SITE_URL ?>/assets/images/sham.jpg" alt="Shampoo product"></div>
        <div class="hero-img-card"><img src="<?= SITE_URL ?>/assets/images/platinum.webp" alt="Kenra Platinum hair care"></div>
      </div>
    </div>
  </div>
  <div class="hero-fw-bottom">
    <div class="hero-ctas">
      <a href="<?= SITE_URL ?>/shop.php?cat=skincare" class="btn btn-primary btn-lg">Shop Skincare</a>
      <a href="<?= SITE_URL ?>/shop.php?cat=hair" class="btn btn-outline-white btn-lg">Shop Hair Care</a>
      <a href="<?= SITE_URL ?>/shop.php?cat=fragrance" class="btn btn-outline-white btn-lg">Shop Fragrance</a>
    </div>
    <div class="hero-proofs">
      <div class="hero-proof"><svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span>500+ Products</span></div>
      <div class="hero-proof"><svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span>Top Global Brands</span></div>
      <div class="hero-proof"><svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span>Island-Wide Delivery</span></div>
    </div>
  </div>
</section>

<!-- ░░ SHOP BY CONCERN ░░ -->
<section class="section section--pale">
  <div class="container">
    <div class="text-center" style="margin-bottom:2rem">
      <h2 class="section-title">Shop by Category</h2>
      <p class="section-sub">Find exactly what your skin and hair need.</p>
    </div>
    <div class="concern-grid">
      <?php foreach ($categories as $cat): ?>
      <a href="<?= SITE_URL ?>/shop.php?cat=<?= h($cat['slug']) ?>" class="concern-card">
        <div class="concern-icon">
          <?php if ($cat['slug'] === 'skincare'): ?>✨
          <?php elseif ($cat['slug'] === 'hair'): ?>💆
          <?php elseif ($cat['slug'] === 'fragrance'): ?>🌸
          <?php else: ?>💄 <?php endif; ?>
        </div>
        <h3><?= h($cat['name']) ?></h3>
        <?php if ($cat['description']): ?><p><?= h(mb_substr($cat['description'], 0, 60)) ?>…</p><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ░░ BEST SELLERS ░░ -->
<section class="section">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">Best Sellers</h2>
        <p class="section-sub">Products your community loves most.</p>
      </div>
      <a href="<?= SITE_URL ?>/shop.php?featured=1" class="btn btn-outline">View All</a>
    </div>
    <div class="product-grid" id="productGrid">
      <?php foreach ($featuredProducts as $p): ?>
      <div class="product-card" data-product-id="<?= $p['id'] ?>">
        <div class="product-card-img">
          <a href="<?= SITE_URL ?>/product.php?slug=<?= h($p['slug']) ?>">
            <img src="<?= product_img($p['image']) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
          </a>
          <?php if ($p['compare_price']): ?>
          <span class="product-badge badge-sale">SALE</span>
          <?php endif; ?>
          <button class="fav-btn" aria-label="Add to wishlist" data-product-id="<?= $p['id'] ?>" onclick="toggleWishlist(this)">
            <svg width="18" height="18" fill="none" stroke="#e05c6a" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          </button>
        </div>
        <div class="product-card-body">
          <div class="product-brand"><?= h($p['brand']) ?></div>
          <h3 class="product-name"><a href="<?= SITE_URL ?>/product.php?slug=<?= h($p['slug']) ?>"><?= h($p['name']) ?></a></h3>
          <div class="product-footer">
            <div class="product-price">
              <span class="price-current"><?= money($p['price']) ?></span>
              <?php if ($p['compare_price']): ?>
              <span class="price-compare"><?= money($p['compare_price']) ?></span>
              <?php endif; ?>
            </div>
            <button class="quick-add-btn" onclick="addToCart(<?= $p['id'] ?>, this)">Add to Cart</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ░░ TRUST BAR ░░ -->
<section class="trust-bar">
  <div class="container">
    <div class="trust-bar-inner">
      <div class="trust-item"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span>Authentic Products Guaranteed</span></div>
      <div class="trust-item"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg><span>Island-Wide Delivery</span></div>
      <div class="trust-item"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Secure Checkout</span></div>
      <div class="trust-item"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Expert Beauty Advice</span></div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
