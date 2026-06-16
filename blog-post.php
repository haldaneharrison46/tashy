<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$post = $slug ? get_blog_post_by_slug($slug, true) : false;

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post Not Found | ' . SITE_NAME;
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding:80px 0;text-align:center"><h1>Post not found</h1><a href="' . asset_base() . '/blog.php" class="btn btn-primary" style="margin-top:16px">Back to Journal</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Admin-authored body: trust simple HTML, otherwise escape + keep line breaks.
$bodyHtml = preg_match('/<(p|br|h[1-6]|ul|ol|li|a|strong|em|b|i|blockquote|img|div|figure)\b/i', $post['body'])
    ? $post['body']
    : nl2br(h($post['body']));

$pageTitle = h($post['title']) . ' | ' . SITE_NAME;
$metaDesc  = $post['excerpt'] ?: mb_substr(trim(strip_tags($post['body'])), 0, 160);
if ($post['cover_image']) $ogImage = product_img($post['cover_image']);
$ogType = 'article';

$recent = array_filter(get_blog_posts(['published_only' => true, 'limit' => 4]), fn($r) => $r['id'] != $post['id']);

require_once __DIR__ . '/includes/header.php';
?>
<article class="section" style="padding-top:32px">
  <div class="container" style="max-width:780px">
    <nav class="breadcrumb" style="margin-bottom:20px;font-size:0.82rem;color:#999">
      <a href="<?= asset_base() ?>/index.php">Home</a> /
      <a href="<?= asset_base() ?>/blog.php">Journal</a> /
      <span style="color:#333"><?= h($post['title']) ?></span>
    </nav>

    <div style="font-size:0.82rem;color:#999;margin-bottom:8px"><?= date('d M Y', strtotime($post['published_at'] ?: $post['created_at'])) ?></div>
    <h1 style="font-size:2rem;line-height:1.2;margin-bottom:18px"><?= h($post['title']) ?></h1>

    <?php if ($post['cover_image']): ?>
    <img src="<?= product_img($post['cover_image']) ?>" alt="<?= h($post['title']) ?>" style="width:100%;border-radius:12px;margin-bottom:24px">
    <?php endif; ?>

    <div class="blog-body" style="color:#333;line-height:1.8;font-size:1.02rem"><?= $bodyHtml ?></div>

    <?php if (!empty($post['tags'])): ?>
    <div style="margin-top:28px;display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach (array_filter(array_map('trim', explode(',', $post['tags']))) as $t): ?>
      <a href="<?= asset_base() ?>/blog.php?tag=<?= urlencode($t) ?>" class="filter-tag" style="font-size:0.78rem"><?= h($t) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:32px"><a href="<?= asset_base() ?>/blog.php" style="color:var(--rose-gold);font-weight:600">← Back to Journal</a></div>
  </div>
</article>

<?php if (!empty($recent)): ?>
<section class="section section--pale">
  <div class="container">
    <h2 class="section-title">More from the Journal</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px">
      <?php foreach (array_slice($recent, 0, 3) as $r): ?>
      <a href="<?= asset_base() ?>/blog-post.php?slug=<?= h($r['slug']) ?>" style="background:var(--white);border:1px solid var(--grey-light);border-radius:12px;overflow:hidden;text-decoration:none;color:inherit;display:flex;flex-direction:column">
        <div style="aspect-ratio:16/10;overflow:hidden;background:#f4f4f4"><img src="<?= product_img($r['cover_image'] ?: 'placeholder.svg') ?>" alt="<?= h($r['title']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover"></div>
        <div style="padding:14px"><h3 style="font-size:0.98rem;line-height:1.3"><?= h($r['title']) ?></h3></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
