<?php
// cron/publish_due.php — publish marketing posts whose scheduled time has passed.
// Add to IONOS cron (e.g. every 5 min):  /usr/bin/php8.2-cli /path/to/cron/publish_due.php
// Or hit via URL with the token:  https://tashykollections.com/cron/publish_due.php?key=shancron2026
require_once __DIR__ . '/../includes/marketing.php';

if (PHP_SAPI !== 'cli' && ($_GET['key'] ?? '') !== 'shancron2026') {
    http_response_code(403);
    exit('forbidden');
}

$pdo  = db();
$due  = $pdo->query("SELECT * FROM marketing_posts WHERE status='scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW() ORDER BY scheduled_at LIMIT 50")->fetchAll();
$done = 0;
foreach ($due as $post) {
    $pub = marketing_publish($post);
    $pdo->prepare('UPDATE marketing_posts SET status=?, published_at=NOW(), result=? WHERE id=?')
        ->execute([$pub['ok'] ? 'published' : 'failed', json_encode($pub['results']), $post['id']]);
    $done++;
}
echo "Published $done due post(s).\n";
