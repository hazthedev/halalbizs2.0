# Project Plan — Vietnamese Catalogue and CMS Content

**Goal:** make Vietnamese (`vi`) a first-class content locale for products, categories, banners, and shopper-facing CMS content, while retaining English as the required fallback.

**Prepared:** 2026-08-17
**Execution mode:** dependency-ordered solo implementation in the shared tree.
**Existing work to preserve:** `lang/ms.json` contains the completed Malay shopper translation; `docs/audit/HALALBIZS-AUDIT-2026-08-11.md` is unrelated and must remain untouched.

## Contract and decisions

- No project contract file exists. `halalbizs2.0-decisions.md` has no decision that conflicts with this feature.
- English remains required and is the fallback. Malay and Vietnamese are optional per record; an empty `vi` value must fall back to English exactly as an empty `ms` value does.
- Existing Spatie-translatable JSON columns already accept `vi`; no SQL schema change is needed for products, categories, banners, pages, help articles, home sections, attributes, or attribute values.
- Theme announcement copy is stored as typed settings, so it requires a new `theme.announcement_text_vi` settings migration.
- Vietnamese content must be natural Vietnamese, not copied English/Malay or machine-looking literal prose. HTML structure, legal meaning, amounts, dates, product facts, certification claims, and URLs must remain unchanged.
- Banner desktop headlines are baked into WebP artwork. Vietnamese banners therefore include a locale-specific `image_vi` media collection and five Vietnamese artwork files. Storefront selection is `image_vi` for `vi`, then the existing image as fallback. Existing English/Malay artwork is not altered.
- Seed/backfill behavior must be idempotent and must never erase an administrator's existing non-empty Vietnamese translation.

## Dependency graph

```text
T1 locale contract + regression harness
 ├── T2 product/category/attribute writers + search/AI
 ├── T3 banner/CMS writers + Vietnamese banner media
 └── T4 complete Vietnamese seed content
          └──────────────┬──────────────┘
                         T5 safe existing-data backfill + deploy wiring
                                      │
                         T6 full verification + visual walkthrough
```

## Wave 1 — foundation

- [x] **T1 — Content-locale contract and regression harness**
  - Centralize the editable content locales as `en`, `ms`, `vi` using the existing locale configuration rather than scattering new two-item arrays.
  - Add reusable tests proving: English required; Vietnamese optional; Vietnamese persists; blank Vietnamese is removed; shopper reads fall back to English.
  - Keep UI chrome translation (`lang/vi.json`) separate from content translations.
  - **Accept:** tests fail against the current two-locale writers and pass once the three-locale contract is applied; no existing English/Malay behavior changes.

## Wave 2 — writers and runtime readers

- [x] **T2 — Products, categories, and attributes** *(depends on T1)*
  - Add Vietnamese name/description fields to the seller product form and admin category form.
  - Add Vietnamese name/value fields to admin attributes and attribute values.
  - Extend product bulk import with optional `name_vi` and `description_vi` columns without rejecting old EN/MS CSV files.
  - Extend AI listing-copy output and its deterministic fallback to return `en`, `ms`, and `vi`.
  - Add `name_vi` and `description_vi` to Scout/Meilisearch searchable data and Vietnamese text to embedding input.
  - Let the shopping concierge accept `vi`, describe products in the shopper's locale with English fallback, and return Vietnamese deterministic replies.
  - **Accept:** create/edit/import/generate flows persist Vietnamese; Vietnamese product/category searches work; old imports remain compatible; seller/admin tests cover save, clear, fallback, sanitization, and search payloads.

- [x] **T3 — Banners and CMS editors** *(depends on T1)*
  - Add Vietnamese fields to banner title, supporting line, and CTA; CMS page title/body; help-article title/body; home-section heading; and theme announcement text.
  - Add the settings migration/property/default for `announcement_text_vi` and preserve English fallback.
  - Add an optional Vietnamese banner image upload/removal path and a locale-aware model helper used by storefront image and video-poster rendering.
  - Update all corresponding admin tabs, labels, validation, reset/edit/save behavior, and accessibility text.
  - **Accept:** every editor round-trips `vi`, clearing it restores English fallback, HTML is sanitized identically in every locale, and Vietnamese desktop/mobile banner copy and alt text agree.

- [x] **T4 — Complete Vietnamese seed content** *(depends on T1; may proceed alongside T2/T3)*
  - Translate all **166 products** (`name_vi` plus Vietnamese generated description based only on existing facts).
  - Translate all **33 categories**.
  - Translate all **5 banners** (headline, supporting line, CTA) and extend `scripts/bake-banner-copy.py` to render five Vietnamese WebP files from the committed source artwork with the same safe-zone/autofit checks.
  - Translate all **6 CMS pages**, including the legal/policy text without changing meaning, values, or HTML structure.
  - Translate all **10 help articles** and **5 titled home sections**.
  - Add a Vietnamese baseline announcement value only where seeded/default occasion copy exists; do not invent an active campaign.
  - **Accept:** a deterministic audit reports 166/166 products, 33/33 categories, 5/5 banners, 6/6 pages, 10/10 help articles, and 5/5 titled home sections with non-empty `vi`; no `vi` value is identical to a non-brand English source except legitimate names/acronyms.

## Wave 3 — existing installations and deployment

- [x] **T5 — Non-destructive backfill and deploy wiring** *(depends on T2, T3, T4)*
  - Make normal seeders include Vietnamese for clean installs.
  - Add one idempotent Vietnamese-content backfill seeder for existing databases. It fills only missing/blank `vi` translations and never overwrites an existing administrator-authored Vietnamese value.
  - Wire the backfill, help/home seeders, theme setting migration, catalogue seeder, and artwork seeder into the deployment path in dependency order.
  - Ensure Scout reindexing/search configuration is documented or invoked only after the new content exists.
  - **Accept:** two consecutive backfill runs produce the same database state; a pre-existing custom Vietnamese field survives; a database containing only EN/MS gains complete seeded Vietnamese content.

## Wave 4 — verification

- [x] **T6 — Automated and visual verification** *(depends on T5)*
  - Run focused feature tests for products, bulk import/AI copy, catalogue admin, banners/content/theme, help centre, CMS pages, search, and concierge.
  - Run `php artisan test -c phpunit.mariadb.xml`, Pint on changed PHP files, JSON parsing, `git diff --check`, and a three-dot diff/status review.
  - Seed a clean test database and run the deterministic Vietnamese completeness audit.
  - Walk the Vietnamese storefront at desktop and mobile widths: home banners, category surfaces, listing/search, product detail, help centre, and all CMS pages. Verify English fallback with one intentionally blank `vi` test record.
  - Confirm only intended files changed and the existing `lang/ms.json` work remains intact.
  - **Accept:** all tests/checks pass, no English-baked banner is shown to a Vietnamese desktop shopper, no raw translation key is visible, and no content surface silently falls back because seeded Vietnamese data is missing.

## Completion boundary

This task is complete only when both halves ship together:

1. future content can be authored/imported in Vietnamese; and
2. the existing seeded shopper catalogue and CMS corpus already contain Vietnamese.

UI-only Vietnamese with English catalogue fallback does not satisfy this plan.

## Verification result — 2026-08-17

- MariaDB full suite: **1,251 passed, 4,712 assertions**.
- Focused Vietnamese/catalogue/CMS suite: **124 passed, 724 assertions**.
- Clean SQLite install plus a second `VietnameseContentSeeder` run succeeded.
- Completeness audit: **166/166 products, 33 categories, 5 banners, 6 pages, 10 help articles, 5 home headings; 0 missing**.
- Five Vietnamese banner WebPs generated at **1600×533**, visually checked for diacritics, collision, and safe-zone fit.
- Changed PHP files pass Pint; Blade view compilation, JSON parsing, and `git diff --check` pass.
