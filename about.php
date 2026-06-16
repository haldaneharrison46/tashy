<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'About Us | ' . SITE_NAME;
$metaDesc  = sk('Tashy Kollections — home décor, bedding, mats & fragrances, based in Falmouth, Trelawny. Curated pieces, honest advice, island-wide delivery.',
                SITE_NAME . ' — hair care, skin care, makeup, nails & beauty tools. Quality beauty supplies, honest advice, island-wide delivery in Jamaica.',
                SITE_NAME . ' — handbags, fashion, footwear, colognes & beauty. Curated style, honest advice, shipping across the US and Jamaica.');
require_once __DIR__ . '/includes/header.php';
?>

<!-- ░░ INTRO ░░ -->
<section class="section">
  <div class="container">
    <div class="text-center" style="max-width:720px;margin:0 auto 3rem">
      <span class="policy-tag">Our Story</span>
      <h1 class="section-title"><?= sk('Pieces That Make Your House a Home', 'Beauty Supplies You Can Trust', 'Style, Curated for You') ?></h1>
      <p class="section-sub"><?= sk(h(SITE_NAME) . ' is a Jamaican home destination curating premium bedding, kitchen &amp; bath, mats, gift sets &amp; signature fragrances — chosen with real care and an eye for detail.',
                                    h(SITE_NAME) . ' is a Jamaican beauty destination stocking hair care, skin care, makeup, nails &amp; the tools to match — trusted brands, chosen with real care.',
                                    h(SITE_NAME) . ' is your destination for handbags, fashion, footwear, colognes &amp; beauty — quality pieces chosen with an eye for style and detail.') ?></p>
    </div>

    <div class="about-story">
      <div class="about-img">
        <img src="<?= asset_base() ?>/assets/images/aestheticjourney-cream-8293579_1920.jpg" alt="Styled home interior" loading="lazy" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div>
        <h2 style="margin-bottom:16px"><?= sk('Rooted in Falmouth, serving all of Jamaica', 'Rooted in Jamaica, beauty for everyone', 'Curated style, shipped to your door') ?></h2>
        <?php if (store_kind() === 'luxe'): ?>
        <p style="margin-bottom:14px;line-height:1.75;color:var(--grey-dark)">
          <?= h(SITE_NAME) ?> began with a simple belief: looking good shouldn't be complicated. We bring together
          handbags, clothing, footwear, colognes and beauty in one curated place — quality you can feel, at fair prices.
        </p>
        <p style="margin-bottom:14px;line-height:1.75;color:var(--grey-dark)">
          We hand-pick every piece, from statement bags and dresses to the grooming and beauty essentials that finish a look —
          no flimsy fillers, just pieces worth carrying.
        </p>
        <p style="line-height:1.75;color:var(--grey-dark)">
          Based in Palm Bay, Florida and shipping across the US and Jamaica, our team is here with honest advice every step of the way.
        </p>
        <?php elseif (store_kind() === 'beauty'): ?>
        <p style="margin-bottom:14px;line-height:1.75;color:var(--grey-dark)">
          From our home at 37 Cornwall Street in historic Falmouth, Trelawny, <?= h(SITE_NAME) ?> began with a simple belief:
          everyone deserves quality beauty products and honest advice — without the markup or the guesswork.
        </p>
        <p style="margin-bottom:14px;line-height:1.75;color:var(--grey-dark)">
          We hand-pick every brand we carry, from salon favourites to everyday essentials.
          No knock-offs, no compromises — just genuine hair, skin, makeup and nail products our community can trust.
        </p>
        <p style="line-height:1.75;color:var(--grey-dark)">
          Whether you're a pro stylist stocking your kit or refreshing your own routine,
          our team is here with honest, expert advice every step of the way.
        </p>
        <?php else: ?>
        <p style="margin-bottom:14px;line-height:1.75;color:var(--grey-dark)">
          From our home at 37 Cornwall Street in historic Falmouth, Trelawny, <?= h(SITE_NAME) ?> began with a simple belief:
          every Jamaican home deserves beautiful, well-made pieces that turn an ordinary room into a space that
          <em>speaks for itself</em>.
        </p>
        <p style="margin-bottom:14px;line-height:1.75;color:var(--grey-dark)">
          We hand-pick every piece we carry, from globally trusted names to thoughtfully sourced finds.
          No flimsy fillers, no compromises — just quality bedding, home essentials and fragrances our community can trust.
        </p>
        <p style="line-height:1.75;color:var(--grey-dark)">
          Whether you're refreshing the bedroom, styling a living space, or finding your home's signature scent,
          our team is here with honest, expert advice every step of the way.
        </p>
        <?php endif; ?>
        <a href="<?= asset_base() ?>/shop.php" class="btn btn-primary" style="margin-top:24px">Shop the Collection</a>
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
        <div class="icon"><?= sk('🏠', '✨', '👜') ?></div>
        <h4><?= sk('Curated for Your Home', 'Curated for You', 'Curated for You') ?></h4>
        <p><?= sk('Every piece is chosen to look beautiful and live well — from restful bedding to statement rugs.', 'Every product is chosen to perform — from salon-grade hair care to everyday skin essentials.', 'Every piece is chosen to look good and last — from statement bags to everyday beauty essentials.') ?></p>
      </div>
      <div class="value-card">
        <div class="icon">🚚</div>
        <h4>Island-Wide Delivery</h4>
        <p>Fast, reliable delivery to all 14 parishes — with free shipping on orders over J$5,000.</p>
      </div>
      <div class="value-card">
        <div class="icon">💬</div>
        <h4>Expert Advice</h4>
        <p>Real guidance from people who know homes — no pushy sales, just honest help.</p>
      </div>
      <div class="value-card">
        <div class="icon">🇯🇲</div>
        <h4>Proudly Jamaican</h4>
        <p>A local business championing both global brands and homegrown Jamaican style.</p>
      </div>
      <div class="value-card">
        <div class="icon">🤝</div>
        <h4>Wholesale Ready</h4>
        <p>Hotels, resorts, gift shops &amp; resellers welcome — partner with us for trade pricing and support.</p>
      </div>
    </div>
  </div>
</section>

<!-- ░░ STATS / CTA ░░ -->
<section class="wholesale-hero">
  <div class="container">
    <h2 style="color:var(--white);margin-bottom:12px">Style that <span>delivers</span>.</h2>
    <p><?= sk('Join the growing community styling their homes with ' . h(SITE_NAME) . '.', 'Join the growing community shopping beauty with ' . h(SITE_NAME) . '.', 'Join the growing community shopping style with ' . h(SITE_NAME) . '.') ?></p>
    <div class="stat-row">
      <div class="stat-item"><strong>500+</strong><span>Curated Products</span></div>
      <div class="stat-item"><strong>14</strong><span>Parishes Served</span></div>
      <div class="stat-item"><strong>100%</strong><span>Authentic Guarantee</span></div>
      <div class="stat-item"><strong>24h</strong><span>Support Response</span></div>
    </div>
    <div style="margin-top:36px;display:flex;gap:12px;flex-wrap:wrap">
      <a href="<?= asset_base() ?>/shop.php" class="btn btn-primary btn-lg">Shop Now</a>
      <a href="<?= asset_base() ?>/contact.php" class="btn btn-outline-white btn-lg">Contact Us</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
