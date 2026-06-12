# 00 — Project Overview & Build Order

## What this is

A custom-built, Shopee-style multi-vendor marketplace for Malaysia. Independent sellers run storefronts under one platform; the platform earns commission on completed orders. Four actors: **Guest** (browse + session cart), **Buyer** (checkout, orders, reviews), **Seller** (approved shop, products, fulfilment, earnings), **Admin** (governance, finance, localization).

Multilanguage (EN default/fallback, BM at launch, ZH later) · multicurrency display (MYR / USD / SGD / IDR — settlement always MYR) · payments via **COD** and **iPay88** (FPX, cards, TNG/GrabPay/Boost/ShopeePay).

## Locked decisions (never relitigate without updating this doc)

| Decision | Value |
|---|---|
| Foundation | Custom Laravel + Livewire. Bagisto is reference only (`docs/02`). |
| Shape | One app, three route contexts. Not three apps. |
| Money | Integer sen, `_sen` suffix, MYR settlement. Display conversion only. |
| Orders | `orders` → `sub_orders` (per store) → `order_items` (snapshotted). |
| Locale | `en` default + fallback; `ms` at launch. |
| COD | Enabled, RM500 cap (settings). |
| Windows | Return 7d, auto-complete 7d, unpaid iPay88 expiry 60min. |
| Commission | Seller override → category chain → global. All admin-editable. |
| Stores | One per seller account. |
| Guest checkout | No — account required (Shopee model). |
| Design | "Ink & Emerald" system, `docs/03`. Ink frame; emerald = money/action only. |

## Build order (milestones)

Docs are area-based; build in this sequence. Each milestone ends deployable to staging.

| M | Milestone | Doc(s) | Contents |
|---|---|---|---|
| M1 | Foundation | 04 | Packages, enums, all migrations, models, seeders, factories, route + middleware skeleton, smoke tests. |
| M2 | Storefront | 05 | Ink-frame layout, home, listing/search/filters, PDP with variant picker, store page, cart, auth + Turnstile, buyer account shell, locale/currency switchers. Runs on demo seed data. |
| M3 | Seller core | 07 §A | Seller onboarding/application, dashboard shell, **product CRUD with variant matrix builder**, shop settings. Real catalog replaces demo data. |
| M4 | Checkout + COD | 06 §A–C | CheckoutService, order splitting, stock locking, COD end-to-end, buyer order tabs, seller order queue (confirm → ship), invoices, notifications. |
| M5 | iPay88 | 06 §D | Sandbox → production: entry, callbacks, requery, expiry job. |
| M6 | Fulfilment polish | 06 §E, 07 §B | Tracking, delivered/auto-complete jobs, cancellations, status timeline UI. |
| M7 | Admin core | 08 | Seller approvals, moderation queue, categories/attributes, orders oversight, finance views, settings, CMS, localization mgmt. |
| M8 | Marketplace depth | 09 | Reviews, voucher engine, returns/refunds, seller ledger + payouts, search upgrades, SEO jobs, multicurrency switcher polish. |
| — | Launch | 10 | Infra, scheduler, monitoring, go-live checklists. |

Post-launch phases (specced in 01 §7 / 02): chat, flash sales, courier API, bulk upload, AI listing assistant, mobile API.

## How to drive this with Claude Code

Kick off any milestone with: *"Read CLAUDE.md and docs/00-overview.md. We're on M<n>. Execute docs/<doc> task by task; stop after each task for review."* Keep tasks small, demand green tests, and update CLAUDE.md when a convention evolves.
