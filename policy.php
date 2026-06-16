<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Policies | ' . SITE_NAME;
$metaDesc  = 'Shipping, returns, privacy, and terms for ' . SITE_NAME . ' — Falmouth, Trelawny, Jamaica.';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="container">
    <div class="text-center" style="margin-bottom:2.5rem">
      <h1 class="section-title">Our Policies</h1>
      <p class="section-sub">Clear, fair terms — so you can shop with confidence.</p>
    </div>

    <div class="policy-layout">

      <!-- Side nav -->
      <nav class="policy-nav" aria-label="Policy sections">
        <a href="#shipping" class="policy-nav-link active">Shipping</a>
        <a href="#returns"  class="policy-nav-link">Returns &amp; Refunds</a>
        <a href="#privacy"  class="policy-nav-link">Privacy Policy</a>
        <a href="#terms"    class="policy-nav-link">Terms of Service</a>
      </nav>

      <!-- Content -->
      <div class="policy-content">

        <h2 id="shipping">Shipping</h2>
        <p>
          We deliver island-wide to all 14 parishes of Jamaica. Orders are processed within 1–2 business days,
          and delivery typically takes 2–5 business days depending on your location.
          Enjoy <strong>free shipping</strong> on orders over <?= money(FREE_SHIPPING_THRESHOLD) ?>.
        </p>
        <table>
          <thead>
            <tr><th>Destination</th><th>Estimated Time</th><th>Rate</th></tr>
          </thead>
          <tbody>
            <tr><td>Falmouth &amp; Trelawny (local)</td><td>1–2 business days</td><td>From J$500</td></tr>
            <tr><td>Montego Bay &amp; western parishes</td><td>2–3 business days</td><td>From J$700</td></tr>
            <tr><td>Kingston &amp; eastern parishes</td><td>3–5 business days</td><td>From J$900</td></tr>
            <tr><td>Orders over <?= money(FREE_SHIPPING_THRESHOLD) ?></td><td>Standard times apply</td><td>FREE</td></tr>
          </tbody>
        </table>
        <p>
          You'll receive a confirmation at the email address on your order, and our team will contact you with
          delivery updates. Local pickup is available from our store at 37 Cornwall Street, Falmouth, Trelawny —
          just let us know at checkout.
        </p>

        <h2 id="returns">Returns &amp; Refunds</h2>
        <p>
          Your satisfaction matters to us. If something isn't right, you may request a return within
          <strong>7 days</strong> of receiving your order, provided the item is unused, unopened, and in its
          original packaging.
        </p>
        <p>
          For hygiene and safety reasons, <?= sk('opened bedding, bath linens, and personal-care items', 'opened cosmetics, hair, skin and personal-care items') ?> cannot be returned
          unless they arrived damaged or defective. If you receive a damaged or incorrect item, contact us within
          48 hours of delivery and we'll make it right — at no cost to you.
        </p>
        <p>
          Approved refunds are issued to your original payment method within 5–10 business days of us receiving
          the returned item. Shipping charges are non-refundable except where the return is due to our error.
          To start a return, email <a href="mailto:<?= h(SITE_EMAIL) ?>"><?= h(SITE_EMAIL) ?></a> with your order number.
        </p>

        <h2 id="privacy">Privacy Policy</h2>
        <p>
          We collect only the information needed to process your orders and serve you well — such as your name,
          contact details, delivery address, and order history. We never sell your personal data.
        </p>
        <p>
          Your information is used to fulfil orders, provide customer support, and (with your consent) share
          offers and updates. You can opt out of marketing emails at any time. We use industry-standard measures
          to protect your data, and payment details are handled securely and never stored on our servers.
        </p>
        <p>
          You may request access to, correction of, or deletion of your personal data by contacting us at
          <a href="mailto:<?= h(SITE_EMAIL) ?>"><?= h(SITE_EMAIL) ?></a>.
        </p>

        <h2 id="terms">Terms of Service</h2>
        <p>
          By using this website and placing an order, you agree to these terms. All prices are listed in
          Jamaican Dollars (JMD) and include applicable GCT at <?= rtrim(rtrim(number_format(TAX_RATE * 100, 2), '0'), '.') ?>%.
          We reserve the right to update prices, product availability, and these terms at any time.
        </p>
        <p>
          We make every effort to display products and colours accurately, but actual appearance may vary slightly.
          All products are intended for personal use as directed; please review ingredient lists and patch-test
          where appropriate. <?= h(SITE_NAME) ?> is not liable for misuse of products or for individual reactions.
        </p>
        <p>
          Orders are subject to acceptance and availability. In the rare event an item is out of stock after you
          order, we'll contact you to arrange a replacement or refund. Questions about these terms? Reach us at
          <a href="mailto:<?= h(SITE_EMAIL) ?>"><?= h(SITE_EMAIL) ?></a> or call +1 (876) 487-0686.
        </p>

      </div>
    </div>
  </div>
</section>

<script>
// Highlight the active policy section link on scroll / click
(function () {
  var links = document.querySelectorAll('.policy-nav-link');
  var sections = Array.prototype.map.call(links, function (l) {
    return document.querySelector(l.getAttribute('href'));
  });
  function setActive() {
    var pos = window.scrollY + 140;
    var current = 0;
    sections.forEach(function (s, i) { if (s && s.offsetTop <= pos) current = i; });
    links.forEach(function (l, i) { l.classList.toggle('active', i === current); });
  }
  window.addEventListener('scroll', setActive, { passive: true });
  setActive();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
