<?php
require_once __DIR__ . '/includes/functions.php';

$slug    = trim($_GET['slug'] ?? '');
$product = $slug ? get_product_by_slug($slug) : null;
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found | ' . SITE_NAME;
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding:80px 0;text-align:center"><h1>Product not found</h1><a href="' . SITE_URL . '/shop.php" class="btn btn-primary" style="margin-top:16px">Back to Shop</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

require_once __DIR__ . '/includes/auth.php';

// ── Review submission ─────────────────────────────────────────
$reviewError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit'])) {
    csrf_check();
    if (!is_logged_in()) {
        $reviewError = 'Please sign in to leave a review.';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $body   = trim($_POST['body'] ?? '');
        if ($rating < 1 || $rating > 5)      $reviewError = 'Please choose a star rating.';
        elseif (mb_strlen($body) < 3)        $reviewError = 'Please write a short review.';
        else {
            db()->prepare(
                'INSERT INTO reviews (product_id, user_id, rating, body) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), body = VALUES(body), created_at = CURRENT_TIMESTAMP'
            )->execute([(int)$product['id'], current_user()['id'], $rating, $body]);
            flash('success', 'Thanks for your review!');
            redirect(SITE_URL . '/product.php?slug=' . $product['slug'] . '#reviews');
        }
    }
}
$prodRating  = get_product_rating((int)$product['id']);
$prodReviews = get_product_reviews((int)$product['id']);

$pageTitle = h($product['name']) . ' | ' . SITE_NAME;
$metaDesc  = $product['description'] ? mb_substr(strip_tags($product['description']), 0, 160) : '';
$ogImage   = SITE_URL . '/assets/images/' . ($product['image'] ?: 'placeholder.svg');
$ogType    = 'product';

// Related products (same category, different product)
$related = get_products(['category' => $product['category_slug'], 'limit' => 4]);
$related = array_filter($related, fn($r) => $r['id'] !== $product['id']);

require_once __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"Product","name":<?= json_encode($product['name']) ?>,"image":<?= json_encode($ogImage) ?>,"description":<?= json_encode($metaDesc) ?>,"brand":{"@type":"Brand","name":<?= json_encode($product['brand'] ?? 'Tashy Kollections') ?>},"sku":<?= json_encode($product['sku'] ?? (string)$product['id']) ?>,"offers":{"@type":"Offer","url":<?= json_encode(SITE_URL.'/product.php?slug='.$product['slug']) ?>,"priceCurrency":"JMD","price":<?= json_encode(number_format((float)$product['price'],2,'.','')) ?>,"availability":"https://schema.org/<?= ((int)$product['stock'] > 0 ? 'InStock' : 'OutOfStock') ?>"}}
</script>

<div class="container" style="padding-top:32px">
  <!-- Breadcrumb -->
  <nav class="breadcrumb" style="margin-bottom:24px;font-size:0.82rem;color:#999">
    <a href="<?= SITE_URL ?>/index.php">Home</a> /
    <a href="<?= SITE_URL ?>/shop.php">Shop</a> /
    <?php if ($product['category_slug']): ?>
    <a href="<?= SITE_URL ?>/shop.php?cat=<?= h($product['category_slug']) ?>"><?= h($product['category_name']) ?></a> /
    <?php endif; ?>
    <span style="color:#333"><?= h($product['name']) ?></span>
  </nav>

  <div class="pdp-layout">
    <!-- Gallery -->
    <div class="pdp-gallery">
      <div class="pdp-main-img" id="pdpMainImg">
        <img src="<?= product_img($product['image']) ?>" alt="<?= h($product['name']) ?>" id="mainProductImg">
      </div>
      <?php if ($product['image2'] || $product['image3']): ?>
      <div class="pdp-thumb-row">
        <img src="<?= product_img($product['image']) ?>" class="pdp-thumb active" onclick="switchImg(this, '<?= product_img($product['image']) ?>')">
        <?php if ($product['image2']): ?>
        <img src="<?= product_img($product['image2']) ?>" class="pdp-thumb" onclick="switchImg(this, '<?= product_img($product['image2']) ?>')">
        <?php endif; ?>
        <?php if ($product['image3']): ?>
        <img src="<?= product_img($product['image3']) ?>" class="pdp-thumb" onclick="switchImg(this, '<?= product_img($product['image3']) ?>')">
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="pdp-info">
      <?php if ($product['brand']): ?><div class="product-brand" style="margin-bottom:6px"><?= h($product['brand']) ?></div><?php endif; ?>
      <h1 class="pdp-title"><?= h($product['name']) ?></h1>
      <?php if ($prodRating['count'] > 0): ?>
      <div style="margin:6px 0 2px"><?= stars_html($prodRating['avg']) ?>
        <a href="#reviews" style="color:#999;font-size:.85rem;text-decoration:none"><?= $prodRating['avg'] ?> (<?= $prodRating['count'] ?> review<?= $prodRating['count']==1?'':'s' ?>)</a>
      </div>
      <?php endif; ?>

      <div class="pdp-price-row">
        <span class="pdp-price"><?= money($product['price']) ?></span>
        <?php if ($product['compare_price']): ?>
        <span class="price-compare" style="font-size:1rem"><?= money($product['compare_price']) ?></span>
        <?php $savings = $product['compare_price'] - $product['price']; ?>
        <span class="badge-sale" style="font-size:0.8rem;padding:4px 10px;border-radius:4px">Save <?= money($savings) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($product['description']): ?>
      <div class="pdp-desc" style="margin:20px 0;color:#555;line-height:1.75"><?= nl2br(h($product['description'])) ?></div>
      <?php endif; ?>

      <?php if ($product['stock'] > 0): ?>
      <p style="color:#3a9e6d;font-size:0.85rem;margin-bottom:16px">✓ In stock (<?= $product['stock'] ?> available)</p>
      <div class="pdp-qty-row">
        <div class="qty-control">
          <button class="qty-btn" onclick="changeQty(-1)">−</button>
          <input type="number" id="pdpQty" value="1" min="1" max="<?= $product['stock'] ?>" class="qty-input" readonly>
          <button class="qty-btn" onclick="changeQty(1)">+</button>
        </div>
        <button class="btn btn-primary pdp-atc-btn" onclick="addToCart(<?= $product['id'] ?>, this, parseInt(document.getElementById('pdpQty').value))">
          Add to Cart
        </button>
        <button class="fav-btn" style="position:static;box-shadow:none;width:44px;height:44px" aria-label="Add to wishlist" data-product-id="<?= $product['id'] ?>" onclick="toggleWishlist(this)">
          <svg width="20" height="20" fill="none" stroke="#e05c6a" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </button>
      </div>
      <?php else: ?>
      <p style="color:#c0392b;margin-bottom:20px">⚠ Currently out of stock.</p>
      <?php endif; ?>

      <?php if ($product['tags']): ?>
      <div class="pdp-tags" style="margin-top:20px">
        <?php foreach (explode(',', $product['tags']) as $tag): ?>
        <a href="<?= SITE_URL ?>/shop.php?q=<?= urlencode(trim($tag)) ?>" class="filter-tag" style="font-size:0.75rem"><?= h(trim($tag)) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="pdp-meta" style="margin-top:24px;font-size:0.82rem;color:#999">
        <?php if ($product['sku']): ?><p>SKU: <?= h($product['sku']) ?></p><?php endif; ?>
        <p>Category: <a href="<?= SITE_URL ?>/shop.php?cat=<?= h($product['category_slug']) ?>"><?= h($product['category_name']) ?></a></p>
      </div>
    </div><!-- /.pdp-info -->
  </div>
</div>

<!-- Customer Reviews -->
<section class="section" id="reviews">
  <div class="container" style="max-width:760px">
    <h2 class="section-title">Customer Reviews</h2>
    <?php if ($prodRating['count'] > 0): ?>
      <p style="font-size:1.1rem;margin-bottom:18px"><?= stars_html($prodRating['avg']) ?> <strong><?= $prodRating['avg'] ?></strong> / 5 &middot; <?= $prodRating['count'] ?> review<?= $prodRating['count']==1?'':'s' ?></p>
    <?php else: ?>
      <p style="color:#999;margin-bottom:18px">No reviews yet — be the first to review this product!</p>
    <?php endif; ?>

    <?php foreach ($prodReviews as $rv): ?>
    <div style="border-top:1px solid var(--grey-light);padding:14px 0">
      <div><?= stars_html((float)$rv['rating']) ?> <strong style="margin-left:6px"><?= h($rv['name']) ?></strong>
        <span style="color:#aaa;font-size:.8rem;margin-left:6px"><?= date('d M Y', strtotime($rv['created_at'])) ?></span></div>
      <p style="margin-top:6px;color:#444"><?= nl2br(h($rv['body'])) ?></p>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:24px">
      <?php if ($reviewError): ?>
      <div class="form-error" style="background:#fef2f2;border:1px solid #fca5a5;color:#c0392b;padding:10px 14px;border-radius:8px;margin-bottom:14px"><?= h($reviewError) ?></div>
      <?php endif; ?>
      <?php if (is_logged_in()): ?>
      <h3 style="margin-bottom:10px">Write a review</h3>
      <form method="post" action="#reviews">
        <?= csrf_field() ?>
        <input type="hidden" name="review_submit" value="1">
        <select name="rating" required class="form-control" style="max-width:180px;margin-bottom:10px">
          <option value="">Choose rating…</option>
          <option value="5">★★★★★ (5)</option>
          <option value="4">★★★★ (4)</option>
          <option value="3">★★★ (3)</option>
          <option value="2">★★ (2)</option>
          <option value="1">★ (1)</option>
        </select>
        <textarea name="body" class="form-control" rows="3" placeholder="Share your thoughts about this product…" required style="width:100%;margin-bottom:10px"></textarea>
        <button type="submit" class="btn btn-primary">Submit Review</button>
      </form>
      <?php else: ?>
      <p style="color:#777"><a href="<?= SITE_URL ?>/login.php?next=<?= urlencode('/product.php?slug='.$product['slug']) ?>" style="color:var(--rose-gold)">Sign in</a> to write a review.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Related Products -->
<?php if (!empty($related)): ?>
<section class="section section--pale">
  <div class="container">
    <h2 class="section-title">You May Also Like</h2>
    <div class="product-grid">
      <?php foreach ($related as $r): ?>
      <div class="product-card">
        <div class="product-card-img">
          <a href="<?= SITE_URL ?>/product.php?slug=<?= h($r['slug']) ?>">
            <img src="<?= product_img($r['image']) ?>" alt="<?= h($r['name']) ?>" loading="lazy">
          </a>
        </div>
        <div class="product-card-body">
          <div class="product-brand"><?= h($r['brand']) ?></div>
          <h3 class="product-name"><a href="<?= SITE_URL ?>/product.php?slug=<?= h($r['slug']) ?>"><?= h($r['name']) ?></a></h3>
          <div class="product-footer">
            <span class="price-current"><?= money($r['price']) ?></span>
            <button class="quick-add-btn" onclick="addToCart(<?= $r['id'] ?>, this)">Add</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
function changeQty(delta) {
  const inp = document.getElementById('pdpQty');
  inp.value = Math.max(1, Math.min(<?= $product['stock'] ?>, parseInt(inp.value) + delta));
}
function switchImg(thumb, src) {
  document.getElementById('mainProductImg').src = src;
  document.querySelectorAll('.pdp-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
