-- ============================================================
-- Tashy Kollections — phpMyAdmin migration
-- Mirrors tk_run_migrations(). Idempotent: safe to run repeatedly.
-- Select your database in phpMyAdmin first, then paste & run this whole file.
-- (No CREATE DATABASE / USE — it runs against the selected DB.)
-- ============================================================

-- ── New columns (guarded so re-running is safe) ─────────────

-- products.taxable
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'taxable') = 0,
  'ALTER TABLE products ADD COLUMN taxable TINYINT(1) NOT NULL DEFAULT 1 AFTER active',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- products.vendor_id
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'vendor_id') = 0,
  'ALTER TABLE products ADD COLUMN vendor_id INT UNSIGNED DEFAULT NULL AFTER category_id',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- users.tax_exempt
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'tax_exempt') = 0,
  'ALTER TABLE users ADD COLUMN tax_exempt TINYINT(1) NOT NULL DEFAULT 0 AFTER active',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- orders.ship_date
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'ship_date') = 0,
  'ALTER TABLE orders ADD COLUMN ship_date DATE DEFAULT NULL AFTER ship_country',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- orders.tracking_number
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'tracking_number') = 0,
  'ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100) DEFAULT NULL AFTER ship_date',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- orders.carrier
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'carrier') = 0,
  'ALTER TABLE orders ADD COLUMN carrier VARCHAR(80) DEFAULT NULL AFTER tracking_number',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ── New tables ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS blog_posts (
  id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200)  NOT NULL,
  slug         VARCHAR(200)  NOT NULL UNIQUE,
  excerpt      VARCHAR(500)  DEFAULT NULL,
  body         MEDIUMTEXT    NOT NULL,
  cover_image  VARCHAR(255)  DEFAULT NULL,
  tags         VARCHAR(500)  DEFAULT NULL,
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  author_id    INT UNSIGNED  DEFAULT NULL,
  published_at DATETIME      DEFAULT NULL,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendors (
  id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150)  NOT NULL,
  contact_name VARCHAR(120)  DEFAULT NULL,
  email        VARCHAR(150)  DEFAULT NULL,
  phone        VARCHAR(40)   DEFAULT NULL,
  address      VARCHAR(255)  DEFAULT NULL,
  notes        TEXT          DEFAULT NULL,
  active       TINYINT(1)    NOT NULL DEFAULT 1,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_receipts (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  vendor_id   INT UNSIGNED  DEFAULT NULL,
  reference   VARCHAR(80)   DEFAULT NULL,
  note        VARCHAR(255)  DEFAULT NULL,
  total_cost  DECIMAL(10,2) NOT NULL DEFAULT 0,
  received_by VARCHAR(100)  DEFAULT NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_receipt_items (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  receipt_id  INT UNSIGNED  NOT NULL,
  product_id  INT UNSIGNED  DEFAULT NULL,
  name        VARCHAR(200)  NOT NULL,
  quantity    INT           NOT NULL DEFAULT 0,
  unit_cost   DECIMAL(10,2) NOT NULL DEFAULT 0,
  INDEX idx_receipt (receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS preset_messages (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  scope      VARCHAR(20)   NOT NULL DEFAULT 'pos',
  title      VARCHAR(120)  NOT NULL,
  body       TEXT          NOT NULL,
  sort_order INT           NOT NULL DEFAULT 0,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_scope (scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Settings seed (INSERT IGNORE keeps any values you've edited) ──

INSERT IGNORE INTO settings (skey, sval) VALUES
  ('tax_enabled',           '1'),
  ('tax_rate',              '15'),
  ('tax_label',             'GCT'),
  ('tax_country',           'Jamaica'),
  ('store_phone',           '+1-876-487-0686'),
  ('store_hours',           'Mon–Sat 9am–6pm'),
  ('store_slogan',          'Bedding, home essentials & fragrances — proudly Jamaican.'),
  ('receipt_header',        ''),
  ('receipt_footer',        'Thank you for shopping with us!'),
  ('receipt_width',         '80'),
  ('print_copies',          '1'),
  ('auto_print',            '0'),
  ('pay_cod_enabled',       '1'),
  ('pay_transfer_enabled',  '1'),
  ('pay_card_enabled',      '0'),
  ('pay_paypal_enabled',    '0'),
  ('bank_transfer_details', ''),
  ('card_instructions',     ''),
  ('paypal_me',             ''),
  ('paypal_email',          ''),
  ('mail_from_name',        'Tashy Kollections'),
  ('mail_from_email',       'hello@tashykollections.com'),
  ('mail_reply_to',         'hello@tashykollections.com'),
  ('mail_admin_recipient',  'hello@tashykollections.com'),
  ('mail_method',           'mail'),
  ('smtp_host',             ''),
  ('smtp_port',             '587'),
  ('smtp_user',             ''),
  ('smtp_pass',             ''),
  ('smtp_secure',           'tls'),
  ('announcement_enabled',  '0'),
  ('announcement_text',     ''),
  ('announcement_link',     ''),
  ('announcement_speed',    '18');

-- Done. Re-running this file makes no further changes.
