<?php
// api/product_ai.php — admin-only. Autofill product fields.
// Modes: generate (AI from a name/query) | import_url (scrape a product page)
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/marketing.php';   // ai_complete / ai_json / ai_available

header('Content-Type: application/json');

$u = current_user();
if (!$u || $u['role'] !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Not authorized']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($in['csrf'] ?? ''))) {
    http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Invalid CSRF token']); exit;
}

$mode = $in['mode'] ?? 'generate';

/* ── Import from a product URL (Open Graph / meta scrape) ── */
if ($mode === 'import_url') {
    $url = trim($in['url'] ?? '');
    if (!filter_var($url, FILTER_VALIDATE_URL)) { echo json_encode(['ok'=>false,'error'=>'Enter a valid URL.']); exit; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TashyBot/1.0)',
    ]);
    $html = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($html === false) { echo json_encode(['ok'=>false,'error'=>'Could not fetch the page: ' . $err]); exit; }
    if ($code >= 400)    { echo json_encode(['ok'=>false,'error'=>'The page returned HTTP ' . $code . '.']); exit; }

    $meta = function (string $prop) use ($html): string {
        foreach (["property=[\"']" . preg_quote($prop, '/') . "[\"'][^>]*content=[\"']([^\"']*)",
                  "name=[\"']" . preg_quote($prop, '/') . "[\"'][^>]*content=[\"']([^\"']*)",
                  "content=[\"']([^\"']*)[\"'][^>]*(?:property|name)=[\"']" . preg_quote($prop, '/') . "[\"']"] as $re) {
            if (preg_match('/<meta[^>]*' . $re . '/i', $html, $m)) return html_entity_decode(trim($m[1]), ENT_QUOTES);
        }
        return '';
    };
    $title = $meta('og:title');
    if ($title === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) $title = html_entity_decode(trim($m[1]), ENT_QUOTES);
    $desc  = $meta('og:description') ?: $meta('description');
    $image = $meta('og:image');
    // crude price sniff
    $price = '';
    if (preg_match('/(?:price[^0-9]{0,12})(\d[\d,]*\.?\d{0,2})/i', strip_tags($html), $pm)) $price = str_replace(',', '', $pm[1]);

    if ($title === '' && $desc === '' && $image === '') { echo json_encode(['ok'=>false,'error'=>'No product details found on that page.']); exit; }
    echo json_encode(['ok'=>true, 'fields'=>[
        'name' => $title, 'description' => $desc, 'image' => $image, 'price' => $price,
    ]]);
    exit;
}

/* ── AI generate from a name / query ── */
$query = trim($in['query'] ?? '');
if ($query === '') { echo json_encode(['ok'=>false,'error'=>'Type a product name first.']); exit; }
if (!ai_available()) { echo json_encode(['ok'=>false,'error'=>'AI is not configured. Set it up under Marketing → AI & Connections.']); exit; }

$cats = [];
foreach (db()->query('SELECT name FROM categories ORDER BY name')->fetchAll() as $c) $cats[] = $c['name'];
$catList = $cats ? implode(', ', $cats) : 'Bedding, Kitchen & Bath, Mats & Rugs, Fragrances, Gift Sets';

$store = defined('SITE_NAME') ? SITE_NAME : 'a home décor store';
$sys = "You are a product cataloguer for {$store}, a home décor & fragrances shop in Jamaica. Prices are in Jamaican dollars (J$). Be accurate and concise; do not invent brand names you aren't confident about.";
$user = "Create a catalogue entry for this product: \"{$query}\".\n"
      . "Pick the best matching category from: {$catList}.\n"
      . "Suggest a realistic Jamaican retail price in JMD (number only).\n"
      . 'Respond as JSON exactly like {"name":"","brand":"","description":"","price":0,"category":"","tags":"comma,separated"}.';

$res = ai_complete($user, $sys, true, 700);
if (!$res['ok']) { echo json_encode($res); exit; }
$d = ai_json($res['text']);
if (!$d || empty($d['name'])) { echo json_encode(['ok'=>false,'error'=>'AI returned an unexpected format. Try again.']); exit; }

echo json_encode(['ok'=>true, 'fields'=>[
    'name'        => (string)($d['name'] ?? ''),
    'brand'       => (string)($d['brand'] ?? ''),
    'description' => (string)($d['description'] ?? ''),
    'price'       => is_numeric($d['price'] ?? null) ? (float)$d['price'] : '',
    'category'    => (string)($d['category'] ?? ''),
    'tags'        => is_array($d['tags'] ?? null) ? implode(',', $d['tags']) : (string)($d['tags'] ?? ''),
]]);
