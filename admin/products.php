<?php
$pageTitle = 'Products';
require_once __DIR__ . '/header.php';

$pdo    = db();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

/* ── Versatile image loader: validate + store an uploaded image ── */
function pf_save_image(array $file, string $base): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['skip' => true];
    if ($file['error'] !== UPLOAD_ERR_OK)       return ['error' => 'Upload failed (code ' . $file['error'] . ').'];
    if (($file['size'] ?? 0) > 6 * 1024 * 1024) return ['error' => 'An image exceeds the 6 MB limit.'];
    $info = @getimagesize($file['tmp_name']);
    $map  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $ext  = $info ? ($map[$info[2]] ?? null) : null;
    if (!$ext) return ['error' => 'Unsupported image type (use JPG, PNG, GIF, WebP).'];
    $dir = dirname(__DIR__) . '/assets/images';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = (slugify($base) ?: 'product') . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(2)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return ['error' => 'Could not save the uploaded image.'];
    return ['ok' => true, 'name' => $name];
}

/* ── Handle POST (save product) ──────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    csrf_check();
    $f = $_POST;
    $errors = [];

    $data = [
        'name'        => trim($f['name']        ?? ''),
        'brand'       => trim($f['brand']        ?? ''),
        'category_id' => (int)($f['category_id'] ?? 0),
        'description' => trim($f['description']  ?? ''),
        'price'       => (float)($f['price']      ?? 0),
        'compare_price'=> (float)($f['compare_price'] ?? 0),
        'stock'       => (int)($f['stock']        ?? 0),
        'sku'         => trim($f['sku']           ?? ''),
        'tags'        => trim($f['tags']          ?? ''),
        'active'      => isset($f['active']) ? 1 : 0,
        'featured'    => isset($f['featured']) ? 1 : 0,
    ];

    // Cover image: uploaded file > typed filename/URL > existing > placeholder
    $coverUp = pf_save_image($_FILES['image_file'] ?? [], $f['name'] ?? 'product');
    if (!empty($coverUp['ok']))        $data['image'] = $coverUp['name'];
    else {
        if (!empty($coverUp['error'])) $errors[] = $coverUp['error'];
        $data['image'] = trim($f['image'] ?? '');
    }
    if ($data['image'] === '') $data['image'] = 'placeholder.jpg';

    if (!empty($f['product_id'])) {
        $pid = (int)$f['product_id'];
        $sql = 'UPDATE products SET name=?,brand=?,category_id=?,description=?,price=?,compare_price=?,stock=?,sku=?,tags=?,active=?,featured=?,image=? WHERE id=?';
        $pdo->prepare($sql)->execute(array_merge(array_values($data), [$pid]));
    } else {
        $data['slug'] = slugify($data['name']) ?: 'product';
        $base = $data['slug']; $cnt = 0;
        $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug=?");
        do { $chk->execute([$data['slug']]); $dupe = (int)$chk->fetchColumn(); if ($dupe) $data['slug'] = $base . '-' . (++$cnt); } while ($dupe);
        $sql = 'INSERT INTO products (name,brand,category_id,description,price,compare_price,stock,sku,tags,active,featured,image,slug) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $pdo->prepare($sql)->execute([$data['name'],$data['brand'],$data['category_id'],$data['description'],$data['price'],$data['compare_price'],$data['stock'],$data['sku'],$data['tags'],$data['active'],$data['featured'],$data['image'],$data['slug']]);
        $pid = (int)$pdo->lastInsertId();
    }

    // Gallery: remove selected images
    if (!empty($f['del_img']) && is_array($f['del_img'])) {
        $ids = array_values(array_filter(array_map('intval', $f['del_img'])));
        if ($ids) {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM product_images WHERE product_id=? AND id IN ($place)")->execute(array_merge([$pid], $ids));
        }
    }
    // Gallery: add newly uploaded images
    if (!empty($_FILES['gallery']) && is_array($_FILES['gallery']['name'] ?? null)) {
        $sn = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id=?");
        $sn->execute([$pid]); $next = (int)$sn->fetchColumn();
        $ins = $pdo->prepare("INSERT INTO product_images (product_id, filename, sort_order) VALUES (?,?,?)");
        foreach ($_FILES['gallery']['name'] as $i => $_nm) {
            $one = [
                'name' => $_FILES['gallery']['name'][$i],   'type' => $_FILES['gallery']['type'][$i] ?? '',
                'tmp_name' => $_FILES['gallery']['tmp_name'][$i], 'error' => $_FILES['gallery']['error'][$i],
                'size' => $_FILES['gallery']['size'][$i],
            ];
            $r = pf_save_image($one, $f['name'] ?? 'product');
            if (!empty($r['ok']))        $ins->execute([$pid, $r['name'], ++$next]);
            elseif (!empty($r['error'])) $errors[] = $r['error'];
        }
    }

    if ($errors) flash('error', implode(' ', array_unique($errors)));
    flash('success', !empty($f['product_id']) ? 'Product saved.' : 'Product added.');
    redirect(SITE_URL . '/admin/products.php' . (!empty($f['product_id']) ? '?action=edit&id=' . $pid : ''));
}

/* ── Delete ──────────────────────────────────── */
if ($action === 'delete' && $editId) {
    csrf_check();
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$editId]);
    flash('success', 'Product deleted.');
    redirect(SITE_URL . '/admin/products.php');
}

/* ── Load product for editing ────────────────── */
$editProduct = null;
if (($action === 'edit') && $editId) {
    $editProduct = $pdo->prepare("SELECT * FROM products WHERE id=?")->execute([$editId]) ? $pdo->prepare("SELECT * FROM products WHERE id=?")->execute([$editId]) : null;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
}

/* ── List ───────────────────────────────────── */
$search    = trim($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$where = []; $params = [];
if ($search)    { $where[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($catFilter) { $where[] = "p.category_id=?"; $params[] = $catFilter; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$products = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $whereSQL ORDER BY p.id DESC LIMIT 100");
$products->execute($params);
$products = $products->fetchAll();

if ($action === 'add' || $editProduct) {
    // Show add/edit form
    $p = $editProduct ?? ['name'=>'','brand'=>'','category_id'=>0,'description'=>'','price'=>'','compare_price'=>'','stock'=>0,'sku'=>'','tags'=>'','active'=>1,'featured'=>0,'image'=>'','image2'=>'','image3'=>''];
    $formTitle = $editProduct ? 'Edit Product' : 'Add New Product';
    $existingImgs = $editProduct ? get_product_images((int)$editProduct['id']) : [];
    $formErr = flash('error');
?>
<div style="max-width:760px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="products.php" style="color:var(--rose-gold)">&larr; Back to Products</a>
  </div>
  <div class="admin-card">
    <h2><?= $formTitle ?></h2>
    <?php if ($formErr): ?><div class="badge-danger" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;display:block"><?= h($formErr) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="save_product" value="1">
      <?php if ($editProduct): ?><input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>"><?php endif; ?>
      <div class="admin-form-grid">
        <div class="form-group full">
          <label class="form-label">Product Name *</label>
          <input type="text" name="name" class="form-control" required value="<?= h($p['name']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Brand</label>
          <input type="text" name="brand" class="form-control" value="<?= h($p['brand']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-control">
            <option value="">No category</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $p['category_id']==$cat['id']?'selected':'' ?>><?= h($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Price (JMD) *</label>
          <input type="number" step="0.01" name="price" class="form-control" required value="<?= h($p['price']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Compare At Price (JMD)</label>
          <input type="number" step="0.01" name="compare_price" class="form-control" value="<?= h($p['compare_price'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Stock Qty *</label>
          <input type="number" name="stock" class="form-control" required value="<?= h($p['stock']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">SKU</label>
          <input type="text" name="sku" class="form-control" value="<?= h($p['sku']) ?>">
        </div>
        <div class="form-group full">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="5"><?= h($p['description']) ?></textarea>
        </div>
        <div class="form-group full">
          <label class="form-label">Tags (comma-separated)</label>
          <input type="text" name="tags" class="form-control" value="<?= h($p['tags']) ?>">
        </div>
        <!-- Cover image -->
        <div class="form-group full">
          <label class="form-label">Cover image</label>
          <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
            <img id="coverPreview" src="<?= h(product_img($p['image'] ?: 'placeholder.svg')) ?>" alt="" style="width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid var(--grey-light);background:#f6f6f6" onerror="this.style.opacity=.25">
            <div style="flex:1;min-width:220px">
              <input type="file" name="image_file" accept="image/*" class="form-control" onchange="pfPreviewCover(this)">
              <input type="text" name="image" class="form-control" placeholder="…or a filename in assets/images, or a full image URL" value="<?= h($p['image']) ?>" style="margin-top:8px">
              <p style="font-size:0.75rem;color:#888;margin-top:4px">Upload a file (JPG/PNG/GIF/WebP, ≤6 MB), or type a filename/URL. Uploading wins.</p>
            </div>
          </div>
        </div>

        <!-- Gallery -->
        <div class="form-group full">
          <label class="form-label">Gallery images <span style="color:#888;font-weight:400">(extra photos shown on the product page)</span></label>
          <?php if (!empty($existingImgs)): ?>
          <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px">
            <?php foreach ($existingImgs as $gi): ?>
            <div style="text-align:center">
              <img src="<?= h(product_img($gi['filename'])) ?>" alt="" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--grey-light);display:block">
              <label style="display:inline-flex;align-items:center;gap:4px;font-size:0.72rem;color:#c0392b;margin-top:3px;cursor:pointer">
                <input type="checkbox" name="del_img[]" value="<?= (int)$gi['id'] ?>"> remove
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <input type="file" name="gallery[]" accept="image/*" multiple class="form-control" onchange="pfPreviewGallery(this)">
          <div id="galleryPreview" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px"></div>
          <p style="font-size:0.75rem;color:#888;margin-top:4px">Select one or more images to add to this product's gallery.</p>
        </div>
        <div class="form-group full" style="display:flex;gap:24px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Active (visible on store)
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="featured" value="1" <?= $p['featured'] ? 'checked' : '' ?>> Featured (homepage)
          </label>
        </div>
      </div>
      <div style="display:flex;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary"><?= $editProduct ? 'Update Product' : 'Add Product' ?></button>
        <a href="products.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>
function pfPreviewCover(input){
  var f = input.files && input.files[0]; if(!f) return;
  var img = document.getElementById('coverPreview');
  img.src = URL.createObjectURL(f); img.style.opacity = 1;
}
function pfPreviewGallery(input){
  var box = document.getElementById('galleryPreview'); box.innerHTML = '';
  Array.prototype.forEach.call(input.files, function(f){
    var img = document.createElement('img');
    img.src = URL.createObjectURL(f);
    img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--grey-light)';
    box.appendChild(img);
  });
}
</script>
<?php } else { // List view
$ok  = flash('success'); ?>
<?php if ($ok): ?>
<div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px"><?= h($ok) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <form method="get" style="display:flex;gap:8px;flex:1">
    <input type="text" name="q" placeholder="Search name, brand, SKU…" class="form-control" value="<?= h($search) ?>" style="max-width:260px">
    <select name="cat" class="form-control" style="max-width:160px">
      <option value="">All Categories</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= $catFilter==$cat['id']?'selected':'' ?>><?= h($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    <?php if ($search || $catFilter): ?><a href="products.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
  <a href="products.php?action=add" class="btn btn-primary">+ Add Product</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr>
      <th style="width:50px"></th>
      <th>Product</th><th>Price</th><th>Stock</th><th>Category</th><th>Status</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
    <tr>
      <td><img src="<?= product_img($p['image']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px" alt="" onerror="this.style.display='none'"></td>
      <td>
        <div style="font-weight:600"><?= h($p['name']) ?></div>
        <div style="font-size:0.75rem;color:#888"><?= h($p['brand']) ?> <?= $p['sku'] ? '· '.$p['sku'] : '' ?></div>
      </td>
      <td style="font-weight:600"><?= money($p['price']) ?></td>
      <td>
        <span class="<?= $p['stock']<=0?'badge badge-danger':($p['stock']<=5?'badge badge-warning':'') ?>"><?= $p['stock'] ?></span>
      </td>
      <td style="color:#888;font-size:0.82rem"><?= h($p['cat_name'] ?? '—') ?></td>
      <td>
        <?php if ($p['active']): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-grey">Hidden</span><?php endif; ?>
        <?php if ($p['featured']): ?><span class="badge badge-info" style="margin-left:4px">Featured</span><?php endif; ?>
      </td>
      <td>
        <a href="products.php?action=edit&id=<?= $p['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem;margin-right:8px">Edit</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this product?')">
          <?= csrf_field() ?>
          <input type="hidden" name="_action" value="delete">
          <a href="products.php?action=delete&id=<?= $p['id'] ?>&<?= csrf_token() ?>" style="color:#c0392b;font-size:0.82rem" onclick="return confirm('Delete this product?')">Delete</a>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($products)): ?><tr><td colspan="7" style="text-align:center;color:#999;padding:28px">No products found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php } ?>
<?php require_once __DIR__ . '/footer.php'; ?>
