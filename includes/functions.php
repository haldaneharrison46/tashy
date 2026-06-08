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

// ── Product reviews ───────────────────────────────────────────
function get_product_rating(int $productId): array {
    $st = db()->prepare('SELECT COUNT(*) c, AVG(rating) a FROM reviews WHERE product_id = ?');
    $st->execute([$productId]);
    $r = $st->fetch();
    return ['count' => (int)($r['c'] ?? 0), 'avg' => round((float)($r['a'] ?? 0), 1)];
}
function get_product_reviews(int $productId): array {
    $st = db()->prepare(
        'SELECT r.rating, r.body, r.created_at, u.name
         FROM reviews r JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ? ORDER BY r.created_at DESC'
    );
    $st->execute([$productId]);
    return $st->fetchAll();
}
function stars_html(float $avg): string {
    $full = (int)floor($avg);
    $half = ($avg - $full) >= 0.5;
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span style="color:#e0a93b">' . ($i <= $full ? '★' : (($i === $full + 1 && $half) ? '⯨' : '☆')) . '</span>';
    }
    return $out;
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

// ── Settings (editable key/value config) ──────────────────────
function get_setting(string $key, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT skey, sval FROM settings')->fetchAll() as $r) {
                $cache[$r['skey']] = $r['sval'];
            }
        } catch (Throwable $e) { $cache = []; }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}
function set_setting(string $key, string $val): void {
    db()->prepare('INSERT INTO settings (skey, sval) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE sval = VALUES(sval)')->execute([$key, $val]);
}

// ── Shipping ──────────────────────────────────────────────────
function shipping_default_rate(): float {
    return (float)get_setting('default_shipping_rate', '1500');
}
function free_shipping_threshold(): float {
    $v = get_setting('free_shipping_threshold', null);
    if ($v !== null && $v !== '') return (float)$v;
    return defined('FREE_SHIPPING_THRESHOLD') ? (float)FREE_SHIPPING_THRESHOLD : 5000.0;
}
function get_shipping_zones(bool $activeOnly = false): array {
    try {
        $sql = 'SELECT * FROM shipping_zones ' . ($activeOnly ? 'WHERE active = 1 ' : '') . 'ORDER BY sort_order, id';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) { return []; }
}
function find_shipping_zone(string $parish): ?array {
    $parish = trim($parish);
    if ($parish === '') return null;
    foreach (get_shipping_zones(true) as $z) {
        foreach (array_map('trim', explode(',', $z['parishes'])) as $p) {
            if ($p !== '' && strcasecmp($p, $parish) === 0) return $z;
        }
    }
    return null;
}
// Shipping charge for a parish given the order subtotal (0 if a free threshold is met).
function shipping_for_parish(string $parish, float $subtotal): float {
    if ($subtotal <= 0) return 0.0;
    $zone = find_shipping_zone($parish);
    $rate = $zone ? (float)$zone['rate'] : shipping_default_rate();
    $thr  = ($zone && $zone['free_threshold'] !== null && $zone['free_threshold'] !== '')
            ? (float)$zone['free_threshold'] : free_shipping_threshold();
    if ($thr > 0 && $subtotal >= $thr) return 0.0;
    return $rate;
}

// Jamaica parishes (shared by checkout & shipping admin)
function jamaica_parishes(): array {
    return ['Kingston','St. Andrew','St. Thomas','Portland','St. Mary','St. Ann',
            'Trelawny','St. James','Hanover','Westmoreland','St. Elizabeth',
            'Manchester','Clarendon','St. Catherine'];
}

// ── Image path helper ─────────────────────────────────────────
function product_img(string $img = '', string $fallback = 'placeholder.svg'): string {
    if (preg_match('~^https?://~i', $img)) return $img;   // allow full URLs
    return SITE_URL . '/assets/images/' . ($img ?: $fallback);
}

// ── Product gallery images (uploaded extras) ──────────────────
function get_product_images(int $productId): array {
    try {
        $st = db()->prepare('SELECT id, filename FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
        $st->execute([$productId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

// Full ordered gallery for a product: cover first, then uploaded images,
// falling back to the legacy image2/image3 columns when no uploads exist.
function product_gallery(array $product): array {
    $imgs = [];
    if (!empty($product['image'])) $imgs[] = $product['image'];
    $extra = array_column(get_product_images((int)($product['id'] ?? 0)), 'filename');
    if ($extra) {
        $imgs = array_merge($imgs, $extra);
    } else {
        foreach (['image2', 'image3'] as $k) {
            if (!empty($product[$k])) $imgs[] = $product[$k];
        }
    }
    return array_values(array_unique(array_filter($imgs)));
}
