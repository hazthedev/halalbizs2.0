# HalalBizs Admin Guide

This guide is for HalalBizs staff who run the marketplace: approving sellers and halal certificates, watching orders, payments, returns and payouts, and managing the storefront. Menu and button names are written in **bold** exactly as the Admin panel shows them. What you can see depends on the permissions on your account.

> Today the marketplace is in listing-only mode (**System > Settings > General**, **Allow buyers to add to cart and checkout** is off). Online payment is still in development and runs on a built-in simulator. A real merchant code is needed before real money moves.

## 1. Sign in

1. Open the admin sign-in page (/admin/login). Enter **Email** and **Password** and select **Log in**. There is no sign-up here: the page says "Admin access is granted by an existing administrator."
2. If two-factor sign-in is on for your account, the **Two-factor check** page asks for a 6-digit code. Enter it and select **Verify and log in**. Select **Email me a new code** if it expired.
3. Your email must be verified. Admin two-factor is optional today.
4. When you sign in from a new device without two-factor sign-in, the **Two-factor check** page says "You're signing in from a new device. Enter the code we emailed to confirm it's you." Enter the 6-digit code from the email "Your login code" (it expires in 10 minutes) and select **Verify and log in**. The device is remembered only after the code passes. The first device you ever use does not need a code.
5. The menu has: **Dashboard**, **Sellers**, **Buyers**, **Catalog**, **Orders**, **Finance**, **Content**, **Support**, **System**. A section you do not have permission for is missing or blocked. <!-- SHOT: admin menu -->
6. Select **View storefront** to see the public site and **Log out** to leave.
7. To add an admin: **System > Staff & roles**, select **Invite admin**, enter **Name** and **Email**, tick the **Permissions** and select **Invite admin**. The new admin gets an email to set their own password. You can only grant permissions you hold yourself. **Edit permissions**, **Make superadmin** and **Remove admin** are on the same list. Only a superadmin can make, demote or remove a superadmin, and there is always at least one. Each of these actions is written to **System > Audit log**.

## 2. Dashboard and reports

Every list works the same way: the search box and filters sit at the top, **Rows per page** at the top right of the table, and selecting a column heading sorts by it (an arrow marks the column the list is sorted by and points the way it runs). Under the table, "Showing x to y of z" is on the left and the page numbers on the right. **Finance > Boosts**, **Finance > Payouts**, **Orders > Returns** and **Sellers > Applications** have a search box too.

1. **Dashboard** shows **GMV (paid)**, **Commission revenue**, **Completed sub-orders**, **Take-rate**, **Boost revenue**, **Orders today**, **New buyers today**, **Coins in circulation**, **Active subscriptions**, **Affiliate referrals**, **Live now**, and the **Group-buy unlock rate**. Change the period with the stats period buttons.
2. **Pending queues** link straight to work waiting: applications, certificates, moderation, payouts, returns.
3. Charts: **GMV — last 30 days**, **New buyers over time**, **Orders by status**, **Top categories by GMV**, **Top stores by GMV**.
4. **System > Search insights** shows **Trending** searches and **Zero-result** searches (what buyers wanted and did not find).
5. **System > Audit log** lists who changed what, with a search box (who, event, description), **Subject** and date filters, a **Diff** of changes, and **Rows per page** at the top right of the table.
6. **Finance > Boosts** reports boost income. **Finance > Payouts** has **Export bank CSV**.

## 3. Sellers and applications

1. Open **Sellers > Applications**. The queue is oldest first. Select **Review** on a row. <!-- SHOT: application review panel -->
2. Check the owner, **Phone**, **State**, **SST registered**, **Bank details**, and the **Documents** (SSM certificate and IC copy). Select **Open file** to view each (private, admin only).
3. For each document select **Verify**, or **Reject** (the applicant must provide it again). Add notes if needed.
4. To accept, select **Approve store**. The owner gets seller access at once and an email.
5. To decline, write the **Rejection reason** (required, emailed to the applicant) and select **Reject application**.
6. **Sellers > Stores** lists every store. Search by store name or owner email, filter by status, and sort by any column heading. Open a store to see **Live products**, **Sub-orders**, **GMV (completed)**.
7. To suspend, write the **Suspension reason (emailed to the owner)** and select **Suspend store**. The shop leaves the storefront. **Reinstate store** brings it back.
8. **Record settlement**: when a seller who owes money pays by bank transfer, enter **Amount (RM)**, **Bank reference** and a note, then select **Record settlement**.
9. **Buyers**: search by name or email, filter by status, sort by any column heading, open a buyer and use **Suspend account** (with a reason) or **Unsuspend account**.

## 4. Halal certificates review

1. Open **Catalog > Halal certificates**. Filters: **Awaiting review**, **Approved**, **Rejected**, **All**, plus **Issuing body**, **Store**, **Expiring soon**.
2. Select **Review** on a row. Check **Holder**, **Issuing body**, **Standard**, **Facility**, **Scope** and select **Open the certificate document**. The document is private. If none is attached, reject and ask for one.
3. Compare the products the seller ticked with the certificate scope. A product the scope does not name shows "Not on the certificate". Select **Allow** only if you are sure it is covered.
4. Select **Approve** when valid. Nothing gets the halal badge until you approve.
5. To refuse, write a reason ("The seller sees this word for word") and select **Reject**.
6. Certificates close to expiry show a warning to the seller. When a certificate expires, the badge goes away, and for gated categories the listing stops being allowed.

## 5. Categories and the halal gate

1. Open **Catalog > Categories**. The tree can be three levels deep. Use **Add root category**, **Add child**, **Edit**, the move up and move down buttons, and **Activate** or **Deactivate**. **Delete** works only when a category has no children or products.
2. In the form fill in **Name (English)**, and optional Malay and Vietnamese names and descriptions. Add an **Image** and tick **Active — visible on the storefront**. Choose **Attributes for this category**.
3. Set **Halal certificate**: **Inherit from parent**, **Required to list** or **Not required**. Required means a seller cannot list there without an approved certificate covering the product.
4. The gate starts on 2 December 2026. On live, the required categories are **Food & Snacks**, **Groceries & Pantry**, **Drinks**, **Home & Living > Groceries**, **Cosmetics & Care** and **Pharma & Supplements**.
5. Select **Create category** or **Save changes**.
6. Related catalog menus: **Attributes**, **Brands**, **Reviews** (sort by **Rating** or **Posted**, hide unsuitable reviews), and **Moderation**. The **Attributes** and **Brands** lists have a search box, sortable headers and **Rows per page** at the top right of the table.
7. **Moderation**: when **New products need admin approval before going live** is on (System > Settings), new products wait here. Select **Approve**, or **Reject** with a reason (the product returns to drafts and the seller gets the reason), or **Ban** to remove it. You can bulk **Approve selected**, **Reject selected**, **Ban selected**.

## 6. Commission

1. Open **Finance > Commission**. The rule: store override, then category chain upward, then the global default.
2. Under **Global default rate** type **Rate (%)** and select **Save rate**.
3. Under **Commission basis** choose what the rate is charged on and select **Save basis**. It applies to orders completed from now on.
4. Use **Effective-rate tester**: pick a **Store** and a **Category** to see the rate a new order would take.
5. Category rates are set in **Catalog > Categories** (**Commission rate (%)**, empty means inherit). Store rates are set in **Sellers > Stores > (store) > Commission override** with **Save override**.
6. A rate change affects only new orders. Placed orders keep their rate.
7. Sellers see their rate on the **Become a seller** form and in **Shop settings** ("10% of each sale", or a range "by category"), and on each order page ("Platform commission rate"). A change to the global rate or a category rate is recorded in **System > Audit log** with the old and new rate.

## 7. Orders, payments and refunds

1. Open **Orders > All orders**. Search by order or sub-order number, and filter by **Status**, **Store**, **Payment method** and dates. <!-- SHOT: All orders -->
2. Open a row to see items, **Timeline**, **Payment** (method, gateway reference and status) and **Totals**. Order data is a snapshot and is read-only.
3. **Admin actions**: **Force cancel** with a **Cancellation reason** (stock returns, buyer is told, no undo). For an online order that was paid and must be refunded, first refund the money in the iPay88 merchant portal, then enter the **iPay88 portal reference** and select **Mark refunded**. For cash on delivery there is no gateway money to move.
4. **Orders > Payments** is the reconciliation grid. It shows **Ref no.**, **Trans ID**, **Signature** (**Signature verified** or **Mismatch**), **Requery result** and **Needs portal refund**. Search by order number, **Ref no.** or **Trans ID**, sort by **Ref no.**, **Amount**, **Status** or **Paid at**, and filter by **Mismatches only**. Select **Requery** on a stuck payment.
   - A row marked **Needs portal refund** means the gateway did not refund the money. Filter by **Needs portal refund** to list them. Refund the money in the iPay88 merchant portal first. Then select **Mark refund settled in portal** on that row, enter the **Portal reference (optional)** and select **Confirm settled**. The flag clears, the row then reads **Settled in portal**, and the action is kept in the audit log with your name and the reference. **Cancel** closes it without a change.
5. While payment is in simulator mode, simulated payments show here too.
6. **Orders > Subscriptions** lists recurring buyers by status. Search by buyer or product, and sort by **Every** or **Next**.
7. **System > Settings > iPay88** shows **Simulator active**, **Sandbox configured**, **Live configured** or **Not configured**. Save **Merchant code**, **Merchant key** and **Sandbox mode**, then **Test connection**. **Online payment is hidden from buyers until both credentials are saved.**
8. On live today no merchant code or key is saved, so the simulator is active. Do not tell anyone that real card or FPX payment works until both are saved and **Test connection** passes.

## 8. Returns and refunds

1. Open **Orders > Returns**. Tabs follow the return status: **Requested**, **Accepted**, **Disputed**, **Escalated**, **Refunded**, **Rejected**. Disputed and auto-escalated returns need your decision.
2. Select **Resolve**. Read the **Reason**, **Buyer says**, **Seller says**, photos and **Refund amount**.
3. To refund, for an online order first move the money in the iPay88 merchant portal and enter the **iPay88 portal reference**. For cash on delivery the refund is recorded as a ledger adjustment against the store. Then select **Refund buyer**. This closes the return and cannot be undone.
4. To refuse, select **Side with seller**. The return is rejected and the order goes back to its previous status.
5. A refund before the order completes also takes back the coins the buyer earned and the seller's sale.

## 9. Payouts

1. Open **Finance > Payouts**. Tabs: **Requested**, **Approved**, **Paid**, **Rejected**.
2. Check the store and bank details against the verified documents. Select **Approve**, or **Reject** with a **Rejection reason** (the money returns to the seller's available balance). If another admin has already approved, paid or rejected the payout, **Approve** and **Mark paid** change nothing and show an error.
3. On **Approved**, tick the payouts to pay and select **Export bank CSV**, then run the file at the bank. The box in the header ticks only the rows on screen: this page, after any search. Rows on other pages or hidden by the search are never ticked, and changing the search, sort, rows per page, page or tab clears the ticks. The line above the table shows how many payouts the export will include. Only payouts still approved are exported.
4. When the transfer is done, select **Mark paid** and enter the **Bank reference**. This is final.
5. **Finance > Affiliates** lists creators and **Pending withdrawals** with **Mark paid** (bank transfer reference) and **Reject**. Creator commission is 2.5% of the referred items' value; the system caps it at 2.5% even if a higher rate is configured, so a referred sale always leaves the platform its own commission. The creator list has a search box, sortable column headers and a footer with the number of rows per page.

## 10. Banners, home sections and pages

1. **Content > Banners**: select **Add banner**. Set titles per language, **Button text**, and **When tapped, open** (a product, store, category, the flash sale, a page, the help centre, search results, or a web address). Add the **Image** (wide, 3:1, at least 1200 pixels, up to 4 MB), optional **Vietnamese image** and **Video**, **Starts at** and **Ends at**, and **Active**. When editing, the current image shows above **Replace image**. Select **Create banner** or **Save banner**. Reorder with **Move up** and **Move down**.
2. **Content > Home sections**: select a **Section type**, then **Add section**. The sections show on the home page top to bottom. Edit **Heading**, **Source** (**Latest products**, **Top sellers**), **Limit**, picked products or stores, tiles with pictures and links, and **Show on** (**Web and app**, **Web only**, **App only**). Select **Save section**.
3. Changes are drafts first (a **Draft** tag). Use **Preview** to look, then **Publish** (or **Publish all**). **Throw away** discards a draft. **History** lists past versions, with **Restore as draft** and **Restore and publish**.
4. **Scheduled layouts**: **New scheduled layout** (for example Ramadan) with **Starts at** and **Ends at**. It switches on by itself and goes back to the everyday layout at the end.
5. **Content > Pages**: **Add page** for terms, FAQ and similar. Set **Slug**, titles and bodies per language, search title and description, **Share picture**, **Show a link to this page in the website footer** and **Published**. System pages keep their slug. Terms and privacy stay published because checkout links to them.
6. **Footer & contact**: update **Email**, **Phone**, **WhatsApp**, **Address** and social links, then **Save contact**.
7. **Content > Flash sales**: enter **Title**, **Starts**, **Ends**, select **Create flash sale**, then **Add deal** with a **Promo RM** price, **Qty** and **Per buyer** limit.
8. **Support > Help articles** and **Support > Tickets**: reply with **Send reply**, **Mark urgent** / **Mark normal**, **Close ticket**, **Reopen ticket**. Tickets and help articles have a search box, sortable headers and **Rows per page** at the top right of the table; help articles also filter by category.
9. **System > Localization**: turn languages on or off and set currencies and exchange rates (display only, charges stay in MYR). For each currency type the **Rate** and the **Margin %**, then select **Save rate**.

## 11. Coins and vouchers settings

1. **Finance > Loyalty Coins**: shows **Coins in circulation** and each buyer's **Balance** and **Lifetime**. Select **Adjust**, enter **Coins (+ grant / − clawback)** and a **Reason**, then select **Apply**. The reason is recorded.
2. The earning and spending rules (coins per RM, redemption value, per-order cap, expiry months, daily check-in rewards and the spin prizes) are not on a screen. They are set by the technical team in the app configuration. On live today: 1 coin per whole RM1 of a completed order, 1 coin = 1 sen off, at most RM50 of coins per order, coins expire 12 months after they are earned (buyers get one reminder 30 days before), and daily check-in pays 5, 5, 10, 10, 15, 15 and 30 coins over a 7-day streak.
3. **Content > Vouchers** (platform vouchers): select **Add voucher**. Enter **Code** (stored in uppercase), **Type** (amount, percent, free shipping), **Maximum discount (RM)**, **Minimum spend (RM)**, **Quota**, **Per-user limit**, **Starts at**, **Ends at** and **Active**. Select **Create voucher** or **Save voucher**. The voucher list has a search box for the code, sortable headers and a footer with the number of rows per page.
4. Buyers can use one platform voucher, one shop voucher and one free-shipping voucher per order. Sellers make their own shop vouchers.
5. **System > Settings** sections, each with its own save button: **General** (**Allow buyers to add to cart and checkout**: turn off for listing-only mode, and **Display currencies**), **Orders** (**Return window (days)**, **Auto-complete after (days)**, **Unpaid order expiry (minutes)**, **Minimum payout (RM)**), **Cash on delivery** (**COD enabled platform-wide**, **Maximum COD order (RM)**), **Mobile app**, **Moderation**, **Security**, **Tracking** and **iPay88**. Live values today: return window 7 days, auto-complete 7 days, unpaid order expiry 60 minutes, seller return response 48 hours, minimum payout RM50, COD on with a RM500 maximum. A change to **Return window (days)** applies to orders delivered after you save it: an order already delivered keeps the window it had on its delivery day, for the buyer's return and for the seller's money hold.
6. The **Security** section holds **Cloudflare Turnstile**, **Google sign-in** and the password rules. The breached-password check stays off while the site is in development.
