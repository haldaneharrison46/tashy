<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'FAQ — ' . SITE_NAME;
$metaDesc  = 'Frequently asked questions about delivery, payment, pickup, returns and more at ' . SITE_NAME . '.';
$faqs      = get_faq_items();
require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="container" style="max-width:820px">
    <div class="text-center" style="margin-bottom:2.5rem">
      <h1 class="section-title">Frequently Asked Questions</h1>
      <p class="section-sub"><?= sk('Delivery, payment, pickup &amp; returns — the things shoppers ask us most.',
                                     'Delivery, payment, pickup &amp; returns — the things our customers ask us most.',
                                     'Delivery, payment, pickup &amp; returns — the things shoppers ask us most.') ?></p>
    </div>

    <div class="faq-list">
      <?php foreach ($faqs as $i => $f): ?>
      <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
        <summary class="faq-q"><?= h($f['q']) ?></summary>
        <div class="faq-a"><?= nl2br(h($f['a'])) ?></div>
      </details>
      <?php endforeach; ?>
    </div>

    <div class="text-center" style="margin-top:2.5rem">
      <p style="color:#777;margin-bottom:14px">Still have a question?</p>
      <a href="<?= asset_base() ?>/contact.php" class="btn btn-primary">Contact Us</a>
    </div>
  </div>
</section>

<style>
.faq-list{display:flex;flex-direction:column;gap:12px}
.faq-item{border:1px solid var(--grey-light,#e6e1da);border-radius:12px;background:#fff;overflow:hidden}
.faq-q{list-style:none;cursor:pointer;padding:16px 20px;font-weight:600;font-size:1.02rem;color:#222;display:flex;justify-content:space-between;align-items:center;gap:12px}
.faq-q::-webkit-details-marker{display:none}
.faq-q::after{content:'+';font-size:1.4rem;font-weight:400;color:var(--rose-gold,#c9956c);line-height:1;flex:none}
.faq-item[open] .faq-q::after{content:'\2212'}
.faq-a{padding:0 20px 18px;color:#555;line-height:1.65}
</style>

<!-- FAQPage structured data for rich results -->
<script type="application/ld+json">
<?= json_encode([
  '@context'   => 'https://schema.org',
  '@type'      => 'FAQPage',
  'mainEntity' => array_map(fn($f) => [
    '@type'          => 'Question',
    'name'           => $f['q'],
    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
  ], $faqs),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
