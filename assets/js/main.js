/**
 * Tashy Kollections — main.js
 * Adapted from the static-site components.js for the PHP/MySQL site.
 */

/* ── Early theme restore (runs synchronously before DOM parse finishes) ── */
(function () {
  var TVARS = {
    light: { '--off-white': '#ffffff', '--white': '#ffffff', '--grey-light': '#f0f0f0', '--rose-pale': '#fff7f4' },
    dark: {
      '--off-white': '#111214', '--white': '#1d1f22', '--grey-light': '#28292d', '--grey-mid': '#888888',
      '--grey-dark': '#b8b8b8', '--black': '#e8e5e0', '--black-soft': '#26282c', '--rose-pale': '#1e120a',
      '--rose-gold': '#d4916a', '--rose-light': '#e8ae86'
    },
    pink: {
      '--black': '#3d1525', '--black-soft': '#581e36', '--rose-gold': '#c84b70', '--rose-light': '#e07898',
      '--rose-pale': '#fce0ea', '--gold-accent': '#b83860', '--off-white': '#fff5f8', '--white': '#fffafb',
      '--grey-light': '#f8dde8', '--grey-dark': '#7a3050', '--grey-mid': '#b07090'
    },
    blue: {
      '--black': '#1a2a45', '--black-soft': '#25396a', '--rose-gold': '#4480c0', '--rose-light': '#70a8e4',
      '--rose-pale': '#daeeff', '--gold-accent': '#3670b0', '--off-white': '#f3f8ff', '--white': '#f9fbff',
      '--grey-light': '#d8eaf8', '--grey-dark': '#3a5880', '--grey-mid': '#6090b8'
    }
  };
  var t = localStorage.getItem('sb-theme');
  if (t && t !== 'default' && TVARS[t]) {
    document.documentElement.setAttribute('data-theme', t);
    var v = TVARS[t];
    Object.keys(v).forEach(function (p) { document.documentElement.style.setProperty(p, v[p]); });
  }
})();

/* ── DOMContentLoaded ─────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {

  /* ── Theme switcher ───────────────────────────── */
  var THEME_VARS = {
    light: { '--off-white': '#ffffff', '--white': '#ffffff', '--grey-light': '#f0f0f0', '--rose-pale': '#fff7f4' },
    dark: {
      '--off-white': '#111214', '--white': '#1d1f22', '--grey-light': '#28292d', '--grey-mid': '#888888',
      '--grey-dark': '#b8b8b8', '--black': '#e8e5e0', '--black-soft': '#26282c', '--rose-pale': '#1e120a',
      '--rose-gold': '#d4916a', '--rose-light': '#e8ae86'
    },
    pink: {
      '--black': '#3d1525', '--black-soft': '#581e36', '--rose-gold': '#c84b70', '--rose-light': '#e07898',
      '--rose-pale': '#fce0ea', '--gold-accent': '#b83860', '--off-white': '#fff5f8', '--white': '#fffafb',
      '--grey-light': '#f8dde8', '--grey-dark': '#7a3050', '--grey-mid': '#b07090'
    },
    blue: {
      '--black': '#1a2a45', '--black-soft': '#25396a', '--rose-gold': '#4480c0', '--rose-light': '#70a8e4',
      '--rose-pale': '#daeeff', '--gold-accent': '#3670b0', '--off-white': '#f3f8ff', '--white': '#f9fbff',
      '--grey-light': '#d8eaf8', '--grey-dark': '#3a5880', '--grey-mid': '#6090b8'
    }
  };
  var ALL_THEME_PROPS = [
    '--off-white', '--white', '--grey-light', '--grey-mid', '--grey-dark',
    '--black', '--black-soft', '--rose-pale', '--rose-gold', '--rose-light', '--gold-accent'
  ];

  function applyTheme(key) {
    var k = key || 'default';
    var root = document.documentElement;
    ALL_THEME_PROPS.forEach(function (p) { root.style.removeProperty(p); });
    if (k === 'default') {
      root.removeAttribute('data-theme');
    } else {
      root.setAttribute('data-theme', k);
      var vars = THEME_VARS[k];
      if (vars) { Object.keys(vars).forEach(function (p) { root.style.setProperty(p, vars[p]); }); }
    }
    localStorage.setItem('sb-theme', k);
    updateThemeActive();
  }

  function updateThemeActive() {
    var current = localStorage.getItem('sb-theme') || 'default';
    document.querySelectorAll('[data-theme-key]').forEach(function (el) {
      el.classList.toggle('active', el.dataset.themeKey === current);
    });
  }

  document.querySelectorAll('[data-theme-key]').forEach(function (el) {
    el.addEventListener('click', function () { applyTheme(el.dataset.themeKey); });
  });
  updateThemeActive();

  /* ── Mobile drawer toggle ─────────────────────── */
  var menuBtn   = document.getElementById('mobileMenuBtn');
  var menuDrawer = document.getElementById('mobileDrawer');
  var overlay   = document.getElementById('drawerOverlay');

  function openDrawer(drawer) {
    if (drawer) drawer.classList.add('open');
    if (overlay) overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  function closeAllDrawers() {
    document.querySelectorAll('.drawer').forEach(function (d) { d.classList.remove('open'); });
    if (overlay) overlay.classList.remove('show');
    document.body.style.overflow = '';
  }

  if (menuBtn) menuBtn.addEventListener('click', function () { openDrawer(menuDrawer); });
  if (overlay) overlay.addEventListener('click', closeAllDrawers);
  document.querySelectorAll('.drawer-close').forEach(function (btn) {
    btn.addEventListener('click', closeAllDrawers);
  });

  /* ── Cart drawer ──────────────────────────────── */
  var cartBtn    = document.getElementById('cartBtn');
  var cartDrawer = document.getElementById('cartDrawer');
  if (cartBtn && cartDrawer) {
    cartBtn.addEventListener('click', function (e) { e.preventDefault(); openDrawer(cartDrawer); });
  }

  /* ── AJAX Add-to-cart ─────────────────────────── */
  document.querySelectorAll('[data-add-to-cart]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var pid = btn.dataset.addToCart;
      var qtyEl = document.getElementById('qtyInput');
      var qty = qtyEl ? parseInt(qtyEl.value) : 1;

      btn.disabled = true;
      btn.textContent = 'Adding…';

      fetch('/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', product_id: parseInt(pid), quantity: qty })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) {
            showToast(data.message || 'Added to cart!');
            updateCartCount(data.count);
            // Reload cart drawer contents
            reloadCartDrawer();
          } else {
            showToast(data.error || 'Could not add to cart.', true);
          }
        })
        .catch(function () { showToast('Connection error.', true); })
        .finally(function () { btn.disabled = false; btn.textContent = 'Add to Cart'; });
    });
  });

  function updateCartCount(n) {
    document.querySelectorAll('.cart-count').forEach(function (el) {
      el.textContent = n || '';
      el.style.display = n ? 'flex' : 'none';
    });
  }

  function reloadCartDrawer() {
    var drawer = document.getElementById('cartDrawer');
    if (!drawer) return;
    fetch('/api/cart.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        updateCartCount(data.count);
        // Refresh the page to get updated drawer from PHP
        // (lightweight approach for server-rendered cart)
        // For full SPA drawer, we'd re-render here; for now open drawer and let user see
      });
  }

  /* ── Wishlist toggle ──────────────────────────── */
  document.querySelectorAll('[data-wishlist]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var pid = btn.dataset.wishlist;
      fetch('/api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: parseInt(pid) })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) {
            var added = data.action === 'added';
            btn.classList.toggle('active', added);
            btn.title = added ? 'Remove from wishlist' : 'Add to wishlist';
            showToast(added ? 'Added to wishlist!' : 'Removed from wishlist.');
          } else if (data.login) {
            window.location.href = '/login.php?next=' + encodeURIComponent(window.location.pathname);
          } else {
            showToast(data.error || 'Error.', true);
          }
        });
    });
  });

  /* ── Live search autocomplete ─────────────────── */
  var searchInput  = document.getElementById('searchInput');
  var searchResults = document.getElementById('searchResults');
  var searchTimer;

  if (searchInput && searchResults) {
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      var q = searchInput.value.trim();
      if (q.length < 2) { searchResults.style.display = 'none'; return; }
      searchTimer = setTimeout(function () {
        fetch('/api/search.php?q=' + encodeURIComponent(q) + '&limit=6')
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.ok || !data.results.length) { searchResults.style.display = 'none'; return; }
            searchResults.innerHTML = data.results.map(function (p) {
              return '<a href="' + p.url + '" class="search-result-item" style="display:flex;gap:10px;align-items:center;padding:10px 14px;border-bottom:1px solid var(--grey-light);text-decoration:none;color:inherit">' +
                '<img src="' + p.image + '" style="width:36px;height:36px;object-fit:cover;border-radius:6px" alt="">' +
                '<div><div style="font-weight:600;font-size:0.85rem">' + p.name + '</div>' +
                '<div style="font-size:0.75rem;color:#888">' + p.brand + ' · ' + p.price_fmt + '</div></div></a>';
            }).join('');
            searchResults.style.display = 'block';
          });
      }, 220);
    });

    document.addEventListener('click', function (e) {
      if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
      }
    });
  }

  /* ── Toast notification ───────────────────────── */
  function showToast(msg, isError) {
    var toast = document.getElementById('toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'toast';
      toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:14px 22px;border-radius:10px;font-size:0.9rem;font-weight:600;max-width:300px;box-shadow:0 4px 16px rgba(0,0,0,.15);transition:opacity .3s';
      document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.background = isError ? '#fef2f2' : '#1a1a1a';
    toast.style.color = isError ? '#c0392b' : '#fff';
    toast.style.opacity = '1';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(function () { toast.style.opacity = '0'; }, 3000);
  }

  /* ── Qty stepper (PDP) ────────────────────────── */
  var qtyInput = document.getElementById('qtyInput');
  document.querySelectorAll('[data-qty]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!qtyInput) return;
      var v = parseInt(qtyInput.value) || 1;
      var max = parseInt(qtyInput.max) || 99;
      if (btn.dataset.qty === '+') qtyInput.value = Math.min(max, v + 1);
      else qtyInput.value = Math.max(1, v - 1);
    });
  });

  /* ── Image gallery (PDP) ──────────────────────── */
  document.querySelectorAll('.thumb-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var mainImg = document.getElementById('mainImage');
      if (mainImg) mainImg.src = btn.dataset.src;
      document.querySelectorAll('.thumb-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
    });
  });

  /* ── Accordion / FAQ ──────────────────────────── */
  document.querySelectorAll('.accordion-header').forEach(function (header) {
    header.addEventListener('click', function () {
      var body = header.nextElementSibling;
      var isOpen = body && body.style.display === 'block';
      document.querySelectorAll('.accordion-body').forEach(function (b) { b.style.display = 'none'; });
      document.querySelectorAll('.accordion-header').forEach(function (h) { h.classList.remove('open'); });
      if (!isOpen && body) { body.style.display = 'block'; header.classList.add('open'); }
    });
  });

  /* ── Flash message auto-dismiss ───────────────── */
  document.querySelectorAll('.flash-msg').forEach(function (el) {
    setTimeout(function () { el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 400); }, 4000);
  });

});
