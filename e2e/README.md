# Local browser journeys

Specs 00–16 run one Chromium worker on desktop (1280×800) and mobile (390×844). Long mobile forms use native keyboard activation because Chromium emulation can pan its visual viewport and misplace pointer coordinates; desktop exercises pointer clicks. CMS coverage includes draft isolation, signed preview, publish, restore, scheduled layouts, all four repeatable section types and web/app visibility. WHIP coverage includes discovery, linking, import, application tracking, renewal reminders, certification links, buyer details and an unverified-SSM refusal.

Use a disposable local database or back up `hbiz2_qa` before a run and restore it afterwards. The suite writes orders, catalog fixtures and content. Run-scoped accounts use generated passwords passed only in memory/stdin; traces are off because login requests contain secrets. The existing catalog and settings must be seeded, migrations current and purchasing enabled locally. Setup uses generated test signing data for the iPay88 bridge; Playwright intercepts its sandbox POST.

Never use the live site, old preview or real WHIP. These fixtures refuse a nonlocal database. Start the local app and the CLI runner with the same environment:

```sh
export APP_URL=http://halalbizs2.0.test:8876
export E2E_BASE_URL=$APP_URL
export WHIP_DB_DRIVER=sqlite
export WHIP_DB_DATABASE=/tmp/hb-e2e-whip.sqlite
export WHIP_APPLY_URL=$APP_URL/e2e-whip/apply
export WHIP_RENEW_URL=$APP_URL/e2e-whip/renew
export MAIL_MAILER=array
export QUEUE_CONNECTION=sync
touch "$WHIP_DB_DATABASE"
printf '%s' '{"action":"init"}' | php e2e/fixtures/whip-fixture.php
php artisan serve --host=127.0.0.1 --port=8876 --no-reload
```

In another terminal with the same environment, run `npm run e2e -- --retries=0 --reporter=line,json`. Set `PLAYWRIGHT_JSON_OUTPUT_FILE` to save pass/total evidence. Stop the server by its PID and restore the local database snapshot after testing; the suite’s dependent order records require that restoration. Remove the disposable WHIP SQLite file.

A dedicated disposable database with automatic snapshot restoration is the better approach for CI. This local run reuses the existing seeded QA database and restores its backup; no new packages or customer feature changes are required.
