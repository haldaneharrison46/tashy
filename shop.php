<?php
require_once __DIR__ . '/includes/functions.php';

// ── Query params ───────────────────────────────────────────────
$catSlug  = trim($_GET['cat']      ?? '');
$search   = trim($_GET['q']        ?? '');
$sort     = trim($_GET['sort']     ?? '');
$featured = !empty($_GET['featured']);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;

$opts = [
    'category' => $catSlug,
    'search'   => $search,
    'sort'     => $sort,
    'featured' => $featured,
    'limit'    => $perPage,
    'offset'   => ($page - 1) * $perPage,
];

$products   = get_products($opts);
$totalCount = count_products($opts);
$pagination = paginate($totalCount, $perPage, $page);
$categories = get_categories();

// Active category label
$activeCat = null;
foreach ($categories as $c) {
    if ($c['slug'] === $catSlug) { $activeCat = $c; break; }
}

$pageTitle = ($activeCat ? h($activeCat['name']) . ' — ' : '') . 'Shop | ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="shop-page-header section--dark" style="padding:48px 0 32px">
  <div class="container">
    <h1 class="section-title" style="color:#fff">
      <?php if ($search):       echo 'Search: "' . h($search) . '"';
      elseif ($featured):       echo 'Best Sellers';
      elseif ($activeCat):      echo h($activeCat['name']);
      else:                     echo 'All Products'; endif; ?>
    </h1>
    <p style="color:rgba(255,255,255,0.55);margin-top:6px"><?= $totalCount ?> product<?= $totalCount !== 1 ? 's' : '' ?> found</p>
  </div>
</div>

<div class="section" style="padding-top:32px">
  <div class="container">
    <div class="shop-layout">

      <!-- Sidebar filters -->
      <aside class="filter-sidebar">
        <div class="filter-group">
          <h4 class="filter-title">Categories</h4>
          <a href="<?= asset_base() ?>/shop.php" class="filter-tag <?= $catSlug === '' ? 'active' : '' ?>">All</a>
          <?php foreach ($categories as $c): ?>
          <a href="<?= asset_base() ?>/shop.php?cat=<?= h($c['slug']) ?>" class="filter-tag <?= $catSlug === $c['slug'] ? 'active' : '' ?>"><?= h($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <div class="filter-group" style="margin-top:24px">
          <h4 class="filter-title">Sort</h4>
          <?php $sorts = [''=>'Featured','new'=>'New Arrivals','price_asc'=>'Price: Low–High','price_desc'=>'Price: High–Low','name'=>'A–Z']; ?>
          <?php foreach ($sorts as $val => $label): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['sort' => $val, 'page' => 1])) ?>" class="filter-tag <?= $sort === $val ? 'active' : '' ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>
        <?php if ($search || $catSlug || $sort || $featured): ?>
        <div style="margin-top:20px"><a href="<?= asset_base() ?>/shop.php" class="btn btn-outline btn-sm">Clear Filters</a></div>
        <?php endif; ?>
      </aside>

      <!-- Product grid -->
      <div class="shop-main">
        <!-- Search bar -->
        <form method="get" action="" class="shop-search-bar" style="margin-bottom:20px;display:flex;gap:8px">
          <?php if ($catSlug): ?><input type="hidden" name="cat" value="<?= h($catSlug) ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search products…" class="form-control" style="flex:1">
          <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <?php if (empty($products)): ?>
        <div class="empty-state" style="text-align:center;padding:80px 0">
          <p style="font-size:1.1rem;color:#999">No products found.</p>
          <a href="<?= asset_base() ?>/shop.php" class="btn btn-primary" style="margin-top:16px">Browse All</a>
        </div>
        <?php else: ?>
        <div class="product-grid" id="productGrid">
          <?php foreach ($products as $p): ?>
          <div class="product-card" data-product-id="<?= $p['id'] ?>">
            <div class="product-card-img">
              <a href="<?= asset_base() ?>/product.php?slug=<?= h($p['slug']) ?>">
                <img src="<?= product_img($p['image']) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
              </a>
              <?php if ($p['compare_price']): ?>
              <span class="product-badge badge-sale">SALE</span>
              <?php endif; ?>
              <?php if ($p['stock'] === 0): ?>
              <span class="product-badge badge-oos">Out of Stock</span>
              <?php endif; ?>
              <button class="fav-btn" aria-label="Wishlist" data-product-id="<?= $p['id'] ?>" onclick="toggleWishlist(this)">
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
                <?php if ($p['stock'] > 0): ?>
                <button class="quick-add-btn" onclick="addToCart(<?= $p['id'] ?>, this)">Add to Cart</button>
                <?php else: ?>
                <span style="font-size:0.78rem;color:#c0392b">Out of stock</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['totalPages'] > 1): ?>
        <div class="pagination" style="margin-top:40px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
          <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
             class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div><!-- /.shop-main -->
    </div><!-- /.shop-layout -->
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
