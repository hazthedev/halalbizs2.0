import { clickInView } from '../support';
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

        test.info().annotations.push({ type: 'variant-picker', description: variantPdpFound ? 'exercised' : 'not found' });
        if (variantPdpFound) {
            const groups = page.locator('[data-option-group]');
            const groupCount = await groups.count();
            for (let i = 0; i < groupCount; i++) {
                const chip = groups.nth(i).locator('button:not([disabled])').first();
                await clickInView(chip);
                await expect(chip).toHaveAttribute('aria-pressed', 'true');
            }
            await page.screenshot({ path: `e2e/screenshots/m2-pdp-${test.info().project.name}.png`, fullPage: true });

            const addBtn = page.getByTestId('pdp-add-to-cart').locator('visible=true').first();
            await expect(addBtn).toBeEnabled();
            await clickInView(addBtn);
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
        await page.fill('input[type="email"]', `${process.env.E2E_BUYER_EMAIL}`);
        await page.fill('input[type="password"]', process.env.E2E_PASSWORD!);
        await page.getByRole('button', { name: /log in/i }).click();
        await page.waitForURL('**/');

        await page.goto('/account');
        await expect(page.getByRole('heading', { name: 'Profile' })).toBeVisible();
        await expect(page.locator('input[value="Demo Buyer"], input[wire\\:model\\.defer="name"], input[wire\\:model="name"]').first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m2-account-${test.info().project.name}.png`, fullPage: true });
    });

    test('shopping concierge opens from its viewport launcher', async ({ page }) => {
        await page.goto('/');
        const launcher = test.info().project.name === 'mobile' ? 'Ask the concierge' : 'Open shopping assistant';
        await page.getByRole('button', { name: launcher, exact: true }).click();

        // The panel reveals its composer + brand heading.
        await expect(page.getByPlaceholder(/Ask about products/)).toBeVisible();
        await expect(page.getByText('Shopping concierge')).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m2-concierge-${test.info().project.name}.png`, fullPage: true });
    });
});
