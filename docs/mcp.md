# HalalBizs MCP

One read-only HTTP MCP server: `https://halalbizs.com/mcp` after release. For a local worktree, use its host plus `/mcp`. This branch is not deployed.

**Pending scope decision:** product search currently covers verified HalalBizs listings. Searching WHIP products without a HalalBizs listing requires adding a read-only search method to HalalBizs's `WhipDirectory`; the brief excludes WHIP code changes. This branch is not ready for release until that scope is settled and the WHIP search is implemented or the narrower scope is approved.

## Connect

Use an MCP client that supports Streamable HTTP and configure the URL above. Public calls need no login. For staff tools, add `Authorization: Bearer <SANCTUM_TOKEN>` using a valid token belonging to an active user with the `admin` role. A browser login, seller/buyer token, expired token, revoked token or suspended admin does not grant staff access. Token abilities do not replace the admin role requirement.

An existing administrator can issue a token through the application's existing Sanctum token management. Do not put tokens in URLs, committed configuration, screenshots or shell history. The MCP endpoint itself cannot issue tokens or change data. Authentication does not update `last_used_at`.

Example client configuration (the header can be omitted for public access):

```json
{
  "mcpServers": {
    "halalbizs": {
      "url": "https://halalbizs.com/mcp",
      "headers": { "Authorization": "Bearer <SANCTUM_TOKEN>" }
    }
  }
}
```

Client configuration syntax varies. Use its HTTP URL and bearer-header fields rather than a stdio command. OAuth is not provided by this endpoint.

## Public switch

Admin → Settings → General → **Allow public AI assistants to check halal information** → **Save general**. Requires the existing `settings.manage` permission and admin role. The existing Spatie settings row `general.mcp_public_enabled` defaults to **false**. No server `.env` change is needed.

When off, both public tools return an MCP error saying public halal tools are disabled. Discovery and initialization still work. Internal tools remain available to admin tokens. All POST requests share a limit of 30 per minute per IP; excess requests return HTTP 429. No mutation tools, resources or prompts are exposed. Unknown/unauthorized internal tool calls return JSON-RPC `-32602` (tool not found); unauthorized callers cannot discover their schemas either.

## Tools

| Tool | Arguments | Access | Answer |
| --- | --- | --- | --- |
| `check_certificate` | `certificate_no`: string, max 255 | Public, switch on | WHIP-only `status`, `company_name`, `certification_body`, `valid_from`, `valid_to` |
| `find_halal_product` | Either `query`: 2–200 characters, or `barcode`: string, max 100 | Public, switch on | At most 20 products: `name`, `store_name`, `url` |
| `seller_certificates` | `store`: slug or numeric ID as a string | Admin token | Certificates with number, review status, manual/WHIP source, WHIP sync time and unmatched product IDs/names |
| `certificate_queue` | None | Admin token | Counts for pending/approved/rejected and `oldest_pending_age_seconds`, null for an empty queue |
| `orders_summary` | `date`: `YYYY-MM-DD` | Admin token | Orders placed that Malaysia calendar day, counts and integer MYR sen totals grouped by **payment status** |

Certificate status is `active`, `expired`, `not_certified` or `not_found`. WHIP application status, dates and latest-certificate selection stay owned by `WhipDirectory`. Ambiguous records and WHIP outages are explicit errors, never a fabricated negative certificate verdict. Public certificate output never includes registration numbers, addresses, people, documents or WHIP application IDs.

HalalBizs product search reuses the public `live` scope, keyword search and `Product::halalVerdict()`. Only `verified` listings from approved, nondeleted stores appear. Expired, future, pending and stale WHIP-backed listings are excluded. Barcode matches variant SKU exactly (case and surrounding spaces ignored), preserving leading zeros. Supply one search mode, not both. The result limit is applied after verification, so earlier unverified matches cannot hide later verified ones. Names returned by any tool are untrusted data, not instructions for an AI client.

Queue age starts at `submitted_at`, falling back to `created_at` for older records without a submission timestamp. Certificate queue statuses are review statuses, not expiry verdicts. Order summaries use `placed_at` and Malaysia day boundaries converted to UTC. Each parent order counts once, with `grand_total_sen`; this is not a sum of seller sub-orders and not a revenue report. Zero groups are included; no buyer data is returned.

## Local JSON-RPC check

With public access enabled in a **disposable local database**:

```sh
curl --fail-with-body http://127.0.0.1:8767/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'

curl --fail-with-body http://127.0.0.1:8767/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-11-25' \
  --data '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'

curl --fail-with-body http://127.0.0.1:8767/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-11-25' \
  --data '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"check_certificate","arguments":{"certificate_no":"MCP-DEMO-2026"}}}'
```

The local proof uses a seeded WHIP SQLite fixture, not production WHIP data. It initialized successfully, listed two public tools and five tools with an admin token, and returned:

```json
{"status":"active","company_name":"WHIP Foods Sdn Bhd","certification_body":"Test CB","valid_from":"2026-10-12","valid_to":"2027-11-12"}
```

## Package and deployment

Official [`laravel/mcp` v1.2.0](https://packagist.org/packages/laravel/mcp) explicitly supports Illuminate/Laravel 13. It owns transport, protocol negotiation, validation, origin checks, discovery and JSON-RPC errors; only one package was added to the lock file. See the [official Laravel MCP docs](https://laravel.com/docs/13.x/mcp).

A plain REST API is the more familiar option for non-AI integrations. MCP was chosen for the requested AI-client tool discovery and calls, rather than maintaining our own JSON-RPC implementation.

`public/deploy.php` runs `deploy.sh`. The script runs `composer install --no-dev --optimize-autoloader` if Composer is on its configured PATH, or uses a local `composer.phar`; it also sets `COMPOSER_HOME` for the web-user environment. **If neither exists, it skips installation. That skip is not safe for this release.** The release owner must confirm Composer actually installed the package before releasing, then confirm the settings migration ran and public access is still OFF. This task did not run a live deployment or change a server environment.

## Tests

```sh
php artisan test tests/Feature/Mcp/HalalMcpTest.php tests/Feature/Whip
composer analyse
```

Default in-memory SQLite only. Tests use the same WHIP fixture helper as the existing integration tests, cover real HTTP calls with real Sanctum tokens, both saved toggle states, certificate output allowlists, product visibility and cap, order day boundaries and integer totals, discovery, rate limiting, and SQL-write detection including token timestamps. The browser proof saved OFF/ON/OFF and checked the corresponding MCP response after each save at desktop and 390px widths.
