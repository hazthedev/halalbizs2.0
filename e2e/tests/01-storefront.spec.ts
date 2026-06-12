import { test, expect, Page } from '@playwright/test';

test.describe('M2 storefront journey', () => {
    test('browse home, open PDP, select variant, add to cart, adjust qty', async ({ page }) => {
        // --- Home renders with seeded sections ---
        await page.goto('/');
        await expect(page.locator('header')).toContainText('HalalBizs');
        const cards = page.locator('a[href*="/p/"]');
        await expect(cards.first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m2-home-${test.info().project.name}.png`, fullPage: true });

        // --- Quick-add a single-variant product from the grid ---
        const quickAdd = page.getByRole('button', { name: 'Add to cart' }).first();
        await quickAdd.click();
        await expect(page.locator('header span[x-text="$store.cart.count"]')).toHaveText('1');

        // --- Open a variant-matrix PDP and drive the picker ---
        // Find a PDP with option chips by walking product links.
        const hrefs = await page.locator('main a[href*="/p/"]').evaluateAll((els) =>
            [...new Set(els.map((e) => (e as HTMLAnchorElement).getAttribute('href')))],
        );

        let variantPdpFound = false;
        for (const href of hrefs.slice(0, 12)) {
            await page.goto(href!);
            const chipGroups = page.locator('[data-option-group]');
            if ((await chipGroups.count()) >= 2) {
                variantPdpFound = true;
                break;
            }
        }

        if (variantPdpFound) {
            const groups = page.locator('[data-option-group]');
            const groupCount = await groups.count();
            for (let i = 0; i < groupCount; i++) {
                await groups.nth(i).locator('button:not([disabled])').first().click();
                await page.waitForTimeout(300); // Livewire roundtrip
            }
            await page.screenshot({ path: `e2e/screenshots/m2-pdp-${test.info().project.name}.png`, fullPage: true });

            const addBtn = page.getByTestId('pdp-add-to-cart').locator('visible=true').first();
            await expect(addBtn).toBeEnabled();
            await addBtn.click();
            await expect(page.getByText('Added to cart').first()).toBeVisible();
        }

        // --- Cart page: qty stepper updates the total ---
        await page.goto('/cart');
        await expect(page.getByText('Items total').first()).toBeVisible();
        const plus = page.getByRole('button', { name: 'Increase' }).first();
        if (await plus.isEnabled()) {
            await plus.click();
            await page.waitForTimeout(400);
            await expect(page.locator('header span[x-text="$store.cart.count"]')).not.toHaveText('1');
        }
        await page.screenshot({ path: `e2e/screenshots/m2-cart-${test.info().project.name}.png`, fullPage: true });
    });

    test('search overlay and results page', async ({ page }) => {
        await page.goto('/');
        // Grab a word from the first product title to guarantee a hit.
        const title = await page.locator('main h3').first().textContent();
        const term = title!.trim().split(/\s+/)[0];

        await page.locator('header button:has(kbd), header button:has-text("Search")').first().click();
        const input = page.locator('input[type="search"]');
        await expect(input).toBeVisible();
        await input.fill(term);
        await page.waitForTimeout(600); // debounce + roundtrip
        await input.press('Enter');

        await expect(page).toHaveURL(/\/search\?q=/);
        await expect(page.locator('a[href*="/p/"]').first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m2-search-${test.info().project.name}.png`, fullPage: true });
    });

    test('login as demo buyer and view account', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[type="email"]', 'buyer@halalbizs.test');
        await page.fill('input[type="password"]', 'password');
        await page.getByRole('button', { name: /log in/i }).click();
        await page.waitForURL('**/');

        await page.goto('/account');
        await expect(page.getByRole('heading', { name: 'Profile' })).toBeVisible();
        await expect(page.locator('input[value="Demo Buyer"], input[wire\\:model\\.defer="name"], input[wire\\:model="name"]').first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m2-account-${test.info().project.name}.png`, fullPage: true });
    });
});
