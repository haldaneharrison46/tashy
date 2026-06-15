<?php
require_once __DIR__ . '/includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $business = trim($_POST['business'] ?? '');
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $type     = trim($_POST['type']     ?? '');
    $details  = trim($_POST['details']  ?? '');

    if (strlen($business) < 2)                       $errors[] = 'Please enter your business name.';
    if (strlen($name) < 2)                           $errors[] = 'Please enter a contact name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Please enter a valid email address.';
    if (strlen($phone) < 7)                          $errors[] = 'Please enter a contact phone number.';

    if (empty($errors)) {
        $subject = 'Wholesale Application — ' . $business;
        $message = "Business: {$business}\n"
                 . "Contact: {$name}\n"
                 . "Phone: {$phone}\n"
                 . "Business type: {$type}\n\n"
                 . "Details:\n{$details}";
        $stmt = db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?,?,?,?)');
        $stmt->execute([$name, $email, $subject, $message]);
        flash('success', 'Application received! Our wholesale team will be in touch within 1–2 business days.');
        redirect(asset_base() . '/wholesale.php');
    }
}

$pageTitle = 'Wholesale B2B | ' . SITE_NAME;
$metaDesc  = 'Partner with Tashy Kollections — wholesale home décor, bedding & fragrances for hotels, resorts, villas, gift shops, and resellers across Jamaica. Trade pricing, reliable supply.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ░░ HERO ░░ -->
<section class="wholesale-hero">
  <div class="container">
    <span class="wholesale-nav-badge" style="margin-bottom:16px;display:inline-block">PRO</span>
    <h1>Grow your business with <span>Tashy Kollections</span></h1>
    <p>Quality home décor, bedding &amp; fragrances at trade pricing — for hotels, resorts, villas, gift shops, and resellers across Jamaica.</p>
    <div class="stat-row">
      <div class="stat-item"><strong>500+</strong><span>Products in Stock</span></div>
      <div class="stat-item"><strong>14</strong><span>Parishes Delivered</span></div>
      <div class="stat-item"><strong>100%</strong><span>Authentic Brands</span></div>
    </div>
    <div style="margin-top:36px">
      <a href="#apply" class="btn btn-primary btn-lg">Apply for an Account</a>
    </div>
  </div>
</section>

<!-- ░░ BENEFITS ░░ -->
<section class="section">
  <div class="container">
    <div class="text-center" style="margin-bottom:2.5rem">
      <h2 class="section-title">Why Partner With Us</h2>
      <p class="section-sub">Everything you need to stock with confidence.</p>
    </div>
    <div class="value-grid">
      <div class="value-card">
        <div class="icon">🏷️</div>
        <h4>Trade Pricing</h4>
        <p>Competitive wholesale rates that protect your margins, with volume discounts as you grow.</p>
      </div>
      <div class="value-card">
        <div class="icon">📦</div>
        <h4>Reliable Supply</h4>
        <p>Consistent stock of best-selling brands so you never have to turn a client away.</p>
      </div>
      <div class="value-card">
        <div class="icon">✅</div>
        <h4>Guaranteed Authentic</h4>
        <p>Every item sourced from authorised suppliers — protect your reputation with genuine product.</p>
      </div>
      <div class="value-card">
        <div class="icon">🚚</div>
        <h4>Island-Wide Logistics</h4>
        <p>Bulk delivery to all 14 parishes, coordinated around your business schedule.</p>
      </div>
      <div class="value-card">
        <div class="icon">🧾</div>
        <h4>Flexible Ordering</h4>
        <p>Reorder favourites quickly, with a dedicated point of contact for your account.</p>
      </div>
      <div class="value-card">
        <div class="icon">🤝</div>
        <h4>Real Partnership</h4>
        <p>Product guidance, new-arrival previews, and support that treats your growth as our own.</p>
      </div>
    </div>
  </div>
</section>

<!-- ░░ HOW IT WORKS ░░ -->
<section class="section section--pale">
  <div class="container">
    <div class="text-center" style="margin-bottom:3rem">
      <h2 class="section-title">How It Works</h2>
      <p class="section-sub">From application to your first order in four simple steps.</p>
    </div>
    <div class="step-grid">
      <div class="step-card">
        <h4>Apply</h4>
        <p>Submit the form below with your business details — it takes just a few minutes.</p>
      </div>
      <div class="step-card">
        <h4>Get Approved</h4>
        <p>Our team reviews your application and reaches out within 1–2 business days.</p>
      </div>
      <div class="step-card">
        <h4>Browse &amp; Order</h4>
        <p>Access trade pricing and place your wholesale order across our full catalogue.</p>
      </div>
      <div class="step-card">
        <h4>We Deliver</h4>
        <p>Receive your stock island-wide and keep your shelves full of what clients love.</p>
      </div>
    </div>
  </div>
</section>

<!-- ░░ APPLY ░░ -->
<section class="section" id="apply">
  <div class="container">
    <div class="text-center" style="margin-bottom:2.5rem">
      <h2 class="section-title">Apply for a Wholesale Account</h2>
      <p class="section-sub">Tell us about your business and we'll get you set up.</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-warning" style="max-width:760px;margin:0 auto 24px">
      <div><?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <div class="apply-form-card">
      <form method="post" action="#apply">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Business Name *</label>
            <input type="text" name="business" class="form-control" required value="<?= h($_POST['business'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Contact Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= h($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" required value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number *</label>
            <input type="tel" name="phone" class="form-control" required value="<?= h($_POST['phone'] ?? '') ?>">
          </div>
          <div class="form-group full">
            <label class="form-label">Business Type</label>
            <select name="type" class="form-control">
              <?php
                $types = ['Hotel / Resort', 'Villa / Airbnb Host', 'Gift Shop', 'Home Décor Retailer', 'Online Reseller', 'Other'];
                $sel = $_POST['type'] ?? '';
                foreach ($types as $t):
              ?>
              <option value="<?= h($t) ?>" <?= $sel === $t ? 'selected' : '' ?>><?= h($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full">
            <label class="form-label">Tell us about your needs</label>
            <textarea name="details" class="form-control" rows="5" placeholder="Product categories of interest, estimated monthly volume, location…"><?= h($_POST['details'] ?? '') ?></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="margin-top:8px">Submit Application</button>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
