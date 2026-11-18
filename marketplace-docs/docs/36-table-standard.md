# Shared list tables

Part A: seller Products, seller Orders and admin Orders use `WithTable` and
`x-ui.table.sortable`, `x-ui.table.footer`, `x-ui.table.bulk-bar`.

The trait owns URL search/sort/direction/page-size state, pagination resets,
and current-page selection. Each list supplies its filter names, a server-side
sort whitelist, default ordering and a paginated `results()` query. Unknown sort
fields fall back to the original order; invalid directions/page sizes are bounded.
Selection clears when filters, sorting or pages change and is never stored in a URL.

Headers are keyboard buttons with `aria-sort`; the footer always reports Showing
(including zero matches) and offers 15/20/50/100 rows. Keep the existing card,
sticky header, skeleton and empty-state styles. Filters combine with AND.

Seller Products keeps status/category/low-stock and name/SKU search, gaining
Product, Variants, Sold, Status and Updated sorting. Its existing delist/delete
bulk actions stay store-scoped. Seller Orders gains number/buyer search, payment
and Malaysia date filters, Placed/Total sorting, and Confirm & pack selected.
Packing loops over the existing status-service action; shipment stays per order.
Admin Orders keeps its existing filters and search, gaining Placed/Total/Status
sorting. Admin actions remain on the detail page; no bulk action is invented.

Default row ordering stays as before. No package, schema/index, gateway or WHIP
change. Tests live with each screen in ProductIndexTest, OrdersTest and
OrdersOversightTest. Run against a private exported DB_DATABASE (hbiz2_t1 for
this job) with `php artisan test -c phpunit.mariadb.xml` and the named files.
