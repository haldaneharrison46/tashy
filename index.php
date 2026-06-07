<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Tashy Kollections — Home Decor & Fragrances, Falmouth Jamaica';
$metaDesc  = 'Tashy Kollections — bedding, comforters, mats, kitchen & bath, and fragrances for men & women. Making statements, one space at a time. Based in Falmouth, Trelawny, Jamaica with island-wide delivery and local pickup.';

$featuredProducts = get_products(['featured' => true, 'limit' => 8]);
require_once __DIR__ . '/includes/header.php';
?>

<!-- ░░ HERO ░░ -->
<section class="hero-home" style="background-image:url('<?= SITE_URL ?>/assets/images/hero-home.jpg')">
  <div class="container">
    <div class="hero-home-text">
      <p class="hero-eyebrow">Falmouth · Trelawny · Jamaica</p>
      <h1>Make a <span>statement</span> in every room</h1>
      <p>Bedding, comforters, mats, kitchen &amp; bath essentials, and signature fragrances — curated to turn your house into a home that speaks for itself.</p>
      <div class="hero-home-ctas">
        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary btn-lg">Shop Collections</a>
        <a href="<?= SITE_URL ?>/about.php" class="btn btn-outline-white btn-lg">Our Story</a>
      </div>
      <div class="hero-home-proofs">
        <div class="hero-home-proof">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span>Shop in US$ &amp; JMD</span>
        </div>
        <div class="hero-home-proof">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
          <span>Island-Wide Delivery &amp; Local Pickup</span>
        </div>
        <div class="hero-home-proof">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span>Secure Checkout</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ░░ COLLECTIONS ░░ -->
<section class="section">
  <div class="container">
    <div class="text-center" style="margin-bottom:2.5rem">
      <h2 class="section-title">Shop by Collection</h2>
      <p class="section-sub">Everything you need to style every corner of your home.</p>
    </div>
    <div class="collection-grid">
      <?php
        $collections = [
          ['icon' => '🛏️', 'name' => 'Bedding',        'desc' => 'Sheets, duvets, comforters &amp; pillows for restful nights.'],
          ['icon' => '🛁', 'name' => 'Kitchen &amp; Bath', 'desc' => 'Towels, mats, and finishing touches for everyday spaces.'],
          ['icon' => '🧶', 'name' => 'Mats &amp; Rugs',    'desc' => 'Soft underfoot, statement on top — for any room.'],
          ['icon' => '🕯️', 'name' => 'Fragrances',     'desc' => 'Signature scents &amp; home fragrance for him &amp; her.'],
          ['icon' => '🎁', 'name' => 'Gift Sets',       'desc' => 'Beautifully bundled — ready to give, easy to love.'],
        ];
        foreach ($collections as $c):
      ?>
      <a href="<?= SITE_URL ?>/shop.php" class="collection-card">
        <div class="cc-icon"><?= $c['icon'] ?></div>
        <h3><?= $c['name'] ?></h3>
        <p><?= $c['desc'] ?></p>
        <span class="cc-link">Shop now
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!empty($featuredProducts)): ?>
<!-- ░░ FEATURED ░░ -->
<section class="section section--pale">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">Featured This Season</h2>
        <p class="section-sub">Hand-picked pieces our community is loving.</p>
      </div>
      <a href="<?= SITE_URL ?>/shop.php" class="btn btn-outline">View All</a>
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
<?php endif; ?>

<!-- ░░ FRAGRANCE LAB ░░ -->
<section class="section">
  <div class="container">
    <div class="wholesale-banner">
      <div class="wholesale-banner-text">
        <h2>Discover your <span>signature scent</span></h2>
        <p>Answer a few questions about your style in our Fragrance Lab, and we'll match you to scents made for you — for him, for her, and for your home.</p>
      </div>
      <div class="wholesale-banner-cta">
        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary btn-lg">Explore Fragrances</a>
      </div>
    </div>
  </div>
</section>

<!-- ░░ BRAND STATEMENT ░░ -->
<section class="section section--pale">
  <div class="container">
    <div class="statement">
      <span class="statement-mark">&ldquo;</span>
      <h2>Making statements, one space at a time</h2>
      <p>From the heart of Falmouth, Trelawny, Tashy Kollections curates bedding, home essentials, and fragrances that turn a house into a statement. Thoughtfully sourced, beautifully made, and delivered across all 14 parishes of Jamaica.</p>
      <a href="<?= SITE_URL ?>/about.php" class="btn btn-outline" style="margin-top:26px">Read Our Story</a>
    </div>
  </div>
</section>

<!-- ░░ TRUST BAR ░░ -->
<section class="trust-bar">
  <div class="container">
    <div class="trust-bar-inner">
      <div class="trust-item"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Pay in US$ or JMD</span></div>
      <div class="trust-item"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg><span>Island-Wide Delivery</span></div>
      <div class="trust-item"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span>Local Pickup in Falmouth</span></div>
      <div class="trust-item"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Secure Checkout</span></div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
