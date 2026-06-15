<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) redirect(asset_base() . '/account.php');

$error = '';
$next  = $_GET['next'] ?? asset_base() . '/account.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $result = login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        flash('success', 'Welcome back, ' . $result['user']['name'] . '!');
        redirect($next);
    }
    $error = $result['error'];
}

$pageTitle = 'Sign In | ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page section">
  <div class="container" style="max-width:440px">
    <div class="auth-card" style="background:var(--white);border:1px solid var(--grey-light);border-radius:16px;padding:40px">
      <h1 style="font-size:1.6rem;margin-bottom:6px">Sign In</h1>
      <p style="color:#888;margin-bottom:28px">Welcome back to Tashy Kollections.</p>

      <?php if ($error): ?>
      <div class="form-error" style="background:#fef2f2;border:1px solid #fca5a5;color:#c0392b;padding:12px 16px;border-radius:8px;margin-bottom:20px"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Sign In</button>
      </form>

      <div style="text-align:center;margin-top:20px;font-size:0.9rem">
        Don't have an account? <a href="<?= asset_base() ?>/register.php" style="color:var(--rose-gold)">Create one</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
