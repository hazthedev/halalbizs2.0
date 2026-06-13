import { test, expect, Page, Locator } from '@playwright/test';
import { execSync } from 'child_process';

async function jsClick(locator: Locator) {
    await locator.first().waitFor({ state: 'visible' });
    await locator.first().evaluate((el: HTMLElement) => el.click());
}

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/');
}

async function logout(page: Page) {
    await page.evaluate(() => {
        (document.querySelector('form[action*="logout"]') as HTMLFormElement | null)?.submit();
    });
    await page.waitForTimeout(800);
}

test.describe('Sponsored product boosts', () => {
    test('seller boosts a product from balance; it shows Sponsored on listings', async ({ page }) => {
        page.on('dialog', (dialog) => dialog.accept()); // wire:confirm
        execSync('php artisan e2e:cod-fixture', { cwd: process.cwd() });
        execSync('php artisan e2e:ledger-credit cod-seller@halalbizs.test 500000', { cwd: process.cwd() });

        await login(page, 'cod-seller@halalbizs.test');
        await page.goto('/seller/boosts');

        await page.selectOption('select#boost-product', { index: 1 });
        await page.fill('input#boost-days', '7');
        await page.waitForTimeout(400); // live cost recompute
        await jsClick(page.getByRole('button', { name: /boost now/i }));

        // Active boost row shows the product + an Active pill (scope to the
        // row span, not the still-present <option> in the select).
        await expect(page.locator('span.line-clamp-1', { hasText: 'COD Journey Widget' }).first()).toBeVisible({ timeout: 10000 });
        await page.screenshot({ path: `e2e/screenshots/boost-seller-${test.info().project.name}.png`, fullPage: true });
        await logout(page);

        // Guest sees the Sponsored badge on the search listing.
        await page.goto('/search?q=' + encodeURIComponent('COD Journey Widget'));
        await expect(page.getByText('Sponsored', { exact: true }).first()).toBeVisible({ timeout: 10000 });
        await page.screenshot({ path: `e2e/screenshots/boost-sponsored-${test.info().project.name}.png`, fullPage: true });
    });
});
