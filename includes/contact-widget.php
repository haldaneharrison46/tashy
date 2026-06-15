<?php
// ============================================================
// includes/contact-widget.php
// Floating quick-contact launcher (WhatsApp / Call / SMS / Email)
// + optional Tidio all-in-one chat (AI bot + live agent + WhatsApp + email).
//
// Tidio: define TIDIO_KEY in config/db.php to switch the chat widget on, e.g.
//   define('TIDIO_KEY', 'abc123yourpublickey');
// Until then, only the free quick-contact buttons render.
// ============================================================

$tkPhoneIntl = '+18764870686';   // tel: / sms:  (E.164)
$tkPhoneWa   = '18764870686';    // wa.me        (digits only, no +)
$tkPhoneShow = '+1 (876) 487-0686';
$tkWaText    = rawurlencode('Hi Tashy Kollections! I have a question.');
$tkEmail     = defined('SITE_EMAIL') ? SITE_EMAIL : 'order@tashykollections.com';
$tidioKey    = defined('TIDIO_KEY') ? trim((string) TIDIO_KEY) : '';
?>
<style>
  .tk-contact{position:fixed;left:18px;bottom:18px;z-index:9998;font-family:inherit}
  .tk-contact-fab{width:56px;height:56px;border:none;border-radius:50%;cursor:pointer;
    background:var(--primary,#c9956c);color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.22);
    display:flex;align-items:center;justify-content:center;transition:transform .2s ease,background .2s ease}
  .tk-contact-fab:hover{transform:scale(1.06)}
  .tk-contact-fab svg{width:26px;height:26px}
  .tk-contact-fab .tk-ico-close{display:none}
  .tk-contact.open .tk-contact-fab .tk-ico-open{display:none}
  .tk-contact.open .tk-contact-fab .tk-ico-close{display:block}
  .tk-contact-menu{position:absolute;left:0;bottom:68px;display:flex;flex-direction:column;gap:10px;
    opacity:0;transform:translateY(10px);pointer-events:none;transition:opacity .2s ease,transform .2s ease}
  .tk-contact.open .tk-contact-menu{opacity:1;transform:translateY(0);pointer-events:auto}
  .tk-cbtn{display:flex;align-items:center;gap:10px;padding:10px 16px 10px 12px;border-radius:30px;
    background:#fff;color:#2b2b2b;text-decoration:none;font-size:.9rem;font-weight:600;white-space:nowrap;
    box-shadow:0 4px 14px rgba(0,0,0,.16);transition:transform .15s ease}
  .tk-cbtn:hover{transform:translateX(3px)}
  .tk-cbtn .tk-dot{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex:0 0 auto;color:#fff}
  .tk-cbtn .tk-dot svg{width:18px;height:18px}
  .tk-wa  .tk-dot{background:#25d366}
  .tk-call .tk-dot{background:#2b8a3e}
  .tk-sms .tk-dot{background:#1c7ed6}
  .tk-email .tk-dot{background:var(--primary,#c9956c)}
  @media (max-width:600px){ .tk-contact{left:14px;bottom:14px} }
</style>

<div class="tk-contact" id="tkContact">
  <div class="tk-contact-menu" id="tkContactMenu" role="menu" aria-label="Contact options">
    <a class="tk-cbtn tk-wa" role="menuitem" target="_blank" rel="noopener"
       href="https://wa.me/<?= $tkPhoneWa ?>?text=<?= $tkWaText ?>">
      <span class="tk-dot"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.82 9.82 0 0 0 1.523 5.27l-.999 3.648 3.965-1.717zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.148-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg></span>
      <span>WhatsApp</span>
    </a>
    <a class="tk-cbtn tk-call" role="menuitem" href="tel:<?= h($tkPhoneIntl) ?>">
      <span class="tk-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
      <span>Call us</span>
    </a>
    <a class="tk-cbtn tk-sms" role="menuitem" href="sms:<?= h($tkPhoneIntl) ?>">
      <span class="tk-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
      <span>Text us</span>
    </a>
    <a class="tk-cbtn tk-email" role="menuitem" href="mailto:<?= h($tkEmail) ?>">
      <span class="tk-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
      <span>Email</span>
    </a>
  </div>
  <button class="tk-contact-fab" id="tkContactFab" aria-label="Contact us" aria-expanded="false" aria-controls="tkContactMenu">
    <svg class="tk-ico-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
    <svg class="tk-ico-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  </button>
</div>
<script>
  (function(){
    var wrap=document.getElementById('tkContact'),fab=document.getElementById('tkContactFab');
    if(!wrap||!fab)return;
    fab.addEventListener('click',function(e){
      e.stopPropagation();
      var open=wrap.classList.toggle('open');
      fab.setAttribute('aria-expanded',open?'true':'false');
    });
    document.addEventListener('click',function(e){
      if(wrap.classList.contains('open')&&!wrap.contains(e.target)){
        wrap.classList.remove('open');fab.setAttribute('aria-expanded','false');
      }
    });
  })();
</script>

<?php if ($tidioKey !== ''): ?>
<!-- Tidio: AI chatbot + live agent + WhatsApp + email inbox -->
<script src="//code.tidio.co/<?= h($tidioKey) ?>.js" async></script>
<?php endif; ?>
