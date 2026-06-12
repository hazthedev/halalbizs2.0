import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

const PNG_1PX =
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function fixturePng(name: string): string {
    const dir = path.join(process.cwd(), 'e2e', 'fixtures');
    fs.mkdirSync(dir, { recursive: true });
    const file = path.join(dir, name);
    if (!fs.existsSync(file)) fs.writeFileSync(file, Buffer.from(PNG_1PX, 'base64'));
    return file;
}

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/');
}

test.describe('M3 seller journey', () => {
    test('apply, approve, create variant product, publish, see on storefront', async ({ page }) => {
        const stamp = `${test.info().project.name}${Date.now()}`;
        const email = `seller-e2e-${stamp}@halalbizs.test`;
        const shopName = `E2E Shop ${stamp}`;
        const productName = `E2E Keris Lamp ${stamp}`;

        execSync(`php artisan e2e:user ${email}`, { cwd: process.cwd() });
        await login(page, email);

        // --- Apply to become a seller ---
        await page.goto('/seller/apply');
        await page.fill('input[wire\\:model\\.live\\.debounce\\.400ms="name"]', shopName);
        await page.fill('textarea[wire\\:model="description"]', 'Quality goods for the e2e journey.');
        await page.selectOption('select[wire\\:model="state"]', 'Selangor');
        await page.selectOption('select[wire\\:model="bankName"]', 'Maybank');
        await page.fill('input[wire\\:model="accountName"]', shopName);
        await page.fill('input[wire\\:model="accountNumber"]', '1234567890');
        await page.setInputFiles('input[wire\\:model="ssmFile"]', fixturePng('ssm.png'));
        await page.setInputFiles('input[wire\\:model="icFile"]', fixturePng('ic.png'));
        await page.waitForTimeout(1500); // Livewire temp uploads
        await page.check('input[wire\\:model="confirm"]');
        await page.getByRole('button', { name: /submit/i }).click();
        await expect(page.getByText(/application received/i).first()).toBeVisible({ timeout: 15000 });
        await page.screenshot({ path: `e2e/screenshots/m3-applied-${test.info().project.name}.png` });

        // --- Approve via artisan (admin panel arrives in M7) ---
        execSync(`php artisan seller:approve ${email}`, { cwd: process.cwd() });

        // --- Seller dashboard ---
        await page.goto('/seller');
        await expect(page.getByText(/live products/i).first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m3-dashboard-${test.info().project.name}.png`, fullPage: true });

        // --- Create a variant product ---
        await page.goto('/seller/products/create');
        await page.fill('input[wire\\:model="name.en"]', productName);
        await page.fill('textarea[wire\\:model="description.en"]', 'A fine handcrafted lamp, tested end to end.');

        await page.selectOption('select[wire\\:model\\.live="categoryTop"]', { index: 1 });
        await page.waitForTimeout(500);
        await page.selectOption('select[wire\\:model\\.live="categoryChild"]', { index: 1 });
        await page.waitForTimeout(500);
        const leaf = page.locator('select[wire\\:model\\.live="categoryLeaf"]');
        if (await leaf.isVisible()) {
            await leaf.selectOption({ index: 1 });
            await page.waitForTimeout(500);
        }

        await page.setInputFiles('input[wire\\:model="newImages"]', fixturePng('product.png'));
        await page.waitForTimeout(1500);

        // Variations: Colour (Red, Blue) ?? Size (S, M)
        await page.check('input[wire\\:model\\.live="hasVariations"]');
        await page.waitForTimeout(400);
        await page.fill('input[wire\\:model="optionGroups.0.name"]', 'Colour');
        for (const value of ['Red', 'Blue']) {
            await page.fill('input[wire\\:model="optionGroups.0.draft"]', value);
            await page.press('input[wire\\:model="optionGroups.0.draft"]', 'Enter');
            await page.waitForTimeout(500);
        }
        await page.locator('button[wire\\:click="addOptionGroup"]').evaluate((el: HTMLElement) => el.click());
        await page.waitForTimeout(500);
        await page.fill('input[wire\\:model="optionGroups.1.name"]', 'Size');
        for (const value of ['S', 'M']) {
            await page.fill('input[wire\\:model="optionGroups.1.draft"]', value);
            await page.press('input[wire\\:model="optionGroups.1.draft"]', 'Enter');
            await page.waitForTimeout(500);
        }

        // Fill every matrix row: price + stock
        const priceInputs = page.locator('input[wire\\:model^="matrix."][wire\\:model$=".price"]');
        const rows = await priceInputs.count();
        expect(rows).toBe(4); // 2 colours ?? 2 sizes
        for (let i = 0; i < rows; i++) {
            await priceInputs.nth(i).fill('29.90');
            await page.locator('input[wire\\:model^="matrix."][wire\\:model$=".stock"]').nth(i).fill('10');
        }
        await page.screenshot({ path: `e2e/screenshots/m3-matrix-${test.info().project.name}.png`, fullPage: true });

        // JS click: mobile emulation never reports the button stable (long form,
        // scroll fighting); desktop exercises the real click path.
        await page.locator('button:has-text("Publish")').first().evaluate((el: HTMLElement) => el.click());
        await page.waitForURL('**/seller/products**', { timeout: 20000 });
        await expect(page.getByText(productName).first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m3-products-index-${test.info().project.name}.png`, fullPage: true });

        // --- Product appears on the storefront ---
        await page.goto(`/search?q=${encodeURIComponent('E2E Keris Lamp')}`);
        await expect(page.getByText(productName).first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m3-storefront-${test.info().project.name}.png`, fullPage: true });
    });
});
