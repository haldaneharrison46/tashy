<?php
// ============================================================
// includes/orders.php — order workflow (status history, notifications, tagging)
// ============================================================
require_once __DIR__ . '/functions.php';

function order_statuses(): array {
    return ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
}
function order_status_colors(): array {
    return ['pending'=>'warning','processing'=>'info','shipped'=>'info','delivered'=>'success','cancelled'=>'danger'];
}

function get_order_history(int $orderId): array {
    $st = db()->prepare('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at, id');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

// Move an order to a new status: update, log history, and notify the customer.
function record_order_status(int $orderId, string $status, string $note = '', ?string $by = null, bool $notify = true): bool {
    if (!in_array($status, order_statuses(), true)) return false;
    $pdo = db();
    $cur = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $cur->execute([$orderId]);
    $old = $cur->fetchColumn();
    if ($old === false) return false;

    $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, note, created_by) VALUES (?,?,?,?)')
        ->execute([$orderId, $status, $note !== '' ? $note : null, $by]);

    if ($notify && $old !== $status) {
        try { notify_order_status($orderId, $status); } catch (Throwable $e) { /* non-fatal */ }
    }
    return true;
}

// Email the customer that their order has moved.
function notify_order_status(int $orderId, string $status): void {
    $st = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o || empty($o['ship_email']) || !filter_var($o['ship_email'], FILTER_VALIDATE_EMAIL)) return;

    $msg = [
        'pending'    => 'has been received and is awaiting processing',
        'processing' => 'is now being prepared',
        'shipped'    => 'is on its way to you',
        'delivered'  => 'has been delivered — we hope you love it!',
        'cancelled'  => 'has been cancelled',
    ][$status] ?? ('is now ' . $status);

    $track = SITE_URL . '/track.php?order=' . urlencode($o['order_number']);
    $accent = '#c9956c';
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#222">'
          . '<h2 style="color:' . $accent . '">' . (defined('SITE_NAME') ? SITE_NAME : 'Tashy Kollections') . '</h2>'
          . '<p>Hi ' . htmlspecialchars($o['ship_name']) . ',</p>'
          . '<p>Your order <strong>' . htmlspecialchars($o['order_number']) . '</strong> ' . $msg . '.</p>'
          . '<p style="margin:18px 0"><a href="' . $track . '" style="background:' . $accent . ';color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Track your order</a></p>'
          . '<p style="font-size:13px;color:#666">Status: <strong>' . ucfirst($status) . '</strong></p>'
          . '</div>';

    if (function_exists('tk_mail')) {
        tk_mail($o['ship_email'], 'Order ' . $o['order_number'] . ' — ' . ucfirst($status), $html);
    }
}

// Email the customer their delivery schedule / tracking details.
function notify_shipping(int $orderId): bool {
    $st = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o || empty($o['ship_email']) || !filter_var($o['ship_email'], FILTER_VALIDATE_EMAIL)) return false;
    if (!function_exists('tk_mail')) return false;

    $accent = '#c9956c';
    $track  = SITE_URL . '/track.php?order=' . urlencode($o['order_number']);
    $rows   = '';
    if (!empty($o['ship_date']))       $rows .= '<p>Scheduled delivery: <strong>' . htmlspecialchars(date('l, d M Y', strtotime($o['ship_date']))) . '</strong></p>';
    if (!empty($o['carrier']))         $rows .= '<p>Carrier: <strong>' . htmlspecialchars($o['carrier']) . '</strong></p>';
    if (!empty($o['tracking_number'])) $rows .= '<p>Tracking #: <strong>' . htmlspecialchars($o['tracking_number']) . '</strong></p>';

    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#222">'
          . '<h2 style="color:' . $accent . '">' . (defined('SITE_NAME') ? SITE_NAME : 'Tashy Kollections') . '</h2>'
          . '<p>Hi ' . htmlspecialchars($o['ship_name']) . ',</p>'
          . '<p>Here are the delivery details for your order <strong>' . htmlspecialchars($o['order_number']) . '</strong>:</p>'
          . ($rows ?: '<p>Your order is being prepared for delivery.</p>')
          . '<p style="margin:18px 0"><a href="' . $track . '" style="background:' . $accent . ';color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Track your order</a></p>'
          . '</div>';
    return tk_mail($o['ship_email'], 'Delivery update — order ' . $o['order_number'], $html);
}

// WhatsApp / mailto quick-contact links for a customer tag.
function order_contact_links(array $o): array {
    $phone = preg_replace('/[^0-9]/', '', $o['ship_phone'] ?? '');
    if ($phone !== '' && strlen($phone) <= 10) $phone = '1' . $phone; // default Jamaica country code
    $msg = rawurlencode('Hi ' . ($o['ship_name'] ?? '') . ', regarding your order ' . ($o['order_number'] ?? '') . ': ');
    return [
        'whatsapp' => $phone ? "https://wa.me/{$phone}?text={$msg}" : '',
        'email'    => !empty($o['ship_email']) ? 'mailto:' . $o['ship_email'] . '?subject=' . rawurlencode('Your order ' . ($o['order_number'] ?? '')) : '',
        'sms'      => $phone ? "sms:+{$phone}" : '',
    ];
}
