import { test, expect } from '@playwright/test';
import { execSync, suiteFixture } from '../support';

test('store resolves on its own subdomain', async ({ page, baseURL }) => {
    execSync('php artisan e2e:cod-fixture', { cwd: process.cwd() });

    const storeURL = new URL(baseURL!);
    const store = JSON.parse(suiteFixture('store').toString());
    storeURL.hostname = `${store.slug}.${storeURL.hostname}`;
    await page.goto(storeURL.href);
    await expect(page.getByRole('heading', { name: 'COD Journey Store' })).toBeVisible();
    await page.screenshot({ path: `e2e/screenshots/w0-subdomain-${test.info().project.name}.png`, fullPage: true });
});
