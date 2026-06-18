</main>

<!-- ░░ SITE FOOTER ░░ -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="footer-logo"><?= tk_logo(210) ?></div>
        <p class="footer-tagline"><?= h(store_slogan() ?: 'Bedding, home essentials & fragrances — proudly Jamaican.') ?></p>
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
          <li><a href="<?= asset_base() ?>/shop.php?cat=<?= h($c['slug']) ?>"><?= h($c['name']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="<?= asset_base() ?>/shop.php?featured=1">Best Sellers</a></li>
          <li><a href="<?= asset_base() ?>/shop.php?sort=new">New Arrivals</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Help</h4>
        <ul>
          <li><a href="<?= asset_base() ?>/track.php">Track Your Order</a></li>
          <li><a href="<?= asset_base() ?>/contact.php">Contact Us</a></li>
          <li><a href="<?= asset_base() ?>/policy.php">Shipping &amp; Returns</a></li>
          <li><a href="<?= asset_base() ?>/faq.php">FAQ</a></li>
          <li><a href="<?= asset_base() ?>/wholesale.php">Wholesale B2B</a></li>
          <li><a href="<?= asset_base() ?>/blog.php">Journal</a></li>
          <li><a href="<?= asset_base() ?>/about.php">About Us</a></li>
          <?php if (current_user()): ?>
          <li><a href="<?= asset_base() ?>/account.php">My Account</a></li>
          <?php else: ?>
          <li><a href="<?= asset_base() ?>/login.php">Sign In</a></li>
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
        <a href="<?= asset_base() ?>/policy.php">Privacy Policy</a>
        <a href="<?= asset_base() ?>/policy.php#terms">Terms</a>
        <a href="<?= asset_base() ?>/admin/login.php" style="opacity:0.3;font-size:0.7rem">Staff</a>
      </div>
    </div>
  </div>
</footer>

<script>window.TK_BASE=<?= json_encode(base_path()) ?>;</script>
<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?: '1' ?>"></script>
<?php require __DIR__ . '/contact-widget.php'; ?>

<?php $tkWelcome = trim((string) get_setting('welcome_message', '')); $tkBye = trim((string) get_setting('exit_message', '')); if ($tkWelcome !== '' || $tkBye !== ''): ?>
<script>
(function(){
  var WELCOME = <?= json_encode($tkWelcome) ?>, BYE = <?= json_encode($tkBye) ?>;
  function toast(msg){
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);background:#1a1a1a;color:#fff;padding:12px 20px;border-radius:30px;font-size:0.9rem;box-shadow:0 8px 28px rgba(0,0,0,.28);z-index:99999;opacity:0;transition:opacity .35s,transform .35s;max-width:90vw;text-align:center';
    document.body.appendChild(t);
    requestAnimationFrame(function(){ t.style.opacity='1'; t.style.transform='translateX(-50%) translateY(0)'; });
    setTimeout(function(){ t.style.opacity='0'; t.style.transform='translateX(-50%) translateY(20px)'; setTimeout(function(){ t.remove(); }, 400); }, 4500);
  }
  if (WELCOME && !sessionStorage.getItem('tk_welcomed')){
    sessionStorage.setItem('tk_welcomed','1');
    setTimeout(function(){ toast(WELCOME); }, 800);
  }
  if (BYE){
    var shown = !!sessionStorage.getItem('tk_byed');
    document.addEventListener('mouseout', function(e){
      if (shown) return;
      if (e.clientY <= 0 && !e.relatedTarget){
        shown = true; sessionStorage.setItem('tk_byed','1');
        var ov = document.createElement('div');
        ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px';
        var card = document.createElement('div');
        card.style.cssText = 'background:#fff;border-radius:16px;max-width:360px;width:100%;padding:28px;text-align:center;box-shadow:0 18px 60px rgba(0,0,0,.3)';
        card.innerHTML = '<div style="font-size:2rem;margin-bottom:8px">💛</div><div style="font-size:1.05rem;line-height:1.5;color:#222;margin-bottom:16px"></div><button type="button" style="border:none;background:var(--rose-gold,#c9956c);color:#fff;padding:10px 22px;border-radius:30px;font-weight:700;cursor:pointer">Keep shopping</button>';
        card.children[1].textContent = BYE;
        ov.appendChild(card);
        function close(){ ov.remove(); }
        ov.addEventListener('click', function(e2){ if (e2.target === ov) close(); });
        card.querySelector('button').addEventListener('click', close);
        document.body.appendChild(ov);
      }
    });
  }
})();
</script>
<?php endif; ?>
</body>
</html>
