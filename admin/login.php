<?php
// ============================================================
// admin/login.php — Staff sign-in for the admin panel
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Already signed in as an admin? straight to the dashboard.
$u = current_user();
if ($u && $u['role'] === 'admin') redirect(SITE_URL . '/admin/index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $result = login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        if (($result['user']['role'] ?? '') === 'admin') {
            redirect(SITE_URL . '/admin/index.php');
        }
        logout_user();              // valid customer, but not staff
        $error = 'That account does not have admin access.';
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Sign In | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  body{display:flex;min-height:100vh;align-items:center;justify-content:center;background:#f4efe9;margin:0;font-family:'Inter',Arial,sans-serif}
  .admin-login{background:#fff;border-radius:16px;box-shadow:0 12px 44px rgba(0,0,0,.13);padding:40px;width:100%;max-width:380px;box-sizing:border-box}
  .admin-login .brand{display:block;font-weight:700;color:var(--primary,#c9956c);letter-spacing:.05em;margin-bottom:16px;font-size:1.05rem}
  .admin-login h1{font-size:1.35rem;margin:0 0 4px}
  .admin-login .sub{color:#999;font-size:.88rem;margin-bottom:24px}
  .admin-login .form-group{margin-bottom:16px}
  .admin-login label{display:block;font-size:.8rem;font-weight:600;margin-bottom:6px;color:#444}
  .admin-login input{width:100%;padding:11px 13px;border:1px solid #ddd;border-radius:8px;font-size:.95rem;box-sizing:border-box}
  .admin-login input:focus{outline:none;border-color:var(--primary,#c9956c)}
  .admin-login button{width:100%;padding:12px;border:none;border-radius:8px;background:var(--primary,#c9956c);color:#fff;font-weight:700;font-size:.95rem;cursor:pointer;margin-top:4px}
  .admin-login button:hover{filter:brightness(.96)}
  .admin-login .err{background:#fef2f2;border:1px solid #fca5a5;color:#c0392b;padding:11px 14px;border-radius:8px;margin-bottom:18px;font-size:.87rem}
  .admin-login .back{display:block;text-align:center;margin-top:20px;font-size:.82rem;color:#aaa;text-decoration:none}
  .admin-login .back:hover{color:#777}
</style>
</head>
<body>
  <form class="admin-login" method="post" action="">
    <span class="brand">⚡ <?= h(SITE_NAME) ?></span>
    <h1>Admin Sign In</h1>
    <p class="sub">Staff access only.</p>
    <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit">Sign In</button>
    <a class="back" href="<?= SITE_URL ?>/index.php">← Back to store</a>
  </form>
</body>
</html>
