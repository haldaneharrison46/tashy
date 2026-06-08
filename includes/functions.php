<?php
// ============================================================
// includes/functions.php — General helper functions
// ============================================================
require_once __DIR__ . '/../config/db.php';

// ── Fallback site constants ───────────────────────────────────
// config/db.php is gitignored, so older copies may not define these.
if (!defined('SITE_ADDRESS')) {
    define('SITE_ADDRESS', '37 Cornwall Street, Falmouth, Trelawny, Jamaica');
}

// ── Output escaping ───────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Redirect ──────────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ── Flash messages ────────────────────────────────────────────
function flash(string $key, string $msg = ''): string {
    if ($msg !== '') {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $val = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $val;
}

// ── CSRF token ────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}
function csrf_check(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ── Currency ──────────────────────────────────────────────────
// Prices are stored in JMD (base). Display converts using fixed rates
// (edit rates here as needed — they are display-only; orders record JMD).
function currency_config(): array {
    return [
        'jmd' => ['symbol' => 'J$',  'rate' => 1.0,    'label' => '🇯🇲 JMD'],
        'usd' => ['symbol' => 'US$', 'rate' => 0.0064, 'label' => '🇺🇸 USD'],
        'gbp' => ['symbol' => '£',   'rate' => 0.0050, 'label' => '🇬🇧 GBP'],
        'eur' => ['symbol' => '€',   'rate' => 0.0059, 'label' => '🇪🇺 EUR'],
    ];
}
function current_currency(): string {
    $c = strtolower($_COOKIE['cur'] ?? 'jmd');
    return array_key_exists($c, currency_config()) ? $c : 'jmd';
}
function money(float $amount): string {
    $cfg = currency_config()[current_currency()];
    return $cfg['symbol'] . number_format($amount * $cfg['rate'], 2);
}

// ── Slug generator ────────────────────────────────────────────
function slugify(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// ── Pagination helper ─────────────────────────────────────────
function paginate(int $total, int $perPage, int $page): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;
    return compact('totalPages', 'page', 'offset', 'perPage', 'total');
}

// ── Product helpers ───────────────────────────────────────────
function get_product_by_slug(string $slug): array|false {
    $stmt = db()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.slug = ? AND p.active = 1');
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function get_products(array $opts = []): array {
    $where  = ['p.active = 1'];
    $params = [];

    if (!empty($opts['category'])) {
        $where[]  = 'c.slug = ?';
        $params[] = $opts['category'];
    }
    if (!empty($opts['search'])) {
        $where[]  = 'MATCH(p.name, p.brand, p.description, p.tags) AGAINST(? IN BOOLEAN MODE)';
        $params[] = $opts['search'] . '*';
    }
    if (!empty($opts['featured'])) {
        $where[] = 'p.featured = 1';
    }

    $orderMap = [
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'new'        => 'p.created_at DESC',
        'name'       => 'p.name ASC',
    ];
    $order = $orderMap[$opts['sort'] ?? ''] ?? 'p.featured DESC, p.created_at DESC';

    $limit  = (int)($opts['limit']  ?? 20);
    $offset = (int)($opts['offset'] ?? 0);

    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p LEFT JOIN categories c ON p.category_id = c.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $order . '
            LIMIT ' . $limit . ' OFFSET ' . $offset;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function count_products(array $opts = []): int {
    $where  = ['p.active = 1'];
    $params = [];
    if (!empty($opts['category'])) { $where[] = 'c.slug = ?'; $params[] = $opts['category']; }
    if (!empty($opts['search']))   {
        $where[] = 'MATCH(p.name, p.brand, p.description, p.tags) AGAINST(? IN BOOLEAN MODE)';
        $params[] = $opts['search'] . '*';
    }
    $sql  = 'SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE ' . implode(' AND ', $where);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function get_categories(): array {
    return db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
}

// ── Order number generator ────────────────────────────────────
function generate_order_number(): string {
    return 'TK-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Y');
}

// ── Image path helper ─────────────────────────────────────────
function product_img(string $img = '', string $fallback = 'placeholder.svg'): string {
    return SITE_URL . '/assets/images/' . ($img ?: $fallback);
}
