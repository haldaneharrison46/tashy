<?php
/**
 * JSON API — Newsletter subscribe
 * POST /api/newsletter.php { email }
 */
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');
function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Method not allowed'], 405);

$body  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = strtolower(trim((string)($body['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Please enter a valid email address.'], 400);
}

try {
    db()->prepare('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)')->execute([$email]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Could not subscribe right now.'], 500);
}

json_out(['ok' => true, 'message' => 'Thanks for subscribing! 🎉']);
