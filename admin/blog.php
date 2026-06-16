<?php
$pageTitle = 'Blog';
require_once __DIR__ . '/header.php';

$pdo    = db();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

// Guard: blog table must exist (run DB updates in Settings otherwise).
$blogReady = (function () { try { db()->query('SELECT 1 FROM blog_posts LIMIT 1'); return true; } catch (Throwable $e) { return false; } })();
if (!$blogReady) {
    echo '<div class="badge-warning" style="padding:14px 18px;border-radius:8px;display:block">The blog table isn\'t set up yet. Go to <a href="settings.php#data" style="color:inherit;text-decoration:underline">Settings → Database updates</a> and click <strong>Apply database updates</strong>, then come back.</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── Save (create / update) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
    csrf_check();
    $f = $_POST;
    $errors = [];
    $id      = (int)($f['post_id'] ?? 0);
    $title   = trim($f['title'] ?? '');
    $excerpt = trim($f['excerpt'] ?? '');
    $body    = trim($f['body'] ?? '');
    $tags    = trim($f['tags'] ?? '');
    $status  = ($f['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

    if ($title === '') $errors[] = 'Title is required.';
    if ($body === '')  $errors[] = 'Post body is required.';

    // Cover image: uploaded file wins, else typed filename/URL.
    $cover   = trim($f['cover_image'] ?? '');
    $coverUp = tk_save_image($_FILES['cover_file'] ?? [], $title ?: 'post');
    if (!empty($coverUp['ok']))         $cover = $coverUp['name'];
    elseif (!empty($coverUp['error']))  $errors[] = $coverUp['error'];

    if ($errors) {
        flash('error', implode(' ', array_unique($errors)));
        redirect(asset_base() . '/admin/blog.php?action=' . ($id ? 'edit&id=' . $id : 'new'));
    }

    if ($id) {
        // Set published_at the first time it goes live.
        $cur = $pdo->prepare('SELECT status, published_at FROM blog_posts WHERE id=?');
        $cur->execute([$id]); $row = $cur->fetch();
        $pubAt = $row['published_at'] ?? null;
        if ($status === 'published' && !$pubAt) $pubAt = date('Y-m-d H:i:s');
        if ($status === 'draft') $pubAt = $row['published_at']; // keep history but stays hidden
        $pdo->prepare('UPDATE blog_posts SET title=?, excerpt=?, body=?, cover_image=?, tags=?, status=?, published_at=? WHERE id=?')
            ->execute([$title, $excerpt, $body, $cover ?: null, $tags ?: null, $status, $pubAt, $id]);
    } else {
        // Unique slug from title
        $slug = slugify($title) ?: 'post';
        $base = $slug; $n = 0;
        $chk = $pdo->prepare('SELECT COUNT(*) FROM blog_posts WHERE slug=?');
        do { $chk->execute([$slug]); $dupe = (int)$chk->fetchColumn(); if ($dupe) $slug = $base . '-' . (++$n); } while ($dupe);
        $pubAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare('INSERT INTO blog_posts (title, slug, excerpt, body, cover_image, tags, status, author_id, published_at) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$title, $slug, $excerpt, $body, $cover ?: null, $tags ?: null, $status, current_user()['id'], $pubAt]);
        $id = (int)$pdo->lastInsertId();
    }
    flash('success', 'Post saved.');
    redirect(asset_base() . '/admin/blog.php?action=edit&id=' . $id);
}

/* ── Delete ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM blog_posts WHERE id=?')->execute([(int)$_POST['post_id']]);
    flash('success', 'Post deleted.');
    redirect(asset_base() . '/admin/blog.php');
}

/* ── Load for edit ── */
$editPost = null;
if ($action === 'edit' && $editId) {
    $st = $pdo->prepare('SELECT * FROM blog_posts WHERE id=?');
    $st->execute([$editId]);
    $editPost = $st->fetch();
}

/* ════════════════ EDITOR ════════════════ */
if ($action === 'new' || $editPost) {
    $p = $editPost ?? ['id'=>0,'title'=>'','slug'=>'','excerpt'=>'','body'=>'','cover_image'=>'','tags'=>'','status'=>'draft'];
    $formErr = flash('error');
?>
<div style="max-width:820px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="blog.php" style="color:var(--rose-gold)">&larr; Back to Blog</a>
    <?php if ($editPost && $editPost['status'] === 'published'): ?>
    <a href="<?= asset_base() ?>/blog-post.php?slug=<?= h($editPost['slug']) ?>" target="_blank" style="margin-left:auto;color:var(--rose-gold);font-size:0.85rem">View live →</a>
    <?php endif; ?>
  </div>
  <div class="admin-card">
    <h2><?= $editPost ? 'Edit Post' : 'New Blog Post' ?></h2>
    <?php if ($formErr): ?><div class="badge-danger" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;display:block"><?= h($formErr) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="save_post" value="1">
      <?php if ($editPost): ?><input type="hidden" name="post_id" value="<?= (int)$editPost['id'] ?>"><?php endif; ?>
      <div class="admin-form-grid">
        <div class="form-group full">
          <label class="form-label">Title *</label>
          <input type="text" name="title" class="form-control" required value="<?= h($p['title']) ?>">
        </div>
        <div class="form-group full">
          <label class="form-label">Excerpt <span style="color:#888;font-weight:400">(short summary for the blog list)</span></label>
          <textarea name="excerpt" class="form-control" rows="2"><?= h($p['excerpt'] ?? '') ?></textarea>
        </div>
        <div class="form-group full">
          <label class="form-label">Body *</label>
          <textarea name="body" class="form-control" rows="14" required style="font-family:inherit;line-height:1.6"><?= h($p['body']) ?></textarea>
          <p style="font-size:0.75rem;color:#888;margin-top:4px">Plain text with line breaks, or simple HTML (paragraphs, links, headings) — it is shown as written.</p>
        </div>
        <div class="form-group full">
          <label class="form-label">Cover image</label>
          <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
            <img id="coverPreview" src="<?= h(product_img($p['cover_image'] ?: 'placeholder.svg')) ?>" alt="" style="width:96px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--grey-light);background:#f6f6f6" onerror="this.style.opacity=.25">
            <div style="flex:1;min-width:220px">
              <input type="file" name="cover_file" accept="image/*" class="form-control" onchange="(function(i){var f=i.files&&i.files[0];if(f){var im=document.getElementById('coverPreview');im.src=URL.createObjectURL(f);im.style.opacity=1;}})(this)">
              <input type="text" name="cover_image" class="form-control" placeholder="…or a filename in assets/images, or a full image URL" value="<?= h($p['cover_image'] ?? '') ?>" style="margin-top:8px">
            </div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Tags (comma-separated)</label>
          <input type="text" name="tags" class="form-control" value="<?= h($p['tags'] ?? '') ?>" placeholder="home decor, tips">
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="draft"     <?= ($p['status'] ?? 'draft')==='draft' ? 'selected' : '' ?>>Draft (hidden)</option>
            <option value="published" <?= ($p['status'] ?? '')==='published' ? 'selected' : '' ?>>Published (live)</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary"><?= $editPost ? 'Update Post' : 'Create Post' ?></button>
        <a href="blog.php" class="btn btn-outline">Cancel</a>
        <?php if ($editPost): ?>
        <form method="post" style="margin-left:auto" onsubmit="return confirm('Delete this post?')">
          <?= csrf_field() ?>
          <input type="hidden" name="delete_post" value="1">
          <input type="hidden" name="post_id" value="<?= (int)$editPost['id'] ?>">
          <button type="submit" class="btn btn-sm" style="background:#fef2f2;color:#c0392b;border:1px solid #fca5a5">Delete</button>
        </form>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; exit; }

/* ════════════════ LIST ════════════════ */
$posts = $pdo->query('SELECT * FROM blog_posts ORDER BY id DESC LIMIT 200')->fetchAll();
$ok = flash('success'); $err = flash('error');
?>
<?php if ($ok): ?><div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px"><?= h($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="badge-danger" style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:block"><?= h($err) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;margin-bottom:18px">
  <h2 style="font-size:1.05rem">Blog Posts</h2>
  <a href="blog.php?action=new" class="btn btn-primary" style="margin-left:auto">+ New Post</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr><th style="width:60px"></th><th>Title</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $po): ?>
    <tr>
      <td><img src="<?= product_img($po['cover_image'] ?: 'placeholder.svg') ?>" style="width:48px;height:32px;object-fit:cover;border-radius:5px" alt="" onerror="this.style.display='none'"></td>
      <td>
        <div style="font-weight:600"><?= h($po['title']) ?></div>
        <div style="font-size:0.76rem;color:#888"><?= h(mb_substr($po['excerpt'] ?? '', 0, 70)) ?></div>
      </td>
      <td><span class="badge <?= $po['status']==='published' ? 'badge-success' : 'badge-grey' ?>"><?= ucfirst($po['status']) ?></span></td>
      <td style="font-size:0.8rem;color:#888"><?= date('d M y', strtotime($po['published_at'] ?: $po['created_at'])) ?></td>
      <td><a href="blog.php?action=edit&id=<?= (int)$po['id'] ?>" style="color:var(--rose-gold);font-size:0.82rem">Edit →</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($posts)): ?><tr><td colspan="5" style="text-align:center;color:#999;padding:28px">No posts yet — write your first one.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
