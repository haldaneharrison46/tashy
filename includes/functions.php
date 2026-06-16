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

// ── Base URL path (sub-folder aware) ──────────────────────────
// Returns the path component of SITE_URL, e.g. '/tashy' when the site is
// served from a sub-folder, or '' at the web root. PHP links already use
// the absolute SITE_URL; this exists so client-side JS (window.TK_BASE)
// can prefix its fetch()/redirect paths to match the install location.
function base_path(): string {
    return rtrim((string)(parse_url(SITE_URL, PHP_URL_PATH) ?? ''), '/');
}

// Scheme + host (+ port) of SITE_URL, without the base path. Use this with
// REQUEST_URI (which is already host-absolute, e.g. /tashy/shop.php) to build
// canonical/OG URLs — concatenating SITE_URL directly would double the
// sub-folder (…/tashy/tashy/…).
function site_origin(): string {
    $u = parse_url(SITE_URL);
    $origin = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');
    if (!empty($u['port'])) $origin .= ':' . $u['port'];
    return $origin;
}

// ── Asset base (filesystem-derived, sub-folder aware) ─────────
// Root-relative URL prefix to the app root: '' at the web root, '/tashy' in a
// sub-folder. Computed from where the app actually lives on disk relative to
// DOCUMENT_ROOT — so on-page asset URLs (images, etc.) resolve same-origin
// regardless of whether SITE_URL is correct. Falls back to the SITE_URL path
// (base_path) if the filesystem comparison can't be made.
function asset_base(): string {
    static $base = null;
    if ($base !== null) return $base;
    $appRoot = realpath(__DIR__ . '/..');                       // app root (parent of includes/)
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($appRoot && $docRoot) {
        $appRoot = str_replace('\\', '/', $appRoot);
        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/'));
        if (strpos($appRoot, $docRoot) === 0) {
            return $base = rtrim(substr($appRoot, strlen($docRoot)), '/');
        }
    }
    return $base = base_path();
}

// ── Brand (config-driven, per-site) ───────────────────────────
// Each site brands itself from its own settings, so .com/.online show the
// default "Tashy Kollections" wordmark while Shanshan Beauty (its own DB)
// shows its own. Defaults reproduce the original Tashy logo exactly.
function brand_wordmark(): string { $v = trim((string)get_setting('brand_name', 'Tashy')); return $v !== '' ? $v : 'Tashy'; }
function brand_subtitle(): string { $v = get_setting('brand_subtitle', 'KOLLECTIONS'); return $v === null ? 'KOLLECTIONS' : $v; }
function brand_monogram(): string { $v = trim((string)get_setting('brand_monogram', 'T')); return $v !== '' ? mb_substr($v, 0, 1) : 'T'; }

// Store kind drives the site's marketing copy. 'home' = Tashy home décor
// (default), 'beauty' = Shanshan Beauty Supplies. sk() picks the variant.
function store_kind(): string { return get_setting('store_kind', 'home') === 'beauty' ? 'beauty' : 'home'; }
function sk(string $home, string $beauty): string { return store_kind() === 'beauty' ? $beauty : $home; }

// Returns the brand logo as inline SVG, scaled to $width. The wordmark uses
// currentColor so it adapts to its context (dark admin sidebar, light header);
// the emblem + subtitle stay rose-gold. Font sizes auto-fit longer names.
function tk_logo(int $width = 250, string $class = 'logo-svg'): string {
    $h     = (int) round($width * 44 / 250);
    $word  = brand_wordmark();
    $sub   = brand_subtitle();
    $mono  = brand_monogram();
    $label = defined('SITE_NAME') ? SITE_NAME : trim($word . ' ' . $sub);
    $wl    = mb_strlen($word);
    $wfs   = $wl <= 5 ? 25 : ($wl <= 7 ? 22 : ($wl <= 9 ? 19 : ($wl <= 11 ? 16 : 14)));
    $sl    = mb_strlen($sub);
    $sfs   = $sl <= 11 ? 9 : ($sl <= 16 ? 8 : 7);
    $sls   = $sl <= 11 ? '0.24em' : ($sl <= 16 ? '0.12em' : '0.06em');
    $pf    = '\'Playfair Display\', Georgia, serif';
    $inter = '\'Inter\', Arial, sans-serif';
    $out = '<svg class="' . h($class) . '" width="' . $width . '" height="' . $h . '" viewBox="0 0 250 44" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . h($label) . '">'
        . '<circle cx="22" cy="22" r="20" fill="none" stroke="#c9956c" stroke-width="1.4"/>'
        . '<circle cx="22" cy="22" r="16.4" fill="none" stroke="#c9956c" stroke-width="0.7" opacity="0.55"/>'
        . '<text x="22" y="30" text-anchor="middle" font-family="' . $pf . '" font-size="22" font-weight="700" fill="currentColor">' . h($mono) . '</text>'
        . '<path d="M13 34 q9 3.6 18 0" fill="none" stroke="#c9956c" stroke-width="1" stroke-linecap="round"/>'
        . '<circle cx="22" cy="4.6" r="1.1" fill="#c9956c"/>'
        . '<text x="52" y="28" font-family="' . $pf . '" font-size="' . $wfs . '" font-weight="700" fill="currentColor">' . h($word) . '</text>';
    if ($sub !== '') {
        $out .= '<text x="53" y="40" font-family="' . $inter . '" font-size="' . $sfs . '" font-weight="600" fill="#c9956c" letter-spacing="' . $sls . '">' . h($sub) . '</text>';
    }
    return $out . '</svg>';
}

// ── Output escaping ───────────────────────────────────────────
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

// ── Schema introspection (safe before migrations run) ─────────
// Returns true if a column exists. Lets new tax/blog code degrade
// gracefully on an un-migrated database instead of throwing.
function tk_column_exists(string $table, string $col): bool {
    static $cache = [];
    $k = $table . '.' . $col;
    if (array_key_exists($k, $cache)) return $cache[$k];
    try {
        $st = db()->prepare("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE ?");
        $st->execute([$col]);
        return $cache[$k] = (bool)$st->fetch();
    } catch (Throwable $e) {
        return $cache[$k] = false;
    }
}

// ── Tax (GCT) ─────────────────────────────────────────────────
// Tax is configurable in admin → Settings. It can be switched off
// globally, charged only for the tax country (international orders
// exempt), skipped for tax-exempt customers, and skipped per item
// (products.taxable = 0). Falls back to the TAX_RATE constant.
function tax_enabled(): bool {
    return get_setting('tax_enabled', '1') === '1';
}
function tax_rate_pct(): float {
    $v = get_setting('tax_rate', null);
    if ($v !== null && $v !== '') return (float)$v;
    return (defined('TAX_RATE') ? (float)TAX_RATE : 0.15) * 100;
}
function tax_rate(): float {            // as a fraction, e.g. 0.15
    return tax_rate_pct() / 100;
}
function tax_label(): string {
    return get_setting('tax_label', 'GCT') ?: 'GCT';
}
function tax_country(): string {
    return get_setting('tax_country', 'Jamaica') ?: 'Jamaica';
}
// Display label with rate, e.g. "GCT (15%)" — for receipts/summaries.
function tax_display_label(): string {
    return tax_label() . ' (' . rtrim(rtrim(number_format(tax_rate_pct(), 2), '0'), '.') . '%)';
}
// Is the given customer (user id) flagged tax-exempt? Safe pre-migration.
function customer_tax_exempt(?int $userId): bool {
    if (!$userId || !tk_column_exists('users', 'tax_exempt')) return false;
    try {
        $st = db()->prepare('SELECT tax_exempt FROM users WHERE id = ?');
        $st->execute([$userId]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}
// Tax owed on a taxable base. Options: country (destination), tax_exempt (bool).
function compute_tax(float $taxableBase, array $opts = []): float {
    if (!tax_enabled() || $taxableBase <= 0) return 0.0;
    if (!empty($opts['tax_exempt'])) return 0.0;
    $country = trim((string)($opts['country'] ?? tax_country()));
    if ($country !== '' && strcasecmp($country, tax_country()) !== 0) return 0.0;
    return round($taxableBase * tax_rate(), 2);
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
    // Same-origin, sub-folder aware: resolves correctly at the web root or under
    // /tashy and doesn't break when SITE_URL's host is misconfigured.
    return asset_base() . '/assets/images/' . ($img ?: $fallback);
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

// ── Shared image upload helper (validate + store) ─────────────
// Mirrors admin/products.php's pf_save_image so other admin screens
// (blog, etc.) can accept uploads. Returns one of:
//   ['skip'=>true] | ['error'=>msg] | ['ok'=>true,'name'=>filename]
function tk_save_image(array $file, string $base): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['skip' => true];
    if ($file['error'] !== UPLOAD_ERR_OK)       return ['error' => 'Upload failed (code ' . $file['error'] . ').'];
    if (($file['size'] ?? 0) > 6 * 1024 * 1024) return ['error' => 'The image exceeds the 6 MB limit.'];
    $info = @getimagesize($file['tmp_name']);
    $map  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $ext  = $info ? ($map[$info[2]] ?? null) : null;
    if (!$ext) return ['error' => 'Unsupported image type (use JPG, PNG, GIF, WebP).'];
    $dir = dirname(__DIR__) . '/assets/images';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = (slugify($base) ?: 'image') . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(2)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return ['error' => 'Could not save the uploaded image.'];
    return ['ok' => true, 'name' => $name];
}

// ── Blog ──────────────────────────────────────────────────────
function get_blog_posts(array $opts = []): array {
    try {
        $where = []; $params = [];
        if (!empty($opts['published_only'])) $where[] = "status = 'published'";
        if (!empty($opts['tag'])) { $where[] = 'tags LIKE ?'; $params[] = '%' . $opts['tag'] . '%'; }
        $sql = 'SELECT * FROM blog_posts';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY COALESCE(published_at, created_at) DESC';
        if (!empty($opts['limit'])) {
            $sql .= ' LIMIT ' . (int)$opts['limit'] . ' OFFSET ' . (int)($opts['offset'] ?? 0);
        }
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}
function count_blog_posts(array $opts = []): int {
    try {
        $where = []; $params = [];
        if (!empty($opts['published_only'])) $where[] = "status = 'published'";
        if (!empty($opts['tag'])) { $where[] = 'tags LIKE ?'; $params[] = '%' . $opts['tag'] . '%'; }
        $sql = 'SELECT COUNT(*) FROM blog_posts' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $st = db()->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
function get_blog_post_by_slug(string $slug, bool $publishedOnly = true): array|false {
    try {
        $sql = 'SELECT * FROM blog_posts WHERE slug = ?' . ($publishedOnly ? " AND status = 'published'" : '');
        $st = db()->prepare($sql);
        $st->execute([$slug]);
        return $st->fetch();
    } catch (Throwable $e) { return false; }
}

// ── Settings helpers (typed) ──────────────────────────────────
function setting_bool(string $key, bool $default = false): bool {
    $v = get_setting($key, $default ? '1' : '0');
    return $v === '1' || $v === 'on' || $v === 'true';
}
function store_slogan(): string { return (string)get_setting('store_slogan', ''); }
function store_address(): string { return get_setting('store_address', defined('SITE_ADDRESS') ? SITE_ADDRESS : ''); }
function store_phone(): string { return get_setting('store_phone', '+1 (876) 487-0686'); }
// Digits with Jamaica country code, for tel:/wa.me links.
function store_phone_e164(): string {
    $d = preg_replace('/\D+/', '', store_phone());
    if (strlen($d) === 10) $d = '1' . $d;   // add +1 if a bare 10-digit number
    return $d;
}

// ── Vendors / suppliers ───────────────────────────────────────
function get_vendors(bool $activeOnly = false): array {
    try {
        $sql = 'SELECT * FROM vendors ' . ($activeOnly ? 'WHERE active = 1 ' : '') . 'ORDER BY name';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) { return []; }
}

// ── Preset (canned) messages ──────────────────────────────────
// scope: 'pos' (receipt share), 'order' (status updates), 'marketing'
function get_preset_messages(string $scope): array {
    try {
        $st = db()->prepare('SELECT * FROM preset_messages WHERE scope = ? ORDER BY sort_order, id');
        $st->execute([$scope]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}
// Fill {name}/{order}/{total}/{store} placeholders in a preset.
function fill_preset(string $body, array $vars): string {
    foreach ($vars as $k => $v) $body = str_replace('{' . $k . '}', (string)$v, $body);
    return $body;
}

// ── Payment methods ───────────────────────────────────────────
function payment_methods(): array {
    return [
        'cod'      => ['label' => 'Cash on Delivery',  'setting' => 'pay_cod_enabled',      'default' => true],
        'transfer' => ['label' => 'Bank Transfer',     'setting' => 'pay_transfer_enabled', 'default' => true],
        'card'     => ['label' => 'Card',              'setting' => 'pay_card_enabled',     'default' => false],
        'paypal'   => ['label' => 'PayPal',            'setting' => 'pay_paypal_enabled',   'default' => false],
    ];
}
function enabled_payment_methods(): array {
    $out = [];
    foreach (payment_methods() as $k => $m) {
        if (setting_bool($m['setting'], $m['default'])) $out[$k] = $m['label'];
    }
    if (!$out) $out['cod'] = 'Cash on Delivery';   // never leave checkout with no method
    return $out;
}
// Build a PayPal.me payment URL for an amount, or '' if not configured.
function paypal_link(float $amount): string {
    $me = trim((string)get_setting('paypal_me', ''));
    if ($me === '') return '';
    $me = ltrim(preg_replace('~^https?://(www\.)?paypal\.me/~i', '', $me), '/@');
    return 'https://www.paypal.me/' . rawurlencode($me) . '/' . number_format($amount, 2, '.', '');
}

// ── Idempotent schema migrations ──────────────────────────────
// Adds the tax/blog columns & tables to an existing database without
// touching data. Safe to run repeatedly. Returns a log of actions.
function tk_run_migrations(): array {
    $pdo = db();
    $log = [];
    $do = function (string $sql, string $label) use ($pdo, &$log) {
        try { $pdo->exec($sql); $log[] = '✓ ' . $label; }
        catch (Throwable $e) { $log[] = '✕ ' . $label . ' — ' . $e->getMessage(); }
    };

    if (!tk_column_exists('products', 'taxable')) {
        $do("ALTER TABLE products ADD COLUMN taxable TINYINT(1) NOT NULL DEFAULT 1 AFTER active",
            'products.taxable column');
    } else { $log[] = '• products.taxable already present'; }

    if (!tk_column_exists('users', 'tax_exempt')) {
        $do("ALTER TABLE users ADD COLUMN tax_exempt TINYINT(1) NOT NULL DEFAULT 0 AFTER active",
            'users.tax_exempt column');
    } else { $log[] = '• users.tax_exempt already present'; }

    // Order scheduling + tracking
    foreach ([
        'ship_date'       => "ALTER TABLE orders ADD COLUMN ship_date DATE DEFAULT NULL AFTER ship_country",
        'tracking_number' => "ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100) DEFAULT NULL AFTER ship_date",
        'carrier'         => "ALTER TABLE orders ADD COLUMN carrier VARCHAR(80) DEFAULT NULL AFTER tracking_number",
    ] as $col => $sql) {
        if (!tk_column_exists('orders', $col)) $do($sql, "orders.$col column");
        else $log[] = "• orders.$col already present";
    }

    $do("CREATE TABLE IF NOT EXISTS blog_posts (
            id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            title        VARCHAR(200)  NOT NULL,
            slug         VARCHAR(200)  NOT NULL UNIQUE,
            excerpt      VARCHAR(500)  DEFAULT NULL,
            body         MEDIUMTEXT    NOT NULL,
            cover_image  VARCHAR(255)  DEFAULT NULL,
            tags         VARCHAR(500)  DEFAULT NULL,
            status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
            author_id    INT UNSIGNED  DEFAULT NULL,
            published_at DATETIME      DEFAULT NULL,
            created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_published (published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'blog_posts table');

    // Vendors / suppliers
    $do("CREATE TABLE IF NOT EXISTS vendors (
            id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            name         VARCHAR(150)  NOT NULL,
            contact_name VARCHAR(120)  DEFAULT NULL,
            email        VARCHAR(150)  DEFAULT NULL,
            phone        VARCHAR(40)   DEFAULT NULL,
            address      VARCHAR(255)  DEFAULT NULL,
            notes        TEXT          DEFAULT NULL,
            active       TINYINT(1)    NOT NULL DEFAULT 1,
            created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'vendors table');

    if (!tk_column_exists('products', 'vendor_id')) {
        $do("ALTER TABLE products ADD COLUMN vendor_id INT UNSIGNED DEFAULT NULL AFTER category_id",
            'products.vendor_id column');
    } else { $log[] = '• products.vendor_id already present'; }

    // Inventory receiving
    $do("CREATE TABLE IF NOT EXISTS inventory_receipts (
            id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            vendor_id   INT UNSIGNED  DEFAULT NULL,
            reference   VARCHAR(80)   DEFAULT NULL,
            note        VARCHAR(255)  DEFAULT NULL,
            total_cost  DECIMAL(10,2) NOT NULL DEFAULT 0,
            received_by VARCHAR(100)  DEFAULT NULL,
            created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_vendor (vendor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'inventory_receipts table');
    $do("CREATE TABLE IF NOT EXISTS inventory_receipt_items (
            id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            receipt_id  INT UNSIGNED  NOT NULL,
            product_id  INT UNSIGNED  DEFAULT NULL,
            name        VARCHAR(200)  NOT NULL,
            quantity    INT           NOT NULL DEFAULT 0,
            unit_cost   DECIMAL(10,2) NOT NULL DEFAULT 0,
            INDEX idx_receipt (receipt_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'inventory_receipt_items table');

    // Preset (canned) messages
    $do("CREATE TABLE IF NOT EXISTS preset_messages (
            id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            scope      VARCHAR(20)   NOT NULL DEFAULT 'pos',
            title      VARCHAR(120)  NOT NULL,
            body       TEXT          NOT NULL,
            sort_order INT           NOT NULL DEFAULT 0,
            created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_scope (scope)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'preset_messages table');

    // Seed settings (INSERT IGNORE keeps any admin-edited values)
    foreach ([
        'tax_enabled' => '1',
        'tax_rate'    => (string)(defined('TAX_RATE') ? TAX_RATE * 100 : 15),
        'tax_label'   => 'GCT',
        'tax_country' => 'Jamaica',
        // Store
        'store_phone'    => '+1-876-487-0686',
        'store_hours'    => 'Mon–Sat 9am–6pm',
        'store_slogan'   => 'Bedding, home essentials & fragrances — proudly Jamaican.',
        'receipt_header' => '',
        'receipt_footer' => 'Thank you for shopping with us!',
        // Printer
        'receipt_width' => '80',
        'print_copies'  => '1',
        'auto_print'    => '0',
        // Payment
        'pay_cod_enabled'       => '1',
        'pay_transfer_enabled'  => '1',
        'pay_card_enabled'      => '0',
        'pay_paypal_enabled'    => '0',
        'bank_transfer_details' => '',
        'card_instructions'     => '',
        'paypal_me'             => '',
        'paypal_email'          => '',
        // Email
        'mail_from_name'       => defined('SITE_NAME') ? SITE_NAME : 'Tashy Kollections',
        'mail_from_email'      => defined('SITE_EMAIL') ? SITE_EMAIL : '',
        'mail_reply_to'        => defined('SITE_EMAIL') ? SITE_EMAIL : '',
        'mail_admin_recipient' => defined('SITE_EMAIL') ? SITE_EMAIL : '',
        'mail_method'          => 'mail',
        'smtp_host'            => '',
        'smtp_port'            => '587',
        'smtp_user'            => '',
        'smtp_pass'            => '',
        'smtp_secure'          => 'tls',
        // Marketing / announcement bar
        'announcement_enabled' => '0',
        'announcement_text'    => '',
        'announcement_link'    => '',
        'announcement_speed'   => '18',
    ] as $k => $v) {
        try {
            $pdo->prepare('INSERT IGNORE INTO settings (skey, sval) VALUES (?, ?)')->execute([$k, $v]);
        } catch (Throwable $e) {}
    }
    $log[] = '✓ store/printer/payment/email/marketing settings seeded';
    return $log;
}
