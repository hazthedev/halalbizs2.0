import { test, expect, Page, Locator } from '@playwright/test';
import { execSync } from 'child_process';

// Long Livewire pages intermittently fail Playwright's stability check on
// real clicks; JS clicks still drive wire:click. Real-click coverage lives
// in the storefront specs.
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
        const form = document.querySelector('form[action*="logout"]') as HTMLFormElement | null;
        form?.submit();
    });
    await page.waitForTimeout(800);
}

test.describe('M4 full COD journey', () => {
    test('buy → seller confirms & ships → delivered → buyer completes', async ({ page }) => {
        page.on('dialog', (dialog) => dialog.accept()); // wire:confirm dialogs
        const slug = execSync('php artisan e2e:cod-fixture', { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();

        // ===== Buyer places a COD order =====
        await login(page, 'buyer@halalbizs.test');
        await page.goto(`/p/${slug}`);
        const addBtn = page.getByTestId('pdp-add-to-cart').locator('visible=true').first();
        await addBtn.click();
        await expect(page.getByText('Added to cart').first()).toBeVisible();

        await page.goto('/checkout');
        await expect(page.locator('main').getByText('COD Journey Store').first()).toBeVisible();
        await page.locator('input[type="radio"][value="cod"]').check();
        await page.getByRole('button', { name: /place order/i }).click();
        await page.waitForURL('**/checkout/success/**', { timeout: 20000 });
        await expect(page.getByText(/order placed/i).first()).toBeVisible();
        const orderNo = (await page.locator('text=/MP\\d{4}[A-Z0-9]{6}/').first().textContent())?.trim();
        await page.screenshot({ path: `e2e/screenshots/m4-success-${test.info().project.name}.png`, fullPage: true });

        // Buyer sees it under To Ship
        await page.goto('/account/orders?tab=to-ship');
        await expect(page.locator('main').getByText('COD Journey Store').first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m4-buyer-toship-${test.info().project.name}.png`, fullPage: true });
        await logout(page);

        // ===== Seller confirms, ships, delivers =====
        await login(page, 'cod-seller@halalbizs.test');
        await page.goto('/seller/orders');
        await jsClick(page.locator('tbody a[href*="/seller/orders/"]'));
        await page.waitForURL('**/seller/orders/**');

        await jsClick(page.getByRole('button', { name: /confirm & pack/i }));
        await expect(page.getByRole('button', { name: /arrange shipment/i })).toBeVisible({ timeout: 10000 });
        await page.screenshot({ path: `e2e/screenshots/m4-seller-detail-${test.info().project.name}.png`, fullPage: true });

        await jsClick(page.getByRole('button', { name: /arrange shipment/i }));
        await page.waitForTimeout(400);
        await page.locator('select').last().selectOption({ index: 1 }); // courier
        await page.locator('input[placeholder*="tracking" i], input[wire\\:model*="tracking" i]').last().fill('MYTRACK123456');
        await jsClick(page.getByRole('button', { name: /^ship|mark .*shipped|confirm shipment/i }).last());
        await expect(page.getByText(/MYTRACK123456/).first()).toBeVisible({ timeout: 10000 });

        await jsClick(page.getByRole('button', { name: /mark delivered/i }));
        await expect(page.getByText(/delivered/i).first()).toBeVisible({ timeout: 10000 });
        await logout(page);

        // ===== Buyer confirms receipt =====
        await login(page, 'buyer@halalbizs.test');
        await page.goto('/account/orders?tab=to-receive');
        await expect(page.locator('main').getByText('COD Journey Store').first()).toBeVisible();
        await jsClick(page.getByRole('button', { name: /order received/i }));
        await page.waitForTimeout(800);

        await page.goto('/account/orders?tab=completed');
        await expect(page.locator('main').getByText('COD Journey Store').first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m4-completed-${test.info().project.name}.png`, fullPage: true });
    });
});
