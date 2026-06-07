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

$pageTitle = h($product['name']) . ' | ' . SITE_NAME;
$metaDesc  = $product['description'] ? mb_substr(strip_tags($product['description']), 0, 160) : '';

// Related products (same category, different product)
$related = get_products(['category' => $product['category_slug'], 'limit' => 4]);
$related = array_filter($related, fn($r) => $r['id'] !== $product['id']);

require_once __DIR__ . '/includes/header.php';
?>

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
