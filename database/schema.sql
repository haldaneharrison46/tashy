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
  created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
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
INSERT IGNORE INTO categories (id, name, slug, description, sort_order) VALUES
(1, 'Skincare',   'skincare',   'Cleansers, moisturisers, serums, and more for every skin type.',  1),
(2, 'Hair Care',  'hair',       'Shampoos, conditioners, treatments, and styling products.',         2),
(3, 'Fragrance',  'fragrance',  'Perfumes, body mists, and scented lotions.',                        3),
(4, 'Makeup',     'makeup',     'Foundation, mascara, lip colour, and accessories.',                 4);

-- Admin user  (password: Admin1234!)
INSERT IGNORE INTO users (id, name, email, password_hash, role) VALUES
(1, 'Admin', 'admin@tashykollection.org',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample products
INSERT IGNORE INTO products
  (id, category_id, name, slug, brand, description, price, compare_price, stock, featured, tags) VALUES
(1,  1, 'Vitamin C Brightening Serum',     'vitamin-c-brightening-serum',
  'Neutrogena', 'A potent 20% Vitamin C serum that fades dark spots and evens skin tone for melanin-rich skin.',
  3200.00, 4500.00, 50, 1, 'serum,brightening,vitamin c,skincare'),
(2,  1, 'Shea Butter Deep Moisture Cream', 'shea-butter-deep-moisture-cream',
  'SheaMoisture', 'Ultra-rich whipped shea butter cream that hydrates and seals moisture for 24 hours.',
  2800.00, NULL,    40, 1, 'moisturiser,shea,dry skin,skincare'),
(3,  1, 'Exfoliating Brown Sugar Scrub',   'exfoliating-brown-sugar-scrub',
  'Tree Hut', 'Fine brown sugar granules gently buff away dead skin, leaving a luminous glow.',
  1900.00, 2500.00, 30, 0, 'scrub,exfoliant,glow,skincare'),
(4,  2, 'Moisture Retention Shampoo',      'moisture-retention-shampoo',
  'Pantene Gold Series', 'Sulfate-free formula that cleanses while locking in vital moisture.',
  2200.00, NULL,    60, 1, 'shampoo,moisture,natural hair,hair care'),
(5,  2, 'Deep Conditioner Treatment Mask', 'deep-conditioner-treatment-mask',
  'Cantu', 'Intensive deep-conditioning mask that restores softness, shine, and manageability.',
  1800.00, 2300.00, 45, 0, 'conditioner,deep treatment,curly hair,hair care'),
(6,  2, 'Jamaican Black Castor Oil',       'jamaican-black-castor-oil',
  'Tropic Isle Living', 'Authentic Jamaican Black Castor Oil for scalp health and edge growth.',
  3500.00, NULL,    35, 1, 'castor oil,growth,scalp,jamaican,hair care'),
(7,  3, 'Black Opium Eau de Parfum',       'black-opium-eau-de-parfum',
  'YSL', 'Addictive and sensual — a coffee and vanilla fragrance for bold evenings.',
  18500.00, NULL,   15, 1, 'perfume,edp,vanilla,coffee,fragrance'),
(8,  3, 'Glow Body Mist — Coconut Lime',   'glow-body-mist-coconut-lime',
  'Sol de Janeiro', 'A refreshing tropical mist that leaves skin glowing and lightly scented.',
  4200.00, 5000.00, 25, 0, 'body mist,tropical,coconut,fragrance'),
(9,  4, 'Full Coverage Foundation SPF 25', 'full-coverage-foundation-spf25',
  'Black Opal', 'Buildable full-coverage foundation with SPF 25, blends invisibly on deeper skin tones.',
  5800.00, NULL,    20, 1, 'foundation,coverage,spf,deep tones,makeup'),
(10, 4, 'Volumising Mascara — Blackest Black','volumising-mascara-blackest-black',
  'Maybelline', 'Dramatic volume and length for every lash type — smudge-proof formula.',
  1600.00, 2100.00, 55, 0, 'mascara,volume,lashes,makeup');
