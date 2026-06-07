<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) redirect(SITE_URL . '/account.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $phone    = trim($_POST['phone']    ?? '');

    if (strlen($name) < 2)        $error = 'Please enter your full name.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Please enter a valid email.';
    elseif (strlen($password) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($password !== $confirm) $error = 'Passwords do not match.';
    else {
        $result = register_user($name, $email, $password, $phone);
        if ($result['ok']) {
            flash('success', 'Account created! Welcome to Tashy Kollections.');
            redirect(SITE_URL . '/account.php');
        }
        $error = $result['error'];
    }
}

$pageTitle = 'Create Account | ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page section">
  <div class="container" style="max-width:480px">
    <div class="auth-card" style="background:var(--white);border:1px solid var(--grey-light);border-radius:16px;padding:40px">
      <h1 style="font-size:1.6rem;margin-bottom:6px">Create Account</h1>
      <p style="color:#888;margin-bottom:28px">Join Tashy Kollections — exclusive deals await.</p>

      <?php if ($error): ?>
      <div class="form-error" style="background:#fef2f2;border:1px solid #fca5a5;color:#c0392b;padding:12px 16px;border-radius:8px;margin-bottom:20px"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid" style="grid-template-columns:1fr 1fr;display:grid;gap:14px">
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" required value="<?= h($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required minlength="8">
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm" class="form-control" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Phone (optional)</label>
            <input type="tel" name="phone" class="form-control" value="<?= h($_POST['phone'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Create Account</button>
      </form>

      <div style="text-align:center;margin-top:20px;font-size:0.9rem">
        Already have an account? <a href="<?= SITE_URL ?>/login.php" style="color:var(--rose-gold)">Sign in</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
