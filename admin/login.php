<?php
// ============================================================
// admin/login.php — Staff sign-in (email+password OR quick PIN)
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Already signed in as an admin? straight to the dashboard.
$u = current_user();
if ($u && $u['role'] === 'admin') redirect(asset_base() . '/admin/index.php');

$error = '';
$mode  = ($_GET['mode'] ?? '') === 'pin' || isset($_POST['pin']) ? 'pin' : 'password';

// ── Brute-force throttle (IP based, for the PIN form) ─────────
function pin_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function pin_throttle(string $ip): array {
    $st = db()->prepare('SELECT attempts, locked_until FROM pin_attempts WHERE ip = ?');
    $st->execute([$ip]);
    return $st->fetch() ?: ['attempts' => 0, 'locked_until' => null];
}
function pin_is_locked(array $row): bool {
    return !empty($row['locked_until']) && strtotime($row['locked_until']) > time();
}
function pin_register_fail(string $ip): void {
    $row = pin_throttle($ip);
    $attempts = (int)$row['attempts'] + 1;
    $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
    db()->prepare(
        'INSERT INTO pin_attempts (ip, attempts, locked_until) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until)'
    )->execute([$ip, $attempts, $lockUntil]);
}
function pin_clear(string $ip): void {
    db()->prepare('DELETE FROM pin_attempts WHERE ip = ?')->execute([$ip]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['pin'])) {
        // ── PIN sign-in ──────────────────────────────────────
        $ip = pin_client_ip();
        if (pin_is_locked(pin_throttle($ip))) {
            $error = 'Too many attempts. Try again in a few minutes, or use email & password.';
        } else {
            $pin = preg_replace('/\D/', '', (string)($_POST['pin'] ?? ''));
            $matched = null;
            if (strlen($pin) >= 4) {
                $rows = db()->query("SELECT id, admin_pin_hash FROM users WHERE role='admin' AND active=1 AND admin_pin_hash IS NOT NULL")->fetchAll();
                foreach ($rows as $r) {
                    if (password_verify($pin, $r['admin_pin_hash'])) { $matched = (int)$r['id']; break; }
                }
            }
            if ($matched) {
                pin_clear($ip);
                login_session($matched);
                redirect(asset_base() . '/admin/index.php');
            }
            pin_register_fail($ip);
            $error = 'Incorrect PIN.';
        }
    } else {
        // ── Email + password sign-in ─────────────────────────
        $result = login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['ok']) {
            if (($result['user']['role'] ?? '') === 'admin') {
                redirect(asset_base() . '/admin/index.php');
            }
            logout_user();
            $error = 'That account does not have admin access.';
        } else {
            $error = $result['error'];
        }
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
  .admin-login .sub{color:#999;font-size:.88rem;margin-bottom:22px}
  .admin-login .form-group{margin-bottom:16px}
  .admin-login label{display:block;font-size:.8rem;font-weight:600;margin-bottom:6px;color:#444}
  .admin-login input{width:100%;padding:11px 13px;border:1px solid #ddd;border-radius:8px;font-size:.95rem;box-sizing:border-box}
  .admin-login input:focus{outline:none;border-color:var(--primary,#c9956c)}
  .admin-login input.pin{letter-spacing:.5em;text-align:center;font-size:1.3rem;font-weight:700}
  .admin-login button{width:100%;padding:12px;border:none;border-radius:8px;background:var(--primary,#c9956c);color:#fff;font-weight:700;font-size:.95rem;cursor:pointer;margin-top:4px}
  .admin-login button:hover{filter:brightness(.96)}
  .admin-login .err{background:#fef2f2;border:1px solid #fca5a5;color:#c0392b;padding:11px 14px;border-radius:8px;margin-bottom:18px;font-size:.87rem}
  .admin-login .alt{display:block;text-align:center;margin-top:16px;font-size:.85rem;color:var(--primary,#c9956c);text-decoration:none;font-weight:600;cursor:pointer}
  .admin-login .back{display:block;text-align:center;margin-top:14px;font-size:.8rem;color:#aaa;text-decoration:none}
  .admin-login .back:hover{color:#777}
  .hidden{display:none}
</style>
</head>
<body>
  <div class="admin-login">
    <span class="brand">⚡ <?= h(SITE_NAME) ?></span>
    <h1>Admin Sign In</h1>
    <p class="sub">Staff access only.</p>
    <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

    <!-- Password form -->
    <form method="post" action="" id="pwForm" class="<?= $mode === 'pin' ? 'hidden' : '' ?>">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" <?= $mode !== 'pin' ? 'autofocus' : '' ?> value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password">
      </div>
      <button type="submit">Sign In</button>
      <a class="alt" onclick="tkShow('pin')">Sign in with PIN →</a>
    </form>

    <!-- PIN form -->
    <form method="post" action="" id="pinForm" class="<?= $mode === 'pin' ? '' : 'hidden' ?>">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>PIN</label>
        <input class="pin" type="password" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="off" <?= $mode === 'pin' ? 'autofocus' : '' ?>>
      </div>
      <button type="submit">Sign In with PIN</button>
      <a class="alt" onclick="tkShow('pw')">Use email &amp; password →</a>
    </form>

    <a class="back" href="<?= asset_base() ?>/index.php">← Back to store</a>
  </div>
  <script>
    function tkShow(which){
      document.getElementById('pwForm').classList.toggle('hidden', which !== 'pw');
      document.getElementById('pinForm').classList.toggle('hidden', which !== 'pin');
      var f = which === 'pin' ? document.querySelector('#pinForm .pin') : document.querySelector('#pwForm input[name=email]');
      if (f) f.focus();
    }
  </script>
</body>
</html>
