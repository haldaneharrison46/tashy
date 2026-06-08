-- ============================================================
-- Tashy Kollections — MySQL Schema
-- Run this file once to create all tables and seed data.
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Database ─────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS shanshan_beauty
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE shanshan_beauty;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)     NOT NULL,
  email         VARCHAR(150)     NOT NULL UNIQUE,
  password_hash VARCHAR(255)     NOT NULL,
  phone         VARCHAR(30)      DEFAULT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  active        TINYINT(1)       NOT NULL DEFAULT 1,
  admin_pin_hash VARCHAR(255)    DEFAULT NULL,  -- optional quick-login PIN (staff)
  created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Product reviews ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED  NOT NULL,
  user_id     INT UNSIGNED  NOT NULL,
  rating      TINYINT       NOT NULL,
  body        TEXT          NOT NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_prod_user (product_id, user_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Newsletter subscribers ───────────────────────────────────
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(150)     NOT NULL UNIQUE,
  created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PIN login throttle (brute-force protection) ──────────────
CREATE TABLE IF NOT EXISTS pin_attempts (
  ip            VARCHAR(45)      PRIMARY KEY,
  attempts      INT              NOT NULL DEFAULT 0,
  locked_until  DATETIME         DEFAULT NULL,
  updated_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Categories ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100)  NOT NULL,
  slug        VARCHAR(100)  NOT NULL UNIQUE,
  description TEXT          DEFAULT NULL,
  image       VARCHAR(255)  DEFAULT NULL,
  sort_order  INT           NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Products ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
  id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  category_id   INT UNSIGNED    DEFAULT NULL,
  name          VARCHAR(200)    NOT NULL,
  slug          VARCHAR(200)    NOT NULL UNIQUE,
  brand         VARCHAR(100)    DEFAULT NULL,
  description   TEXT            DEFAULT NULL,
  price         DECIMAL(10,2)   NOT NULL,
  compare_price DECIMAL(10,2)   DEFAULT NULL,
  stock         INT             NOT NULL DEFAULT 0,
  sku           VARCHAR(100)    DEFAULT NULL,
  image         VARCHAR(255)    DEFAULT NULL,
  image2        VARCHAR(255)    DEFAULT NULL,
  image3        VARCHAR(255)    DEFAULT NULL,
  tags          VARCHAR(500)    DEFAULT NULL,
  featured      TINYINT(1)      NOT NULL DEFAULT 0,
  active        TINYINT(1)      NOT NULL DEFAULT 1,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_category (category_id),
  INDEX idx_featured (featured),
  INDEX idx_active   (active),
  FULLTEXT INDEX ft_search (name, brand, description, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Cart Items ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cart_items (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  DEFAULT NULL,
  session_id  VARCHAR(128)  DEFAULT NULL,
  product_id  INT UNSIGNED  NOT NULL,
  quantity    INT           NOT NULL DEFAULT 1,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_user    (user_id),
  INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Orders ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
  id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED   DEFAULT NULL,
  order_number VARCHAR(50)    NOT NULL UNIQUE,
  status       ENUM('pending','processing','shipped','delivered','cancelled')
                              NOT NULL DEFAULT 'pending',
  subtotal     DECIMAL(10,2)  NOT NULL,
  shipping     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  tax          DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  total        DECIMAL(10,2)  NOT NULL,
  currency     VARCHAR(10)    NOT NULL DEFAULT 'JMD',
  ship_name    VARCHAR(100)   DEFAULT NULL,
  ship_email   VARCHAR(150)   DEFAULT NULL,
  ship_phone   VARCHAR(30)    DEFAULT NULL,
  ship_address TEXT           DEFAULT NULL,
  ship_city    VARCHAR(100)   DEFAULT NULL,
  ship_parish  VARCHAR(100)   DEFAULT NULL,
  ship_country VARCHAR(100)   NOT NULL DEFAULT 'Jamaica',
  notes        TEXT           DEFAULT NULL,
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user   (user_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Order Items ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
  id         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED   NOT NULL,
  product_id INT UNSIGNED   DEFAULT NULL,
  name       VARCHAR(200)   NOT NULL,
  brand      VARCHAR(100)   DEFAULT NULL,
  price      DECIMAL(10,2)  NOT NULL,
  quantity   INT            NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Wishlist ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wishlist (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED  NOT NULL,
  product_id INT UNSIGNED  NOT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wishlist (user_id, product_id),
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Contact Messages ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100)  DEFAULT NULL,
  email      VARCHAR(150)  DEFAULT NULL,
  subject    VARCHAR(200)  DEFAULT NULL,
  message    TEXT          DEFAULT NULL,
  is_read    TINYINT(1)    NOT NULL DEFAULT 0,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Categories
INSERT IGNORE INTO categories (id, name, slug, description, image, sort_order) VALUES
(1, 'Bedding',        'bedding',      'Sheets, duvets, comforters & pillows for restful nights.',     'bedding-1.jpg', 1),
(2, 'Kitchen & Bath', 'kitchen-bath', 'Towels, robes, and finishing touches for everyday spaces.',    'bath-1.jpg',    2),
(3, 'Mats & Rugs',    'mats-rugs',    'Area rugs, runners, doormats and bath mats for every room.',   'rug-1.jpg',     3),
(4, 'Fragrances',     'fragrances',   'Candles, diffusers & eau de parfum for him, her, and home.',   'candle-1.jpg',  4),
(5, 'Gift Sets',      'gift-sets',    'Beautifully bundled gift sets — ready to give, easy to love.', 'gift-1.jpg',    5);

-- Admin user  (password: Admin1234!)
INSERT IGNORE INTO users (id, name, email, password_hash, role) VALUES
(1, 'Admin', 'admin@tashykollections.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample products (home decor & fragrances)
INSERT IGNORE INTO products
  (id, category_id, name, slug, brand, description, price, compare_price, stock, sku, image, tags, featured) VALUES
-- Bedding
(1, 1, 'Stonewashed Linen Duvet Set', 'stonewashed-linen-duvet-set',
  'Belle Maison', 'Pure stonewashed linen duvet cover with two pillow shams — breathable, soft, and beautifully relaxed. Queen size.',
  14500.00, 18000.00, 24, 'BED-LIN-DUV-Q', 'bedding-1.jpg', 'duvet,linen,bedding,queen,bedroom', 1),
(2, 1, '400TC Egyptian Cotton Sheet Set', '400tc-egyptian-cotton-sheet-set',
  'Belle Maison', 'Crisp 400 thread-count Egyptian cotton sheet set: fitted sheet, flat sheet, and two pillowcases.',
  9800.00, NULL, 40, 'BED-COT-SHT-Q', 'bedding-2.jpg', 'sheets,cotton,bedding,queen', 0),
(3, 1, 'All-Season Down Alternative Comforter', 'all-season-down-alternative-comforter',
  'Cloud9', 'Lightweight yet cosy down-alternative comforter with box-stitch construction for even warmth all year round.',
  11200.00, NULL, 30, 'BED-CMF-AS-Q', 'bedding-1.jpg', 'comforter,duvet insert,bedding', 1),
(4, 1, 'Velvet Decorative Throw Pillows (Set of 2)', 'velvet-decorative-throw-pillows-set-of-2',
  'Tashy Home', 'Plush velvet accent pillows with hidden zip covers and plump inserts — the easiest way to refresh a space.',
  4200.00, 5200.00, 55, 'BED-PIL-VLT-2', 'bedding-2.jpg', 'throw pillows,velvet,cushions,decor', 0),
-- Kitchen & Bath
(5, 2, 'Turkish Cotton Bath Towel Set (4-pc)', 'turkish-cotton-bath-towel-set-4pc',
  'Aegean', 'Quick-drying, ultra-absorbent Turkish cotton — two bath towels and two hand towels in a timeless neutral.',
  7600.00, NULL, 45, 'BAT-TWL-TC-4', 'bath-1.jpg', 'bath towels,turkish cotton,bath', 1),
(6, 2, 'Waffle-Weave Cotton Bathrobe', 'waffle-weave-cotton-bathrobe',
  'Aegean', 'Lightweight waffle-weave robe with patch pockets and a tie belt — spa comfort at home. One size.',
  6800.00, 8200.00, 28, 'BAT-ROB-WF', 'bath-2.jpg', 'bathrobe,waffle,bath,spa', 0),
(7, 2, 'Linen Kitchen Towel Set (3-pc)', 'linen-kitchen-towel-set-3pc',
  'Tashy Home', 'Absorbent pure-linen tea towels with hanging loops — practical and pretty on any rail.',
  3200.00, NULL, 60, 'KIT-TWL-LIN-3', 'bath-1.jpg', 'kitchen towels,linen,tea towel,kitchen', 0),
(8, 2, 'Ceramic Soap Dispenser & Tray', 'ceramic-soap-dispenser-and-tray',
  'Tashy Home', 'Hand-glazed ceramic pump dispenser with a matching tray — a refined finish for kitchen or bath.',
  3800.00, 4600.00, 38, 'BAT-SOP-CER', 'bath-2.jpg', 'soap dispenser,ceramic,bath,kitchen', 0),
-- Mats & Rugs
(9, 3, 'Handwoven Jute Area Rug (5x8 ft)', 'handwoven-jute-area-rug-5x8',
  'Coastal Living', 'Naturally textured handwoven jute rug that grounds a room with warmth. Durable and reversible.',
  22000.00, 27000.00, 14, 'RUG-JUT-58', 'rug-1.jpg', 'rug,jute,area rug,living room,natural', 1),
(10, 3, 'Memory Foam Bath Mat', 'memory-foam-bath-mat',
  'Cloud9', 'Plush memory-foam bath mat with a non-slip backing and quick-dry microfibre top.',
  2800.00, NULL, 70, 'MAT-BTH-MF', 'rug-2.jpg', 'bath mat,memory foam,non-slip,bath', 0),
(11, 3, 'Braided Cotton Doormat', 'braided-cotton-doormat',
  'Tashy Home', 'Chunky braided cotton doormat in a soft neutral — welcoming, washable, and hard-wearing.',
  2400.00, 3000.00, 50, 'MAT-DOR-BRD', 'rug-1.jpg', 'doormat,cotton,braided,entry,mat', 0),
(12, 3, 'Boho Runner Rug (2.5x8 ft)', 'boho-runner-rug-25x8',
  'Coastal Living', 'Low-pile patterned runner that brings character to hallways and kitchens. Stain-resistant weave.',
  9500.00, NULL, 22, 'RUG-RUN-258', 'rug-2.jpg', 'runner,rug,boho,hallway,mats', 0),
-- Fragrances
(13, 4, 'Soy Wax Candle — Vanilla & Amber', 'soy-wax-candle-vanilla-amber',
  'Isle Aroma', 'Hand-poured soy wax candle with warm notes of vanilla, amber, and a hint of sandalwood. 45-hour burn.',
  3500.00, NULL, 65, 'FRG-CDL-VA', 'candle-1.jpg', 'candle,soy wax,vanilla,amber,home fragrance', 1),
(14, 4, 'Reed Diffuser — Sea Salt & Sage', 'reed-diffuser-sea-salt-sage',
  'Isle Aroma', 'Flame-free reed diffuser that fills a room with fresh sea salt and sage for weeks. 200ml.',
  4200.00, 5000.00, 48, 'FRG-DIF-SS', 'candle-2.jpg', 'diffuser,reed,sea salt,sage,home fragrance', 0),
(15, 4, 'Noir Eau de Parfum — for Him', 'noir-eau-de-parfum-for-him',
  'Atelier 37', 'A bold woody-spicy eau de parfum with bergamot, leather, and cedar. Long-lasting. 100ml.',
  16500.00, NULL, 18, 'FRG-EDP-NOIR', 'candle-1.jpg', 'perfume,edp,men,woody,fragrance', 1),
(16, 4, 'Fleur Eau de Parfum — for Her', 'fleur-eau-de-parfum-for-her',
  'Atelier 37', 'An elegant floral eau de parfum with jasmine, peony, and soft musk. Long-lasting. 100ml.',
  16500.00, NULL, 18, 'FRG-EDP-FLEUR', 'candle-2.jpg', 'perfume,edp,women,floral,fragrance', 0),
(17, 4, 'Linen & Room Spray — Fresh Cotton', 'linen-room-spray-fresh-cotton',
  'Isle Aroma', 'A light, clean fresh-cotton mist for linens and living spaces. Alcohol-free formula. 250ml.',
  2600.00, 3200.00, 60, 'FRG-SPR-FC', 'candle-1.jpg', 'room spray,linen spray,fresh cotton,home fragrance', 0),
-- Gift Sets
(18, 5, 'Spa Indulgence Gift Set', 'spa-indulgence-gift-set',
  'Tashy Kollections', 'Waffle robe, Turkish hand towel, soy candle, and bath mat — boxed and ready to gift.',
  8900.00, 11000.00, 20, 'GFT-SPA-01', 'gift-1.jpg', 'gift set,spa,bath,gift', 1),
(19, 5, 'Cozy Night Bedding Bundle', 'cozy-night-bedding-bundle',
  'Tashy Kollections', 'Linen duvet set, comforter, and a pair of velvet throw pillows — everything for a dreamy bed.',
  19500.00, 24000.00, 15, 'GFT-BED-01', 'gift-2.jpg', 'gift set,bedding,bundle,gift', 0),
(20, 5, 'Home Fragrance Trio Gift Box', 'home-fragrance-trio-gift-box',
  'Isle Aroma', 'A candle, reed diffuser, and room spray in coordinating scents — a beautifully boxed trio.',
  9800.00, NULL, 26, 'GFT-FRG-01', 'gift-1.jpg', 'gift set,fragrance,trio,gift', 1),
(21, 5, 'Kitchen Essentials Starter Set', 'kitchen-essentials-starter-set',
  'Tashy Home', 'Linen tea towels, a ceramic soap dispenser, and a braided mat to set up any kitchen in style.',
  7200.00, NULL, 24, 'GFT-KIT-01', 'gift-2.jpg', 'gift set,kitchen,starter,gift', 0);
