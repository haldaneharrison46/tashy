<?php
// ============================================================
// includes/auth.php — Authentication helpers
// ============================================================
require_once __DIR__ . '/../config/db.php';

// ── Check if logged in ────────────────────────────────────────
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): array|null {
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ? AND active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if (!$user) { // user deleted / deactivated
            session_destroy();
        }
    }
    return $user;
}

function require_login(string $redirect = ''): void {
    if (!is_logged_in()) {
        $back = $redirect ?: $_SERVER['REQUEST_URI'];
        redirect(asset_base() . '/login.php?next=' . urlencode($back));
    }
}

function require_admin(): void {
    $u = current_user();
    if (!$u || $u['role'] !== 'admin') {
        redirect(asset_base() . '/admin/login.php');
    }
}

// ── Register ──────────────────────────────────────────────────
function register_user(string $name, string $email, string $password, string $phone = ''): array {
    $email = strtolower(trim($email));

    // Duplicate check
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'An account with that email already exists.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, phone) VALUES (?, ?, ?, ?)');
    $stmt->execute([trim($name), $email, $hash, trim($phone)]);
    $id = (int)db()->lastInsertId();

    login_session($id);
    return ['ok' => true, 'id' => $id];
}

// ── Login ─────────────────────────────────────────────────────
function login_user(string $email, string $password): array {
    $email = strtolower(trim($email));
    $stmt  = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
    $stmt->execute([$email]);
    $user  = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Incorrect email or password.'];
    }

    login_session($user['id']);
    merge_guest_cart($user['id']); // pull in any guest-cart items
    return ['ok' => true, 'user' => $user];
}

function login_session(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

// ── Logout ────────────────────────────────────────────────────
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Merge guest cart into user cart on login ──────────────────
function merge_guest_cart(int $userId): void {
    $sid = session_id();
    if (!$sid) return;

    $items = db()->prepare('SELECT product_id, quantity FROM cart_items WHERE session_id = ?');
    $items->execute([$sid]);
    foreach ($items->fetchAll() as $row) {
        $exists = db()->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
        $exists->execute([$userId, $row['product_id']]);
        $existing = $exists->fetch();
        if ($existing) {
            $upd = db()->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?');
            $upd->execute([$row['quantity'], $existing['id']]);
        } else {
            $ins = db()->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)');
            $ins->execute([$userId, $row['product_id'], $row['quantity']]);
        }
    }
    $del = db()->prepare('DELETE FROM cart_items WHERE session_id = ?');
    $del->execute([$sid]);
}
