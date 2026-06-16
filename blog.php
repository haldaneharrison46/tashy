<?php
require_once __DIR__ . '/includes/functions.php';

$tag     = trim($_GET['tag'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;

$opts = ['published_only' => true, 'tag' => $tag, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage];
$posts      = get_blog_posts($opts);
$totalCount = count_blog_posts(['published_only' => true, 'tag' => $tag]);
$pagination = paginate($totalCount, $perPage, $page);

$pageTitle = 'Journal | ' . SITE_NAME;
$metaDesc  = 'News, styling tips and stories from ' . SITE_NAME . '.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="shop-page-header section--dark" style="padding:48px 0 32px">
  <div class="container">
    <h1 class="section-title" style="color:#fff">Our Journal<?= $tag ? ' — ' . h($tag) : '' ?></h1>
    <p style="color:rgba(255,255,255,0.55);margin-top:6px">Stories, styling tips & news from <?= h(SITE_NAME) ?></p>
  </div>
</div>

<div class="section" style="padding-top:32px">
  <div class="container">
    <?php if (empty($posts)): ?>
    <div class="empty-state" style="text-align:center;padding:80px 0">
      <p style="font-size:1.1rem;color:#999"><?= $tag ? 'No posts tagged “' . h($tag) . '”.' : 'No posts yet — check back soon!' ?></p>
      <?php if ($tag): ?><a href="<?= asset_base() ?>/blog.php" class="btn btn-primary" style="margin-top:16px">All Posts</a><?php endif; ?>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px">
      <?php foreach ($posts as $po): ?>
      <article style="background:var(--white);border:1px solid var(--grey-light);border-radius:12px;overflow:hidden;display:flex;flex-direction:column">
        <a href="<?= asset_base() ?>/blog-post.php?slug=<?= h($po['slug']) ?>" style="display:block;aspect-ratio:16/10;overflow:hidden;background:#f4f4f4">
          <img src="<?= product_img($po['cover_image'] ?: 'placeholder.svg') ?>" alt="<?= h($po['title']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover">
        </a>
        <div style="padding:18px;display:flex;flex-direction:column;flex:1">
          <div style="font-size:0.76rem;color:#999;margin-bottom:6px"><?= date('d M Y', strtotime($po['published_at'] ?: $po['created_at'])) ?></div>
          <h2 style="font-size:1.12rem;line-height:1.3;margin-bottom:8px"><a href="<?= asset_base() ?>/blog-post.php?slug=<?= h($po['slug']) ?>" style="color:inherit;text-decoration:none"><?= h($po['title']) ?></a></h2>
          <p style="color:#666;font-size:0.9rem;line-height:1.55;flex:1"><?= h($po['excerpt'] ?: mb_substr(trim(strip_tags($po['body'])), 0, 140) . '…') ?></p>
          <a href="<?= asset_base() ?>/blog-post.php?slug=<?= h($po['slug']) ?>" style="color:var(--rose-gold);font-size:0.85rem;margin-top:12px;font-weight:600">Read more →</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <?php if ($pagination['totalPages'] > 1): ?>
    <div class="pagination" style="margin-top:40px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
      <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
