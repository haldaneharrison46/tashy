# Project Continuation — Tashy multi-store e-commerce

_Snapshot: 2026-06-18 · branch `main` @ `a1709dd`_

## What this is

A single shared PHP codebase that powers **five storefronts** off one IONOS
webspace. Each store lives in its own folder and has its **own**
`config/db.php` (per-store DB + branding constants); everything else (page
templates, `includes/`, `admin/`) is identical code mirrored to every folder.
Store-specific behavior is driven by DB **settings** (e.g. `store_kind`,
`currencies_enabled`, hero fields) rather than code forks.

- Stack: plain PHP 8.2 + PDO/MySQL, no framework, server-rendered pages.
- Root pages: `index.php`, `shop.php`, `product.php`, `cart.php`,
  `checkout.php`, `account.php`, `contact.php`, `blog*.php`, `policy.php`,
  `wholesale.php`, `track.php`, etc.
- `admin/` — full back office: products, inventory, orders, kanban, POS +
  POS report, returns, vendors, shipping, marketing, subscribers, customers,
  blog, users, settings.
- `includes/` — `functions.php` (core helpers incl. `tk_run_migrations()`,
  `set_setting()`, `slugify()`, `currency_base()`, `tk_logo()`), `auth.php`,
  `cart.php`, `orders.php`, `mail.php`, `marketing.php`, `header.php`,
  `footer.php`, `contact-widget.php`.

## The five stores

| Folder        | Store              | Kind   | Currencies | DB            | Status |
|---------------|--------------------|--------|------------|---------------|--------|
| `/` (root)    | Tashy (.com auto)  | home   | JMD        | dbs15760212   | live   |
| `/tashy`      | Tashy Kollections  | home   | JMD        | dbs15760212   | live   |
| `/shan`       | Shan               | —      | —          | —             | live   |
| `/luxeline`   | Luxe Line          | luxe   | JMD+USD    | —             | live   |
| `/alinuluxe`  | Alinuluxe Line     | luxe   | JMD+USD    | dbs15793201   | live — products pending |

> See memory `alinuluxe-store.md`: 5th store provisioned 2026-06-16,
> admin `admin@alinuluxe.com`. Only product entry remains.

`config/db.php` is **per-store and not deployed** (excluded along with
`.htaccess`). The committed `config/db.php` holds Tashy's creds + the shared
constant shape (`SITE_NAME/SITE_URL/CURRENCY/TAX_RATE/...`).

## Deploy (see memory `deploy-topology.md`)

- **.com auto-deploys on push** (GitHub → host).
- Other folders deploy via verified `git archive HEAD | docker lftp mirror`
  as user **a1229881** to `/`, `/tashy`, `/shan`, `/luxeline`, `/alinuluxe`.
  Excludes `config/` and `.htaccess`; runs **without** `--delete`.
- Scripts in repo: `deploy-tashy.ps1`, `deploy-ionos.ps1`.
- ⚠️ `deploy.log` tail (2026-06-17) shows **SSH publickey refused → fell back
  to password** for a1229881. SFTP/SSH key auth was not working; the lftp
  (password) path is the one that works. Re-check key auth before relying on it.

## DB migrations (see memory `db-migrations.md`)

No migration runner. Live schema changes go through `tk_run_migrations()` +
admin **Settings → "Apply database updates"**. Write new code **defensively**
(assume a column/table may not exist yet on a given store's DB).

## Uncommitted / in-flight (working tree)

Two **one-off server-side tools**, untracked, not yet run/cleaned up:

- **`_setup_alinuluxe.php`** — idempotent provisioner for the `/alinuluxe`
  store (bootstraps schema DDL, upserts settings, seeds luxe categories,
  creates admin if missing). Run server-side then **delete**. Access guard
  key `alinu-setup-9931`. Per the store table above, Alinuluxe is already
  live, so this has likely been run — confirm, then `rm`.
- **`_fix_seo.php`** — guards robots/sitemap rewrite rules in each store's
  `.htaccess` and generates static `robots.txt`/`sitemap.xml` per folder
  (idempotent, EOL-aware). Run server-side then delete.

Both are throwaway maintenance scripts — not app code. Decide: run-and-delete,
or commit if we want them as reusable tooling.

## Recent work (last ~15 commits)

Per-store hero images (beauty/luxe, `store_kind`-aware fallback); warm luxury
home hero + stronger overlay; broad **mobile polish** (centered pages, contact
form, shop/category product grid, filter chips, search button); per-store
**currency switcher** (`currencies_enabled`); static robots/sitemap serving;
configurable hero + about image + welcome/exit messages; base-currency support;
US-states checkout; `luxe` store kind; config-driven store address/phone;
language globe (Google Translate); admin PIN setter.

Theme of the recent stretch: **multi-store configurability + mobile UX + SEO**.

## Known issues / watch list

- IONOS SSL won't provision → **moving DNS+SSL to Cloudflare** (IONOS stays
  origin host). See memory `cloudflare-plan.md`.
- "Formatting off in all areas" was a `style.css` 404 from an absolute
  `SITE_URL`; fixed by linking the stylesheet **document-relative**. See
  memory `formatting-css-loading.md`. Watch for regressions when adding
  asset links — keep them relative.
- SSH key auth to the host is currently refused (see Deploy note).

## Likely next steps

1. Finish **Alinuluxe**: add products in admin; then run+delete the two
   `_*.php` setup scripts on the server.
2. Continue the **Cloudflare DNS/SSL** migration.
3. Keep new pages/queries **migration-defensive** and asset links
   **document-relative**.
