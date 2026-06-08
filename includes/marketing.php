<?php
// ============================================================
// includes/marketing.php — AI copy generation + social publishing
// ============================================================
require_once __DIR__ . '/functions.php';

/* ── Low-level Claude Messages API call (raw cURL) ────────────
   The project has no Composer/SDK, so we call the REST endpoint
   directly. Model + key come from editable settings.            */
function anthropic_call(array $messages, ?string $system = null, int $maxTokens = 1024, ?array $outputFormat = null): array {
    $key = trim((string) get_setting('anthropic_api_key', ''));
    if ($key === '') return ['ok' => false, 'error' => 'No Anthropic API key set. Add one under Marketing → AI & Connections.'];
    $model = trim((string) get_setting('marketing_ai_model', 'claude-opus-4-8')) ?: 'claude-opus-4-8';

    $payload = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $messages];
    if ($system)        $payload['system'] = $system;
    if ($outputFormat)  $payload['output_config'] = ['format' => $outputFormat];
    // Note: Opus 4.8 rejects temperature/top_p/top_k — do not send them.

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return ['ok' => false, 'error' => 'Network error contacting Claude: ' . $cerr];
    $data = json_decode($resp, true);
    if ($code !== 200) {
        return ['ok' => false, 'error' => 'Claude API ' . $code . ': ' . ($data['error']['message'] ?? substr((string)$resp, 0, 300))];
    }
    $text = '';
    foreach (($data['content'] ?? []) as $b) {
        if (($b['type'] ?? '') === 'text') { $text = $b['text']; break; }
    }
    return ['ok' => true, 'text' => $text, 'usage' => $data['usage'] ?? null];
}

/* ── Generate marketing copy variants ─────────────────────────
   $opts: product (array|null), topic (string), platform, tone, count */
function ai_generate_copy(array $opts): array {
    $platform = $opts['platform'] ?? 'instagram';
    $tone     = $opts['tone']     ?? 'warm and inviting';
    $count    = max(1, min(5, (int)($opts['count'] ?? 3)));
    $voice    = trim((string) get_setting('brand_voice', ''));

    $store = defined('SITE_NAME') ? SITE_NAME : 'our store';
    $ctx = "You write social media marketing copy for {$store}, a home décor & fragrances boutique in Falmouth, Jamaica. "
         . "Prices are in Jamaican dollars (J\$). Audience: Jamaican and Caribbean home shoppers.";
    if ($voice !== '') $ctx .= " Brand voice notes: {$voice}.";

    $subject = '';
    if (!empty($opts['product']) && is_array($opts['product'])) {
        $p = $opts['product'];
        $subject = "Promote this specific product:\n"
                 . "- Name: {$p['name']}\n"
                 . (isset($p['brand']) && $p['brand'] !== '' ? "- Brand: {$p['brand']}\n" : '')
                 . (isset($p['price']) ? "- Price: J\$" . number_format((float)$p['price'], 0) . "\n" : '')
                 . (isset($p['description']) && $p['description'] !== '' ? "- Description: " . mb_substr($p['description'], 0, 400) . "\n" : '');
    } elseif (!empty($opts['topic'])) {
        $subject = "Topic / promotion to write about: " . $opts['topic'];
    } else {
        $subject = "Write a general brand-awareness post inviting people to shop our home décor & fragrances.";
    }

    $user = $subject . "\n\n"
          . "Write {$count} distinct {$platform} post option(s) in a {$tone} tone. "
          . "Each caption should be ready to post (you may use 1-3 tasteful emoji), 1-3 short sentences, with a soft call to action. "
          . "Also give 5-10 relevant hashtags per option (no '#' duplicates, lowercase, no spaces).";

    $schema = [
        'type' => 'json_schema',
        'schema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['variants'],
            'properties' => [
                'variants' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['caption', 'hashtags'],
                        'properties' => [
                            'caption'  => ['type' => 'string'],
                            'hashtags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $res = anthropic_call(
        [['role' => 'user', 'content' => $user]],
        $ctx, 1200, $schema
    );
    if (!$res['ok']) return $res;

    $parsed = json_decode($res['text'], true);
    if (!is_array($parsed) || empty($parsed['variants'])) {
        return ['ok' => false, 'error' => 'AI returned an unexpected format. Try again.'];
    }
    return ['ok' => true, 'variants' => $parsed['variants'], 'usage' => $res['usage'] ?? null];
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
