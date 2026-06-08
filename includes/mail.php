<?php
// ============================================================
// includes/mail.php — transactional email (order confirmations)
// Uses PHP mail(). From address must be a real domain mailbox
// (SITE_EMAIL) for deliverability.
// ============================================================

function tk_mail(string $to, string $subject, string $html): bool {
    $from = defined('SITE_EMAIL') ? SITE_EMAIL : 'order@tashykollections.org';
    $name = defined('SITE_NAME') ? SITE_NAME : 'Tashy Kollections';
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $name . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion(),
    ]);
    return @mail($to, $subject, $html, $headers);
}

// JMD formatter (emails always show the stored order currency, not the
// visitor's cookie-selected display currency).
function tk_jmd(float $n): string { return 'J$' . number_format($n, 2); }

function tk_order_rows(array $items): string {
    $rows = '';
    foreach ($items as $it) {
        $line = (float)$it['price'] * (int)$it['quantity'];
        $rows .= '<tr>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #eee">'
            . htmlspecialchars($it['name']) . ' &times; ' . (int)$it['quantity'] . '</td>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:right">' . tk_jmd($line) . '</td>'
            . '</tr>';
    }
    return $rows;
}

function tk_order_summary(array $o, array $items): string {
    $sym = '#c9956c';
    return '
    <div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#222">
      <h2 style="color:' . $sym . '">Tashy Kollections</h2>
      <p>Order <strong>' . htmlspecialchars($o['order_number']) . '</strong></p>
      <table style="width:100%;border-collapse:collapse;margin:12px 0">' . tk_order_rows($items) . '
        <tr><td style="padding:8px 12px;text-align:right">Subtotal</td><td style="padding:8px 12px;text-align:right">' . tk_jmd($o['subtotal']) . '</td></tr>
        <tr><td style="padding:8px 12px;text-align:right">Shipping</td><td style="padding:8px 12px;text-align:right">' . ($o['shipping'] > 0 ? tk_jmd($o['shipping']) : 'FREE') . '</td></tr>
        <tr><td style="padding:8px 12px;text-align:right">GCT (15%)</td><td style="padding:8px 12px;text-align:right">' . tk_jmd($o['tax']) . '</td></tr>
        <tr><td style="padding:8px 12px;text-align:right;font-weight:700">Total</td><td style="padding:8px 12px;text-align:right;font-weight:700">' . tk_jmd($o['total']) . '</td></tr>
      </table>
      <p style="font-size:13px;color:#555">
        <strong>Deliver to:</strong><br>' . htmlspecialchars($o['ship_name']) . '<br>'
        . nl2br(htmlspecialchars($o['ship_address'])) . '<br>'
        . htmlspecialchars($o['ship_city']) . ', ' . htmlspecialchars($o['ship_parish']) . ', Jamaica<br>'
        . htmlspecialchars($o['ship_phone']) . '
      </p>
      <p style="font-size:13px;color:#555">Payment: Cash / Bank transfer on delivery.</p>
    </div>';
}

/**
 * Send confirmation to the customer and an alert to the store.
 * $o expects keys: order_number, subtotal, shipping, tax, total,
 *   ship_name, ship_email, ship_phone, ship_address, ship_city, ship_parish
 */
function send_order_emails(array $o, array $items): void {
    $body = tk_order_summary($o, $items);
    // Customer
    if (!empty($o['ship_email']) && filter_var($o['ship_email'], FILTER_VALIDATE_EMAIL)) {
        tk_mail(
            $o['ship_email'],
            'Your Tashy Kollections order ' . $o['order_number'],
            '<p>Hi ' . htmlspecialchars($o['ship_name']) . ', thanks for your order! We\'ll be in touch to arrange delivery.</p>' . $body
        );
    }
    // Store alert
    $admin = defined('SITE_EMAIL') ? SITE_EMAIL : 'order@tashykollections.org';
    tk_mail(
        $admin,
        'New order ' . $o['order_number'] . ' — ' . $o['ship_name'],
        '<p>New order received via the website.</p>' . $body
    );
}
