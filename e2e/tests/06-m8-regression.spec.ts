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

test.describe('M8 full regression', () => {
    test('voucher checkout → fulfil → complete → review → seller ledger', async ({ page }) => {
        page.on('dialog', (dialog) => dialog.accept());

        const slug = execSync('php artisan e2e:cod-fixture', { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();
        execSync('php artisan e2e:voucher E2ESAVE5', { cwd: process.cwd() });

        // ===== Buyer: voucher checkout (COD) =====
        await login(page, 'buyer@halalbizs.test');
        await page.goto(`/p/${slug}`);
        await page.getByTestId('pdp-add-to-cart').locator('visible=true').first().click();
        await expect(page.getByText('Added to cart').first()).toBeVisible();

        await page.goto('/checkout');
        await page.fill('input[wire\\:model="voucherCode"]', 'E2ESAVE5');
        await jsClick(page.getByRole('button', { name: /^apply$/i }));
        await expect(page.getByText(/-\s?RM 5\.00|−\s?RM 5\.00/).first()).toBeVisible({ timeout: 10000 });
        await page.screenshot({ path: `e2e/screenshots/m8-voucher-checkout-${test.info().project.name}.png`, fullPage: true });

        await page.locator('input[type="radio"][value="cod"]').check();
        await page.getByRole('button', { name: /place order/i }).click();
        await page.waitForURL('**/checkout/success/**', { timeout: 20000 });
        await logout(page);

        // ===== Seller: fulfil =====
        await login(page, 'cod-seller@halalbizs.test');
        await page.goto('/seller/orders');
        await jsClick(page.locator('tbody a[href*="/seller/orders/"]'));
        await page.waitForURL('**/seller/orders/**');
        await jsClick(page.getByRole('button', { name: /confirm & pack/i }));
        await expect(page.getByRole('button', { name: /arrange shipment/i })).toBeVisible({ timeout: 10000 });
        await jsClick(page.getByRole('button', { name: /arrange shipment/i }));
        await page.waitForTimeout(400);
        await page.locator('select').last().selectOption({ index: 1 });
        await page.locator('input[placeholder*="tracking" i], input[wire\\:model*="tracking" i]').last().fill('M8TRACK99999');
        await jsClick(page.getByRole('button', { name: /^ship|mark .*shipped|confirm shipment/i }).last());
        await expect(page.getByText(/M8TRACK99999/).first()).toBeVisible({ timeout: 10000 });
        await jsClick(page.getByRole('button', { name: /mark delivered/i }));
        await page.waitForTimeout(800);
        await logout(page);

        // ===== Buyer: complete + review =====
        await login(page, 'buyer@halalbizs.test');
        await page.goto('/account/orders?tab=to-receive');
        await jsClick(page.getByRole('button', { name: /order received/i }));
        await expect
            .poll(async () => {
                await page.goto('/account/orders?tab=completed');
                return page.locator('main').getByText('COD Journey Store').first().isVisible();
            }, { timeout: 20000 })
            .toBe(true);

        await jsClick(page.getByRole('button', { name: /rate order/i }));
        await page.waitForTimeout(500);
        // Pick 5 stars on the first item then submit.
        await page.locator('fieldset input[type="radio"][value="5"]').first().check({ force: true });
        await page.waitForTimeout(400);
        await jsClick(page.getByRole('button', { name: /post review/i }));
        await page.waitForTimeout(1500);
        await page.screenshot({ path: `e2e/screenshots/m8-review-${test.info().project.name}.png`, fullPage: true });

        // PDP shows the rating aggregate.
        await page.goto(`/p/${slug}`);
        await expect(page.getByText(/★/).first()).toBeVisible();
        await logout(page);

        // ===== Seller: ledger entries on earnings page =====
        await login(page, 'cod-seller@halalbizs.test');
        await page.goto('/seller/earnings');
        await expect(page.getByText(/commission/i).first()).toBeVisible();
        // The COD-offset ledger entry is written at completion regardless of
        // the running balance sign (test credits can push it positive).
        await expect(page.getByText(/cod offset|cod cash collected/i).first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m8-earnings-${test.info().project.name}.png`, fullPage: true });
    });
});
