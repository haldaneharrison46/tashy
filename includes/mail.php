<?php
// ============================================================
// includes/mail.php — transactional email (order confirmations)
// Sender/recipient configurable in admin → Settings → Email.
// Sends via PHP mail() by default, or an SMTP relay when the
// mail method is set to "smtp".
// ============================================================
require_once __DIR__ . '/functions.php';   // get_setting()

function tk_mail(string $to, string $subject, string $html): bool {
    $fromEmail = trim((string)get_setting('mail_from_email', defined('SITE_EMAIL') ? SITE_EMAIL : 'order@tashykollections.com')) ?: 'order@tashykollections.com';
    $fromName  = trim((string)get_setting('mail_from_name',  defined('SITE_NAME')  ? SITE_NAME  : 'Tashy Kollections')) ?: 'Tashy Kollections';
    $replyTo   = trim((string)get_setting('mail_reply_to', $fromEmail)) ?: $fromEmail;

    if (get_setting('mail_method', 'mail') === 'smtp' && trim((string)get_setting('smtp_host', '')) !== '') {
        try { return tk_smtp_send($to, $subject, $html, $fromName, $fromEmail, $replyTo); }
        catch (Throwable $e) { return false; }
    }

    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $replyTo,
        'X-Mailer: PHP/' . phpversion(),
    ]);
    return @mail($to, $subject, $html, $headers);
}

// Minimal SMTP client (AUTH LOGIN). Supports STARTTLS (tls) and implicit SSL (ssl).
function tk_smtp_send(string $to, string $subject, string $html, string $fromName, string $fromEmail, string $replyTo): bool {
    $host = trim((string)get_setting('smtp_host', ''));
    $port = (int)(get_setting('smtp_port', '587') ?: 587);
    $user = trim((string)get_setting('smtp_user', ''));
    $pass = (string)get_setting('smtp_pass', '');
    $sec  = strtolower((string)get_setting('smtp_secure', 'tls'));
    if ($host === '') return false;

    $transport = ($sec === 'ssl') ? "ssl://{$host}" : $host;
    $fp = @fsockopen($transport, $port, $errno, $errstr, 20);
    if (!$fp) return false;
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;  // last line of reply
        }
        return $data;
    };
    $cmd = function (string $c) use ($fp, $read): string { fwrite($fp, $c . "\r\n"); return $read(); };

    $read(); // server greeting
    $ehloHost = preg_replace('~^.*@~', '', $fromEmail) ?: 'localhost';
    $cmd('EHLO ' . $ehloHost);
    if ($sec === 'tls') {
        $cmd('STARTTLS');
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
        $cmd('EHLO ' . $ehloHost);
    }
    if ($user !== '') {
        $cmd('AUTH LOGIN');
        $cmd(base64_encode($user));
        $r = $cmd(base64_encode($pass));
        if (strpos($r, '235') !== 0) { fclose($fp); return false; }
    }
    $cmd('MAIL FROM:<' . $fromEmail . '>');
    $rcpt = $cmd('RCPT TO:<' . $to . '>');
    if (!preg_match('/^25\d/', $rcpt)) { fclose($fp); return false; }
    $dataResp = $cmd('DATA');
    if (strpos($dataResp, '354') !== 0) { fclose($fp); return false; }

    $headers = "From: {$fromName} <{$fromEmail}>\r\n"
             . "Reply-To: {$replyTo}\r\n"
             . "To: <{$to}>\r\n"
             . 'Subject: ' . $subject . "\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n";
    $body = preg_replace('/^\./m', '..', $html);   // dot-stuffing
    $final = $cmd($headers . "\r\n" . $body . "\r\n.");
    $cmd('QUIT');
    fclose($fp);
    return preg_match('/^25\d/', $final) === 1;
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
      <h2 style="color:' . $sym . '">' . htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'Tashy Kollections') . '</h2>
      <p>Order <strong>' . htmlspecialchars($o['order_number']) . '</strong></p>
      <table style="width:100%;border-collapse:collapse;margin:12px 0">' . tk_order_rows($items) . '
        <tr><td style="padding:8px 12px;text-align:right">Subtotal</td><td style="padding:8px 12px;text-align:right">' . tk_jmd($o['subtotal']) . '</td></tr>
        <tr><td style="padding:8px 12px;text-align:right">Shipping</td><td style="padding:8px 12px;text-align:right">' . ($o['shipping'] > 0 ? tk_jmd($o['shipping']) : 'FREE') . '</td></tr>
        ' . (($o['tax'] ?? 0) > 0 ? '<tr><td style="padding:8px 12px;text-align:right">' . htmlspecialchars(tax_display_label()) . '</td><td style="padding:8px 12px;text-align:right">' . tk_jmd($o['tax']) . '</td></tr>' : '') . '
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
            'Your ' . (defined('SITE_NAME') ? SITE_NAME : 'Tashy Kollections') . ' order ' . $o['order_number'],
            '<p>Hi ' . htmlspecialchars($o['ship_name']) . ', thanks for your order! We\'ll be in touch to arrange delivery.</p>' . $body
        );
    }
    // Store alert
    $admin = trim((string)get_setting('mail_admin_recipient', defined('SITE_EMAIL') ? SITE_EMAIL : 'order@tashykollections.com'))
             ?: (defined('SITE_EMAIL') ? SITE_EMAIL : 'order@tashykollections.com');
    tk_mail(
        $admin,
        'New order ' . $o['order_number'] . ' — ' . $o['ship_name'],
        '<p>New order received via the website.</p>' . $body
    );
}
