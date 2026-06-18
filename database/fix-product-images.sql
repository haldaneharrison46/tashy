-- ============================================================
-- Tashy Kollections — fix mismatched product images (LIVE-SAFE)
-- Re-points the seeded home-decor products at better-matching
-- images. Matches by SKU so it ONLY touches the seeded catalog.
--
-- Safe on a live DB: UPDATE only — no DELETE, no schema change,
-- order history and any products you added yourself are untouched.
-- Idempotent: re-running it changes nothing further.
--
-- Apply in phpMyAdmin: select the store database (dbs15760212 for
-- .com/.online), then paste & run. (The DB host isn't reachable
-- from a dev machine, so this is a phpMyAdmin / server-side step.)
-- ============================================================

-- Perfumes were showing a candle photo — point them at real perfume
-- bottle photos (added to assets/images): a dark masculine bottle for
-- the "for Him" scent and an elegant feminine bottle for "for Her".
UPDATE products SET image = 'perfume-noir.jpg'  WHERE sku = 'FRG-EDP-NOIR';
UPDATE products SET image = 'perfume-fleur.jpg' WHERE sku = 'FRG-EDP-FLEUR';

-- Rugs/mats: the boho *runner* read better as the patterned room rug,
-- and the *doormat* as the floor mat — swap them.
UPDATE products SET image = 'rug-1.jpg' WHERE sku = 'RUG-RUN-258';
UPDATE products SET image = 'rug-2.jpg' WHERE sku = 'MAT-DOR-BRD';
