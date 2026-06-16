<?php
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$sent   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (strlen($name) < 2)                                   $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $errors[] = 'Please enter a valid email address.';
    if (strlen($message) < 10)                              $errors[] = 'Please enter a message (at least 10 characters).';

    if (empty($errors)) {
        $stmt = db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?,?,?,?)');
        $stmt->execute([$name, $email, $subject, $message]);
        flash('success', 'Thanks for reaching out! We\'ll get back to you within 24 hours.');
        redirect(asset_base() . '/contact.php');
    }
}

$pageTitle = 'Contact Us | ' . SITE_NAME;
$metaDesc  = 'Get in touch with ' . SITE_NAME . ' — visit us at 37 Cornwall Street, Falmouth, Trelawny, Jamaica, or send us a message.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="section">
  <div class="container">

    <div class="text-center" style="margin-bottom:2.5rem">
      <h1 class="section-title">Get in Touch</h1>
      <p class="section-sub">Questions about a product, an order, or wholesale? We'd love to hear from you.</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-warning" style="max-width:760px;margin:0 auto 24px">
      <div>
        <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="contact-grid">

      <!-- Contact info -->
      <div class="contact-info-card">
        <h3>Visit &amp; Reach Us</h3>

        <div class="contact-item">
          <span class="ci-icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </span>
          <div>
            <h5>Store Address</h5>
            <p><?= str_replace(', ', '<br>', h(store_address())) ?></p>
          </div>
        </div>

        <div class="contact-item">
          <span class="ci-icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </span>
          <div>
            <h5>Email</h5>
            <p><a href="mailto:<?= h(SITE_EMAIL) ?>" style="color:inherit"><?= h(SITE_EMAIL) ?></a></p>
          </div>
        </div>

        <div class="contact-item">
          <span class="ci-icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </span>
          <div>
            <h5>Phone / WhatsApp</h5>
            <p><a href="tel:+<?= h(store_phone_e164()) ?>" style="color:inherit"><?= h(store_phone()) ?></a></p>
          </div>
        </div>

        <div class="contact-item">
          <span class="ci-icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </span>
          <div>
            <h5>Opening Hours</h5>
            <p>Mon – Fri: 9:00 AM – 6:00 PM<br>Sat: 10:00 AM – 4:00 PM<br>Sun &amp; Holidays: Closed</p>
          </div>
        </div>

        <div class="response-badge">
          💬 We typically respond within 24 hours on business days.
        </div>
      </div>

      <!-- Contact form -->
      <div class="contact-form-card">
        <h3 style="margin-bottom:24px">Send a Message</h3>
        <form method="post" action="">
          <?= csrf_field() ?>
          <div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">Your Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= h($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email Address *</label>
              <input type="email" name="email" class="form-control" required value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Subject</label>
              <input type="text" name="subject" class="form-control" value="<?= h($_POST['subject'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Message *</label>
              <textarea name="message" class="form-control" rows="6" required><?= h($_POST['message'] ?? '') ?></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-primary" style="margin-top:8px">Send Message</button>
        </form>
      </div>

    </div><!-- /.contact-grid -->

    <!-- Map -->
    <div style="margin-top:48px;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-sm)">
      <iframe
        title="<?= h(SITE_NAME) ?> — <?= h(store_address()) ?>"
        src="https://www.google.com/maps?q=<?= urlencode(store_address()) ?>&output=embed"
        width="100%" height="380" style="border:0;display:block" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
