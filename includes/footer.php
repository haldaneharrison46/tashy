</main>

<!-- ░░ SITE FOOTER ░░ -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="footer-logo">Tashy<br><span>KOLLECTIONS</span></div>
        <p class="footer-tagline">Bedding, home essentials &amp; fragrances — proudly Jamaican.</p>
        <address class="footer-address">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px;margin-right:4px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?= SITE_ADDRESS ?>
        </address>
        <div class="footer-social">
          <a href="#" aria-label="Instagram">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
          </a>
          <a href="#" aria-label="Facebook">
            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          </a>
          <a href="#" aria-label="TikTok">
            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.27 6.27 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.97a8.2 8.2 0 0 0 4.79 1.53V7.06a4.85 4.85 0 0 1-1.02-.37z"/></svg>
          </a>
        </div>
      </div>

      <div class="footer-col">
        <h4>Shop</h4>
        <ul>
          <?php foreach (get_categories() as $c): ?>
          <li><a href="<?= SITE_URL ?>/shop.php?cat=<?= h($c['slug']) ?>"><?= h($c['name']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="<?= SITE_URL ?>/shop.php?featured=1">Best Sellers</a></li>
          <li><a href="<?= SITE_URL ?>/shop.php?sort=new">New Arrivals</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Help</h4>
        <ul>
          <li><a href="<?= SITE_URL ?>/contact.php">Contact Us</a></li>
          <li><a href="<?= SITE_URL ?>/policy.php">Shipping &amp; Returns</a></li>
          <li><a href="<?= SITE_URL ?>/wholesale.php">Wholesale B2B</a></li>
          <li><a href="<?= SITE_URL ?>/about.php">About Us</a></li>
          <?php if (current_user()): ?>
          <li><a href="<?= SITE_URL ?>/account.php">My Account</a></li>
          <?php else: ?>
          <li><a href="<?= SITE_URL ?>/login.php">Sign In</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="footer-col footer-newsletter">
        <h4>Stay in the loop</h4>
        <p>New drops, exclusive deals, and home styling tips — straight to your inbox.</p>
        <form class="newsletter-form" onsubmit="newsletterSignup(event)">
          <input type="email" placeholder="Your email address" required class="newsletter-input">
          <button type="submit" class="btn btn-primary btn-sm">Subscribe</button>
        </form>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="container">
      <p class="footer-copy">
        &copy; <?= date('Y') ?> <?= SITE_NAME ?> &mdash; All rights reserved.
        Built with ❤️ in Jamaica 🇯🇲
      </p>
      <div class="footer-legal">
        <a href="<?= SITE_URL ?>/policy.php">Privacy Policy</a>
        <a href="<?= SITE_URL ?>/policy.php#terms">Terms</a>
        <a href="<?= SITE_URL ?>/admin/login.php" style="opacity:0.3;font-size:0.7rem">Staff</a>
      </div>
    </div>
  </div>
</footer>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
