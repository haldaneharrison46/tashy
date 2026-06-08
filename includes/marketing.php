<?php
// ============================================================
// includes/marketing.php — AI copy generation + social publishing
// ============================================================
require_once __DIR__ . '/functions.php';

/* ── AI provider config ───────────────────────────────────────
   Default is a FREE OpenAI-compatible endpoint (Groq). The same
   path also works with OpenRouter free models, Together, a local
   server, or a keyless endpoint (e.g. Pollinations). Switch to
   'anthropic' in settings to use a paid Claude key instead.      */
function ai_provider_cfg(): array {
    $provider = get_setting('ai_provider', 'openai');
    if ($provider === 'anthropic') {
        return [
            'provider' => 'anthropic',
            'key'      => trim((string) get_setting('anthropic_api_key', '')),
            'model'    => trim((string) get_setting('marketing_ai_model', 'claude-opus-4-8')) ?: 'claude-opus-4-8',
        ];
    }
    return [
        'provider' => 'openai',
        'base'     => rtrim(trim((string) get_setting('ai_base_url', 'https://api.groq.com/openai/v1')), '/'),
        'key'      => trim((string) get_setting('ai_api_key', '')),
        'model'    => trim((string) get_setting('ai_model', 'llama-3.3-70b-versatile')) ?: 'llama-3.3-70b-versatile',
    ];
}

function ai_available(): bool {
    $c = ai_provider_cfg();
    if ($c['provider'] === 'anthropic') return $c['key'] !== '';
    // OpenAI-compatible: a key may be optional (keyless endpoints); base + model are required.
    return $c['base'] !== '' && $c['model'] !== '';
}

// Pull a JSON object out of possibly-noisy model output.
function ai_json(string $text) {
    $d = json_decode($text, true);
    if (is_array($d)) return $d;
    if (preg_match('/\{.*\}/s', $text, $m)) {
        $d = json_decode($m[0], true);
        if (is_array($d)) return $d;
    }
    return null;
}

/* ── Provider-agnostic completion (raw cURL, no SDK) ──────────*/
function ai_complete(string $user, ?string $system = null, bool $wantJson = false, int $maxTokens = 1300): array {
    $c = ai_provider_cfg();
    if ($wantJson) $user .= "\n\nReturn ONLY valid minified JSON — no markdown fences, no commentary.";

    if ($c['provider'] === 'anthropic') {
        if ($c['key'] === '') return ['ok' => false, 'error' => 'No Anthropic API key set.'];
        $payload = ['model' => $c['model'], 'max_tokens' => $maxTokens, 'messages' => [['role' => 'user', 'content' => $user]]];
        if ($system) $payload['system'] = $system;
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 45,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['x-api-key: ' . $c['key'], 'anthropic-version: 2023-06-01', 'content-type: application/json'],
        ]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($resp === false) return ['ok' => false, 'error' => 'Network error: ' . $err];
        $data = json_decode($resp, true);
        if ($code !== 200) return ['ok' => false, 'error' => 'Claude API ' . $code . ': ' . ($data['error']['message'] ?? substr((string)$resp, 0, 200))];
        $text = '';
        foreach (($data['content'] ?? []) as $b) { if (($b['type'] ?? '') === 'text') { $text = $b['text']; break; } }
        return ['ok' => true, 'text' => $text];
    }

    // OpenAI-compatible: POST {base}/chat/completions
    $msgs = [];
    if ($system) $msgs[] = ['role' => 'system', 'content' => $system];
    $msgs[] = ['role' => 'user', 'content' => $user];
    $payload = ['model' => $c['model'], 'messages' => $msgs, 'max_tokens' => $maxTokens];
    if ($wantJson) $payload['response_format'] = ['type' => 'json_object'];
    $headers = ['content-type: application/json'];
    if ($c['key'] !== '') $headers[] = 'Authorization: Bearer ' . $c['key'];

    $post = function ($pl) use ($c, $headers) {
        $ch = curl_init($c['base'] . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 45,
            CURLOPT_POSTFIELDS => json_encode($pl), CURLOPT_HTTPHEADER => $headers,
        ]);
        $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $e = curl_error($ch); curl_close($ch);
        return [$r, $code, $e];
    };

    [$resp, $code, $err] = $post($payload);
    if ($resp === false) return ['ok' => false, 'error' => 'Network error contacting AI: ' . $err];
    $data = json_decode($resp, true);
    // Some free endpoints reject response_format — retry once without it.
    if ($code !== 200 && $wantJson && stripos((string)$resp, 'response_format') !== false) {
        unset($payload['response_format']);
        [$resp, $code, $err] = $post($payload);
        $data = json_decode($resp, true);
    }
    if ($code !== 200) return ['ok' => false, 'error' => 'AI API ' . $code . ': ' . ($data['error']['message'] ?? substr((string)$resp, 0, 200))];
    $text = $data['choices'][0]['message']['content'] ?? '';
    if ($text === '') return ['ok' => false, 'error' => 'AI returned an empty response.'];
    return ['ok' => true, 'text' => $text];
}

/* ── Generate marketing copy variants ─────────────────────────
   $opts: products (array of product rows), topic, platform, tone, count */
function ai_generate_copy(array $opts): array {
    if (!ai_available()) return ['ok' => false, 'error' => 'AI is not configured. Set a model/key under Marketing → AI & Connections.'];
    $platform = $opts['platform'] ?? 'instagram';
    $tone     = $opts['tone']     ?? 'warm and inviting';
    $count    = max(1, min(5, (int)($opts['count'] ?? 3)));
    $voice    = trim((string) get_setting('brand_voice', ''));

    $store = defined('SITE_NAME') ? SITE_NAME : 'our store';
    $ctx = "You are a social media marketer for {$store}, a home décor & fragrances boutique in Falmouth, Jamaica. "
         . "Prices are in Jamaican dollars (J\$). Audience: Jamaican and Caribbean home shoppers.";
    if ($voice !== '') $ctx .= " Brand voice notes: {$voice}.";

    // Accept either a list of products or a single product (back-compat) or a topic.
    $products = $opts['products'] ?? (!empty($opts['product']) ? [$opts['product']] : []);
    $subject = '';
    if (is_array($products) && count($products)) {
        $lines = [];
        foreach ($products as $p) {
            $lines[] = "- {$p['name']}"
                . (isset($p['brand']) && $p['brand'] !== '' ? " by {$p['brand']}" : '')
                . (isset($p['price']) ? " (J\$" . number_format((float)$p['price'], 0) . ")" : '')
                . (isset($p['description']) && $p['description'] !== '' ? " — " . mb_substr($p['description'], 0, 160) : '');
        }
        $subject = (count($products) > 1 ? "Build one ad that features these products together:\n" : "Promote this product:\n") . implode("\n", $lines);
    } elseif (!empty($opts['topic'])) {
        $subject = "Topic / promotion to write about: " . $opts['topic'];
    } else {
        $subject = "Write a general brand-awareness post inviting people to shop our home décor & fragrances.";
    }

    $user = $subject . "\n\n"
          . "Write {$count} distinct {$platform} post option(s) in a {$tone} tone. "
          . "Each caption: ready to post, 1-3 short sentences, 1-3 tasteful emoji, a soft call to action. "
          . "Also give 5-10 lowercase hashtags (no spaces, no duplicates) per option. "
          . 'Respond as JSON exactly like {"variants":[{"caption":"...","hashtags":["tag1","tag2"]}]}.';

    $res = ai_complete($user, $ctx, true, 1300);
    if (!$res['ok']) return $res;
    $parsed = ai_json($res['text']);
    if (!$parsed || empty($parsed['variants'])) return ['ok' => false, 'error' => 'AI returned an unexpected format. Try again.'];
    return ['ok' => true, 'variants' => $parsed['variants']];
}

/* ── AI suggests which products to promote right now ──────────*/
function ai_suggest_products(array $products, int $n = 4): array {
    if (!ai_available()) return ['ok' => false, 'error' => 'AI is not configured.'];
    if (empty($products)) return ['ok' => false, 'error' => 'No products to choose from.'];
    $lines = [];
    foreach ($products as $p) {
        $onSale = (!empty($p['compare_price']) && (float)$p['compare_price'] > (float)$p['price']);
        $lines[] = "id={$p['id']} | {$p['name']} | J\$" . number_format((float)$p['price'], 0)
                 . " | stock={$p['stock']}"
                 . (!empty($p['featured']) ? " | featured" : '')
                 . ($onSale ? " | on-sale" : '')
                 . (!empty($p['tags']) ? " | " . $p['tags'] : '');
    }
    $store = defined('SITE_NAME') ? SITE_NAME : 'the store';
    $user = "From {$store}'s catalogue below, choose the {$n} products most worth promoting on social media right now "
          . "(favour sale items, featured products, healthy stock, and broad appeal). Give a one-line reason for each.\n\n"
          . implode("\n", $lines)
          . "\n\nRespond as JSON exactly like {\"picks\":[{\"id\":12,\"reason\":\"...\"}]}.";
    $res = ai_complete($user, "You are a retail marketing strategist. Be selective and practical.", true, 700);
    if (!$res['ok']) return $res;
    $parsed = ai_json($res['text']);
    if (!$parsed || empty($parsed['picks'])) return ['ok' => false, 'error' => 'AI returned an unexpected format. Try again.'];
    return ['ok' => true, 'picks' => $parsed['picks']];
}

/* ── Publish: generic automation webhook (Zapier/Make/Buffer) ── */
function publish_webhook(string $url, array $post): array {
    if ($url === '') return ['ok' => false, 'info' => 'No webhook URL configured'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => json_encode($post),
        CURLOPT_HTTPHEADER => ['content-type: application/json'],
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($resp === false) return ['ok' => false, 'info' => 'Webhook network error: ' . $err];
    return ['ok' => $code >= 200 && $code < 300, 'info' => 'Webhook HTTP ' . $code];
}

/* ── Publish: Facebook Page (Graph API) ───────────────────────
   Needs a Page ID + long-lived Page access token in settings.   */
function publish_facebook(string $message, string $link = '', string $imageUrl = ''): array {
    $pageId = trim((string) get_setting('fb_page_id', ''));
    $token  = trim((string) get_setting('fb_page_token', ''));
    if ($pageId === '' || $token === '') return ['ok' => false, 'info' => 'Facebook not connected (set Page ID + token)'];

    if ($imageUrl !== '') {
        $endpoint = "https://graph.facebook.com/v21.0/{$pageId}/photos";
        $fields = ['url' => $imageUrl, 'caption' => $message, 'access_token' => $token];
    } else {
        $endpoint = "https://graph.facebook.com/v21.0/{$pageId}/feed";
        $fields = ['message' => $message, 'access_token' => $token];
        if ($link !== '') $fields['link'] = $link;
    }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query($fields),
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($resp === false) return ['ok' => false, 'info' => 'Facebook network error: ' . $err];
    $data = json_decode($resp, true);
    if ($code === 200 && !empty($data['id'])) return ['ok' => true, 'info' => 'Posted to Facebook (' . $data['id'] . ')'];
    return ['ok' => false, 'info' => 'Facebook error: ' . ($data['error']['message'] ?? ('HTTP ' . $code))];
}

/* ── Manual share links (always available) ────────────────────*/
function social_share_links(string $text, string $url = ''): array {
    $u = urlencode($url ?: (defined('SITE_URL') ? SITE_URL : ''));
    $t = urlencode($text);
    return [
        'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$u}&quote={$t}",
        'twitter'  => "https://twitter.com/intent/tweet?text={$t}&url={$u}",
        'whatsapp' => "https://wa.me/?text={$t}%20{$u}",
        'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$u}",
    ];
}

/* ── Orchestrate publishing one post to its selected platforms ─*/
function marketing_publish(array $post): array {
    $platforms = array_filter(array_map('trim', explode(',', $post['platforms'] ?? '')));
    $msg  = trim(($post['body'] ?? '') . ($post['hashtags'] ? "\n\n" . $post['hashtags'] : ''));
    $link = trim($post['link_url'] ?? '');
    $img  = '';
    if (!empty($post['image'])) {
        $img = preg_match('~^https?://~i', $post['image']) ? $post['image'] : (SITE_URL . '/assets/images/' . $post['image']);
    }

    $results = [];
    if (in_array('facebook', $platforms, true)) $results['facebook'] = publish_facebook($msg, $link, $img);
    if (in_array('webhook', $platforms, true)) {
        $results['webhook'] = publish_webhook(trim((string) get_setting('social_webhook_url', '')), [
            'title' => $post['title'] ?? '', 'message' => $msg, 'image_url' => $img,
            'link' => $link, 'platforms' => $platforms, 'source' => defined('SITE_NAME') ? SITE_NAME : '',
        ]);
    }
    // instagram / twitter without a webhook fall back to manual share (no auto-post)
    $autoChannels = ['facebook', 'webhook'];
    foreach ($platforms as $pl) {
        if (!in_array($pl, $autoChannels, true) && !isset($results[$pl])) {
            $results[$pl] = ['ok' => false, 'info' => 'Use the Share links (no auto-post connection for ' . $pl . ')'];
        }
    }
    $anyOk = false; foreach ($results as $r) { if (!empty($r['ok'])) { $anyOk = true; break; } }
    return ['ok' => $anyOk, 'results' => $results];
}
