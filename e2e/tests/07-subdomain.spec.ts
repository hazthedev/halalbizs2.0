import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test('store resolves on its own subdomain', async ({ page }) => {
    execSync('php artisan e2e:cod-fixture', { cwd: process.cwd() });

    await page.goto('http://cod-journey-store.halalbizs2.0.test/');
    await expect(page.getByRole('heading', { name: 'COD Journey Store' })).toBeVisible();
    await page.screenshot({ path: `e2e/screenshots/w0-subdomain-${test.info().project.name}.png`, fullPage: true });
});
