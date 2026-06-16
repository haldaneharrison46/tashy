<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = SITE_NAME . sk(' — Home Decor & Fragrances, Falmouth Jamaica', ' — Beauty Supplies & Cosmetics, Jamaica', ' — Handbags, Fashion & Beauty');
$metaDesc  = sk('Tashy Kollections — bedding, comforters, mats, kitchen & bath, and fragrances for men & women. Making statements, one space at a time. Based in Falmouth, Trelawny, Jamaica with island-wide delivery and local pickup.',
                SITE_NAME . ' — hair care, skin care, makeup, nails, tools & beauty essentials. Quality beauty supplies with island-wide delivery and local pickup in Jamaica.',
                SITE_NAME . ' — handbags, dresses, colognes, footwear, hair & makeup. Curated style & beauty, delivered across the US and Jamaica.');

$featuredProducts = get_products(['featured' => true, 'limit' => 8]);
require_once __DIR__ . '/includes/header.php';
?>

<!-- ░░ HERO ░░ -->
<?php
  $heroImg = trim((string) get_setting('hero_image', ''));
  $heroBg  = $heroImg !== '' ? (preg_match('~^https?://~i', $heroImg) ? $heroImg : product_img($heroImg)) : asset_base() . '/assets/images/hero-home.jpg';
  $weekly  = trim((string) get_setting('weekly_special', ''));
?>
<section class="hero-home" style="background-image:url('<?= h($heroBg) ?>')">
  <div class="container">
    <div class="hero-home-text">
      <p class="hero-eyebrow">★ <?= sk('Free Island-Wide Delivery over ', 'Free Island-Wide Delivery over ', 'Free Shipping over ') . money(free_shipping_threshold()) ?></p>
      <?php if ($weekly !== ''): ?>
      <div class="hero-weekly">🔥 <strong>Weekly Special</strong> — <?= h($weekly) ?></div>
      <?php endif; ?>
      <?php if (store_kind() === 'beauty'): ?>
      <h1>Look Your Best. <span>Every Day.</span></h1>
      <p>Hair care, skin care, makeup, nails &amp; the tools to match — quality beauty supplies at honest prices. Stock up and <strong>glow on.</strong></p>
      <?php elseif (store_kind() === 'luxe'): ?>
      <h1>Carry the Look. <span>Own the Moment.</span></h1>
      <p>Handbags, dresses, colognes, footwear &amp; the finishing touches — curated style, delivered to your door. <strong>Elevate the everyday.</strong></p>
      <?php else: ?>
      <h1>Upgrade Every Room. <span>Starting Today.</span></h1>
      <p>Premium bedding, mats, bath essentials &amp; signature fragrances — curated to transform your space. Don't just decorate. <strong>Make a statement.</strong></p>
      <?php endif; ?>
      <div class="hero-home-ctas">
        <a href="<?= asset_base() ?>/shop.php?featured=1" class="btn btn-primary btn-lg"><?= sk('Shop Best Sellers', 'Shop Best Sellers', 'Shop the Collection') ?></a>
        <a href="<?= asset_base() ?>/shop.php?sort=new" class="btn btn-outline-white btn-lg">New Arrivals</a>
        <?php if (setting_bool('call_to_order') && store_phone_e164() !== ''): ?>
        <a href="tel:+<?= h(store_phone_e164()) ?>" class="btn btn-outline-white btn-lg">📞 Call to Order</a>
        <?php endif; ?>
      </div>
      <div class="hero-home-proofs">
        <div class="hero-home-proof">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span><?= sk('Shop in US$ &amp; JMD', 'Shop in US$ &amp; JMD', 'Shop in US$ &amp; JMD') ?></span>
        </div>
        <div class="hero-home-proof">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
          <span><?= sk('Island-Wide Delivery &amp; Local Pickup', 'Island-Wide Delivery &amp; Local Pickup', 'US &amp; Jamaica Shipping') ?></span>
        </div>
        <div class="hero-home-proof">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span>Secure Checkout</span>
        </div>
      </div>
    </div>
  </div>
</section>
<style>
.hero-weekly{display:inline-block;background:rgba(201,149,108,.95);color:#fff;padding:7px 16px;border-radius:30px;font-size:0.86rem;font-weight:600;margin:0 0 16px;box-shadow:0 6px 18px rgba(0,0,0,.2);backdrop-filter:blur(2px)}
.hero-weekly strong{font-weight:800;letter-spacing:.02em}
</style>

<!-- ░░ COLLECTIONS ░░ -->
<section class="section">
  <div class="container">
    <div class="text-center" style="margin-bottom:2.5rem">
      <h2 class="section-title"><?= sk('Shop by Collection', 'Category Filter', 'Shop by Category') ?></h2>
      <p class="section-sub"><?= sk('Everything you need to style every corner of your home.', 'Everything you need to look and feel your best.', 'Curated style, beauty & accessories for every occasion.') ?></p>
    </div>
    <div class="collection-grid">
      <?php
        // Driven by the store's own categories, so each site shows its own.
        $catIcons = [
          'bedding'=>'🛏️','kitchen-bath'=>'🛁','mats-rugs'=>'🧶','fragrances'=>'🕯️','gift-sets'=>'🎁',
          'hair-care'=>'💇🏽‍♀️','skin-care'=>'🧴','makeup'=>'💄','nails'=>'💅','tools-accessories'=>'🪮','wigs-extensions'=>'👩🏽‍🦱',
        ];
        foreach (get_categories() as $c):
          $icon = $catIcons[$c['slug']] ?? '🛍️';
      ?>
      <a href="<?= asset_base() ?>/shop.php?cat=<?= h($c['slug']) ?>" class="collection-card">
        <div class="cc-icon"><?= $icon ?></div>
        <h3><?= h($c['name']) ?></h3>
        <p><?= h($c['description'] ?? '') ?></p>
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
      <a href="<?= asset_base() ?>/shop.php" class="btn btn-outline">View All</a>
    </div>
    <div class="product-grid" id="productGrid">
      <?php foreach ($featuredProducts as $p): ?>
      <div class="product-card" data-product-id="<?= $p['id'] ?>">
        <div class="product-card-img">
          <a href="<?= asset_base() ?>/product.php?slug=<?= h($p['slug']) ?>">
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
          <h3 class="product-name"><a href="<?= asset_base() ?>/product.php?slug=<?= h($p['slug']) ?>"><?= h($p['name']) ?></a></h3>
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
      <?php if (store_kind() === 'beauty'): ?>
      <div class="wholesale-banner-text">
        <h2>Find your <span>perfect shade</span></h2>
        <p>Explore makeup, skin care and hair essentials — trusted brands and pro-quality picks for every look and every routine.</p>
      </div>
      <div class="wholesale-banner-cta">
        <a href="<?= asset_base() ?>/shop.php?cat=makeup" class="btn btn-primary btn-lg">Shop Makeup</a>
      </div>
      <?php elseif (store_kind() === 'luxe'): ?>
      <div class="wholesale-banner-text">
        <h2>Discover your <span>signature scent</span></h2>
        <p>Explore our colognes &amp; fragrances — bold, fresh and timeless picks to finish every look.</p>
      </div>
      <div class="wholesale-banner-cta">
        <a href="<?= asset_base() ?>/shop.php?cat=colognes" class="btn btn-primary btn-lg">Shop Colognes</a>
      </div>
      <?php else: ?>
      <div class="wholesale-banner-text">
        <h2>Discover your <span>signature scent</span></h2>
        <p>Explore our curated fragrance collection — candles, diffusers and eau de parfum for him, for her, and for your home.</p>
      </div>
      <div class="wholesale-banner-cta">
        <a href="<?= asset_base() ?>/shop.php?cat=fragrances" class="btn btn-primary btn-lg">Shop Fragrances</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ░░ BRAND STATEMENT ░░ -->
<section class="section section--pale">
  <div class="container">
    <div class="statement">
      <span class="statement-mark">&ldquo;</span>
      <h2><?= sk('Making statements, one space at a time', 'Beauty essentials you can count on', 'Style that turns heads') ?></h2>
      <p><?= sk('From the heart of Falmouth, Trelawny, ' . h(SITE_NAME) . ' curates bedding, home essentials, and fragrances that turn a house into a statement. Thoughtfully sourced, beautifully made, and delivered across all 14 parishes of Jamaica.',
                h(SITE_NAME) . ' brings you quality hair care, skin care, makeup and beauty tools at honest prices — trusted brands and pro-quality picks, delivered across all 14 parishes of Jamaica.',
                h(SITE_NAME) . ' curates handbags, fashion, footwear, colognes and beauty — quality pieces and the finishing touches, shipped across the US and Jamaica.') ?></p>
      <a href="<?= asset_base() ?>/about.php" class="btn btn-outline" style="margin-top:26px">Read Our Story</a>
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
