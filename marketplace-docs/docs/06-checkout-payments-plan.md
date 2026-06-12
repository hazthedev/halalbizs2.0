# 06 — Checkout, Payments & Order Lifecycle (M4–M6)

The money path. Every rule in CLAUDE.md "Hard rules" 1–5 applies on every task here. Test coverage is non-negotiable, including concurrency tests.

## A. Checkout page `/checkout` (M4)

Single page, auth + verified-email required, selected cart items only.

Layout: left column — (1) **Address** card: default address with "Change" → slide-over list + add-new inline; (2) **Per-seller groups**: store name, item rows (read-only), shipping option per store (v1: store's flat rate or per-state matrix rate resolved against chosen address; display fee), seller-note input; (3) **Voucher** row: code input + Apply (full engine M8; until then validates platform vouchers only — flag `voucher_lite`). Right column — sticky **summary**: items subtotal, shipping total, discounts, **grand total** (MYR; if display currency ≠ MYR show "≈ {converted}" with disclaimer line), **payment method radio**: iPay88 (logos: FPX, Visa/MC, TNG, GrabPay, Boost, ShopeePay) | COD (hidden if any selected item has `cod_enabled=false`, store disabled it, or total > `CodSettings.max_order_sen` — show why) — then **Place order** (emerald, full width).

Reactivity: any mutation (address, shipping, voucher, method) sets `recalculating=true` → Place order disabled until totals settle (Bagisto race lesson). Server re-validates everything on submit regardless of UI state.

## B. CheckoutService::place() (M4) — the transaction

Inside ONE DB transaction:
1. Reload selected cart items with `variant->lockForUpdate()`. Validate: live product, store approved + not holiday, stock ≥ qty, COD constraints if COD.
2. Voucher (if applied): re-validate + consume atomically (`lockForUpdate()` on voucher row, check quota/per-user, increment `used_count`, insert `voucher_usages`).
3. Compute totals in sen: per-store items subtotal, shipping fee (ShippingCalculator: flat | state-matrix), discounts, grand total. Assert > 0.
4. Create `orders` row (order_no `MP` + base36 ULID-ish, address json snapshot, method, display currency + rate snapshot, `expires_at = now+60min` if iPay88).
5. Create one `sub_orders` per store: totals, `commission_rate` = CommissionResolver, status `pending_payment` (iPay88) or `confirmed` (COD) **via SubOrderStatusService** (initial-state insert allowed, history row written).
6. Create `order_items` snapshots (name in current display locale's value w/ en fallback, variant label, effective unit price).
7. Decrement variant stock; increment product `sold_count` only on completion (LedgerService M8) — NOT here.
8. Create `payments` row (gateway, ref_no = order_no, amount, status pending; COD: status stays pending until delivery).
9. Delete purchased cart items.
Commit → COD: fire `OrderConfirmed` events, redirect to success page. iPay88: redirect to `/pay/{order}` bridge.

Failure anywhere = full rollback; surface the human reason ("Blue / XL just sold out — 0 left").

**Pest concurrency tests (mandatory):** two parallel checkouts on last-stock variant → exactly one succeeds; two parallel redemptions of quota-1 voucher → exactly one succeeds (use `DB::transaction` + pcntl or sequential locked asserts pattern).

## C. COD flow (M4)
Order `payment_status=pending`, sub_orders `confirmed` immediately. Seller fulfils normally. On `delivered` → mark payment `success`, `paid_at=now` (system actor). Ledger handles cod_offset at completion (M8). Buyer cancel allowed while `confirmed` (pre-ship) → restock + status via service.

## D. iPay88 integration (M5)

> Spec from iPay88 MY technical doc family v1.6.x. **Verify field names, SignatureType, and PaymentId channel codes against the latest PDF during merchant onboarding** — record the doc version here when confirmed.

**Config** (`Ipay88Settings`): merchant_code, merchant_key (encrypted), sandbox flag. Entry endpoint `https://payment.ipay88.com.my/ePayment/entry.asp`; requery `.../ePayment/enquiry.asp` (sandbox equivalents per onboarding pack).

**D1. Bridge page `/pay/{order}`** — auto-submitting POST form (with manual "Continue to payment" fallback) carrying: `MerchantCode, PaymentId (optional preselect), RefNo (order_no), Amount ("1,250.00" → format "1250.00"), Currency (MYR), ProdDesc, UserName, UserEmail, UserContact, Remark, Lang (UTF-8), SignatureType (SHA256), Signature, ResponseURL, BackendURL`.
**Request signature** = `sha256(MerchantKey . MerchantCode . RefNo . AmountNoSeparators . Currency)` where AmountNoSeparators strips `.` and `,` — our sen integer **is already** that value (RM12.50 → amount "12.50", signature uses "1250"). Store request payload json on `payments`.

**D2. ResponseURL `/payments/ipay88/response`** (browser redirect, POST, CSRF-exempt) — UX ONLY. Verify response signature; show "Payment processing…" page that polls order state (wire:poll 2s, max 60s) → success page or "still confirming" page. Never fulfil here.

**D3. BackendURL `/payments/ipay88/backend`** (server-to-server POST, CSRF-exempt, must respond plain `RECEIVEOK`). Fields include `MerchantCode, PaymentId, RefNo, Amount, Currency, Remark, TransId, AuthCode, Status (1=success), ErrDesc, Signature`.
**Response signature** = `sha256(MerchantKey . MerchantCode . PaymentId . RefNo . AmountNoSeparators . Currency . Status)`.
Handler: locate payment by ref_no → verify signature (log + flag mismatch, do nothing else) → idempotency guard (unique gateway+TransId; already-success = return RECEIVEOK) → if Status=1: dispatch `ConfirmIpay88PaymentJob`; if 0: mark failed + store ErrDesc.

**D4. ConfirmIpay88PaymentJob** — **requery** (`MerchantCode, RefNo, Amount` → body `00` = success) as final truth. On `00`: in transaction — payment `success` + ids + paid_at; order `paid`; each sub_order → `confirmed` via service; clear `expires_at`; fire `OrderPaid` (notifications to buyer + sellers). On mismatch: flag `requery_result`, alert admin (Sentry + admin notification), leave pending.

**D5. Expiry** — `orders:expire-unpaid` every minute: orders `pending` + `expires_at` past → final requery (rescue late payments) → else payment `expired`, order `expired`, sub_orders `cancelled` (system), **restock variants**, notify buyer "payment window closed". "Pay again" button on order detail while not expired re-uses bridge with same RefNo? **No** — iPay88 RefNo should be unique per attempt: new attempt = new `payments` row with ref `order_no-2` suffix; signature uses that ref. Record decision.

**D6. Refunds** — manual in iPay88 portal v1. Admin marks payment `refunded` + reference; sub_order → `refunded` via service (M8 returns flow).

## E. Order lifecycle UX (M4 + M6)

**Buyer `/account/orders`** — Shopee tabs: To Pay (pending iPay88, countdown chip + Pay now) · To Ship (confirmed/processing) · To Receive (shipped, tracking visible, **Order received** button → completed) · Completed (Review CTA placeholder M8, Buy again) · Cancelled · Return/Refund (M8). Detail page: ink-frame-free clean sheet — status **timeline** (design §7), address, items (snapshot render), totals, payment info (mono ids), invoice PDF download, contextual actions (Cancel while confirmed & unshipped → reason select from `cancellation_reasons`).

**Seller order queue** — see `docs/07` §B: confirm → arrange shipment (courier + tracking_no) → mark shipped. Delivered: v1 = buyer confirmation or auto-complete job; courier webhook P4.

**Jobs**: `orders:auto-complete` hourly — `delivered_at + auto_complete_days` passed & no open return → `completed` via service (fires LedgerService M8). Invoice PDF (dompdf) per sub_order generated on first request, cached to storage.

**Notifications (database + queued mail)**: placed, paid, payment failed/expired, confirmed, shipped (with tracking), delivered, completed, cancelled — buyer; new paid order, cancellation — seller. Templates: short, verb-first, mono order numbers.

## F. Task sequence
M4: 1) ShippingCalculator + settings UI hooks → 2) Checkout page UI → 3) CheckoutService + concurrency tests → 4) COD e2e + success page → 5) Buyer order tabs + detail + timeline → 6) Seller queue minimal (confirm/ship) → 7) Cancel flow + restock → 8) Invoices + notifications → Dusk: full COD journey.
M5: 9) Ipay88Service (signatures unit-tested against doc examples) → 10) bridge → 11) callbacks + job + idempotency tests (replay same TransId twice) → 12) expiry job + restock test → 13) sandbox e2e all channels → 14) production cutover per `docs/10` checklist.
M6: 15) delivered/auto-complete + timeline polish → 16) Pay-again attempts → 17) edge audit (multi-seller partial cancel before payment? → not allowed: cancel = whole order pre-payment).
