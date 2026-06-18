-- ============================================================
-- Tashy Kollections — Home Decor catalog (20 products, 5 categories)
-- Replaces the catalog with a curated home-decor set where every
-- product has its own matching photo (no repeated/placeholder images).
--
-- Run ONCE against the live database (phpMyAdmin — select the DB first;
-- no USE statement so it works on any DB name).
--
-- Safe on a live DB:
--   * order_items keep their name/brand/price snapshot (FK SET NULL),
--     so existing order history is preserved.
--   * cart_items and wishlist rows are cleared (they reference products).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM cart_items;
DELETE FROM wishlist;
DELETE FROM products;
DELETE FROM categories;
ALTER TABLE products   AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Categories ───────────────────────────────────────────────
INSERT INTO categories (id, name, slug, description, image, sort_order) VALUES
(1, 'Bedding',        'bedding',      'Sheets, duvets, comforters & pillows for restful nights.',     'bedding-1.jpg', 1),
(2, 'Kitchen & Bath', 'kitchen-bath', 'Towels, robes, and finishing touches for everyday spaces.',    'bath-1.jpg',    2),
(3, 'Mats & Rugs',    'mats-rugs',    'Area rugs, runners, doormats and bath mats for every room.',   'rug-1.jpg',     3),
(4, 'Fragrances',     'fragrances',   'Candles, diffusers & eau de parfum for him, her, and home.',   'candle-1.jpg',  4),
(5, 'Gift Sets',      'gift-sets',    'Beautifully bundled gift sets — ready to give, easy to love.', 'giftset-spa.jpg', 5);

-- ── Products ─────────────────────────────────────────────────
-- columns: id, category_id, name, slug, brand, description, price, compare_price, stock, sku, image, tags, featured
INSERT INTO products
  (id, category_id, name, slug, brand, description, price, compare_price, stock, sku, image, tags, featured) VALUES
-- Bedding
(1, 1, 'Stonewashed Linen Duvet Set', 'stonewashed-linen-duvet-set',
  'Belle Maison', 'Pure stonewashed linen duvet cover with two pillow shams — breathable, soft, and beautifully relaxed. Queen size.',
  14500.00, 18000.00, 24, 'BED-LIN-DUV-Q', 'bedding-1.jpg', 'duvet,linen,bedding,queen,bedroom', 1),
(2, 1, '400TC Egyptian Cotton Sheet Set', '400tc-egyptian-cotton-sheet-set',
  'Belle Maison', 'Crisp 400 thread-count Egyptian cotton sheet set: fitted sheet, flat sheet, and two pillowcases. Hotel-quality white.',
  9800.00, NULL, 40, 'BED-COT-SHT-Q', 'sheets-cotton.jpg', 'sheets,cotton,bedding,queen,white', 0),
(3, 1, 'Cloud Cotton Comforter', 'cloud-cotton-comforter',
  'Cloud9', 'Lightweight, breathable cotton comforter with a soft textured weave and box-stitch fill — cosy all year round.',
  11200.00, NULL, 30, 'BED-CMF-AS-Q', 'comforter-cotton.jpg', 'comforter,duvet insert,bedding,cotton', 1),
(4, 1, 'Velvet Decorative Throw Pillows (Set of 2)', 'velvet-decorative-throw-pillows-set-of-2',
  'Tashy Home', 'Plush velvet accent pillows with hidden zip covers and plump inserts — the easiest way to refresh a space.',
  4200.00, 5200.00, 55, 'BED-PIL-VLT-2', 'bedding-2.jpg', 'throw pillows,velvet,cushions,decor', 0),

-- Kitchen & Bath
(5, 2, 'Turkish Cotton Bath Towel Set (4-pc)', 'turkish-cotton-bath-towel-set-4pc',
  'Aegean', 'Quick-drying, ultra-absorbent Turkish cotton — two bath towels and two hand towels in a timeless palette.',
  7600.00, NULL, 45, 'BAT-TWL-TC-4', 'bath-1.jpg', 'bath towels,turkish cotton,bath', 1),
(6, 2, 'Waffle-Weave Cotton Bathrobe', 'waffle-weave-cotton-bathrobe',
  'Aegean', 'Lightweight waffle-weave robe with a hood, patch pockets and a tie belt — spa comfort at home. One size.',
  6800.00, 8200.00, 28, 'BAT-ROB-WF', 'bathrobe-waffle.jpg', 'bathrobe,waffle,bath,spa,robe', 0),
(7, 2, 'Ceramic & Brass Soap Dispenser', 'ceramic-brass-soap-dispenser',
  'Tashy Home', 'Matte ceramic pump dispenser with a brushed brass top — a refined finish for any kitchen or bath.',
  3800.00, 4600.00, 38, 'BAT-SOP-CER', 'soap-dispenser.jpg', 'soap dispenser,ceramic,brass,bath,kitchen', 0),
(8, 2, 'Linen Kitchen Towel Set (3-pc)', 'linen-kitchen-towel-set-3pc',
  'Tashy Home', 'Absorbent pure-linen tea towels with hanging loops — practical and pretty on any rail.',
  3200.00, NULL, 60, 'KIT-TWL-LIN-3', 'bath-2.jpg', 'kitchen towels,linen,tea towel,kitchen', 0),

-- Mats & Rugs
(9, 3, 'Handwoven Jute Area Rug (5x8 ft)', 'handwoven-jute-area-rug-5x8',
  'Coastal Living', 'Naturally textured handwoven jute rug that grounds a room with warmth. Durable and reversible.',
  22000.00, 27000.00, 14, 'RUG-JUT-58', 'rug-1.jpg', 'rug,jute,area rug,living room,natural', 1),
(10, 3, 'Memory Foam Bath Mat', 'memory-foam-bath-mat',
  'Cloud9', 'Plush memory-foam bath mat with a non-slip backing and quick-dry microfibre top.',
  2800.00, NULL, 70, 'MAT-BTH-MF', 'rug-2.jpg', 'bath mat,memory foam,non-slip,bath', 0),
(11, 3, 'Boho Kilim Runner Rug (2.5x8 ft)', 'boho-kilim-runner-rug-25x8',
  'Coastal Living', 'Patterned flat-weave kilim runner that brings character to hallways and kitchens. Stain-resistant.',
  9500.00, NULL, 22, 'RUG-RUN-258', 'rug-boho-runner.jpg', 'runner,rug,boho,kilim,hallway,mats', 0),
(12, 3, 'Round Braided Jute Doormat', 'round-braided-jute-doormat',
  'Tashy Home', 'Chunky round braided jute mat in a warm natural tone — welcoming, hard-wearing and easy to shake clean.',
  2400.00, 3000.00, 50, 'MAT-DOR-BRD', 'doormat-jute.jpg', 'doormat,jute,braided,round,entry,mat', 0),

-- Fragrances
(13, 4, 'Soy Wax Candle — Vanilla & Amber', 'soy-wax-candle-vanilla-amber',
  'Isle Aroma', 'Hand-poured soy wax candle with warm notes of vanilla, amber, and a hint of sandalwood. 45-hour burn.',
  3500.00, NULL, 65, 'FRG-CDL-VA', 'candle-1.jpg', 'candle,soy wax,vanilla,amber,home fragrance', 1),
(14, 4, 'Reed Diffuser — Sea Salt & Sage', 'reed-diffuser-sea-salt-sage',
  'Isle Aroma', 'Flame-free reed diffuser that fills a room with fresh sea salt and sage for weeks. 200ml amber glass.',
  4200.00, 5000.00, 48, 'FRG-DIF-SS', 'reed-diffuser.jpg', 'diffuser,reed,sea salt,sage,home fragrance', 0),
(15, 4, 'Noir Eau de Parfum — for Him', 'noir-eau-de-parfum-for-him',
  'Atelier 37', 'A bold woody-spicy eau de parfum with bergamot, leather, and cedar. Long-lasting. 100ml.',
  16500.00, NULL, 18, 'FRG-EDP-NOIR', 'perfume-noir.jpg', 'perfume,edp,men,woody,fragrance', 1),
(16, 4, 'Fleur Eau de Parfum — for Her', 'fleur-eau-de-parfum-for-her',
  'Atelier 37', 'An elegant floral eau de parfum with jasmine, peony, and soft musk. Long-lasting. 100ml.',
  16500.00, NULL, 18, 'FRG-EDP-FLEUR', 'perfume-fleur.jpg', 'perfume,edp,women,floral,fragrance', 0),

-- Gift Sets
(17, 5, 'Spa Indulgence Gift Set', 'spa-indulgence-gift-set',
  'Tashy Kollections', 'Plush towels, a soy candle, and a soft floral touch — boxed and ready to gift for a little everyday luxury.',
  8900.00, 11000.00, 20, 'GFT-SPA-01', 'giftset-spa.jpg', 'gift set,spa,bath,candle,gift', 1),
(18, 5, 'Cozy Night Blanket Bundle', 'cozy-night-blanket-bundle',
  'Tashy Kollections', 'A trio of soft knitted throws in warm neutral tones — everything for cosy evenings, beautifully bundled.',
  19500.00, 24000.00, 15, 'GFT-BED-01', 'blanket-stack.jpg', 'gift set,blanket,throw,bundle,gift', 0),
(19, 5, 'Home Fragrance Trio Gift Box', 'home-fragrance-trio-gift-box',
  'Isle Aroma', 'A candle, reed diffuser, and room spray in coordinating scents — a beautifully boxed trio.',
  9800.00, NULL, 26, 'GFT-FRG-01', 'gift-1.jpg', 'gift set,fragrance,trio,candle,gift', 1),
(20, 5, 'Kitchen Essentials Starter Set', 'kitchen-essentials-starter-set',
  'Tashy Home', 'Linen tea towels and coordinating kitchen textiles to set up any kitchen in style — a thoughtful housewarming gift.',
  7200.00, NULL, 24, 'GFT-KIT-01', 'kitchen-towels.jpg', 'gift set,kitchen,starter,linen,gift', 0);
