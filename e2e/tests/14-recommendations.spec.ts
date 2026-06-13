import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

function artisan(cmd: string) {
    return execSync(`php artisan ${cmd}`, { cwd: process.cwd() }).toString().trim();
}

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/');
}

const heading = (page: Page) => page.getByRole('heading', { name: 'Recommended for you' });

// Lazy Livewire islands load on IntersectionObserver; a single jump to the
// bottom can skip mid-page sections, so step down to trigger each one.
async function scrollThrough(page: Page) {
    await page.evaluate(async () => {
        for (let y = 0; y <= document.body.scrollHeight; y += 400) {
            window.scrollTo(0, y);
            await new Promise((r) => setTimeout(r, 120));
        }
    });
}

// Give the buyer a real purchase so the recommender has a signal to work from.
test.beforeAll(() => {
    artisan('e2e:cod-fixture');
    artisan('e2e:seed-sale buyer@halalbizs.test');
});

test.describe('Recommended for you', () => {
    test('the strip renders for a buyer on home, dashboard and PDP', async ({ page }) => {
        const slug = artisan('e2e:cod-fixture').split('\n').pop()!.trim();
        await login(page, 'buyer@halalbizs.test');

        // Home — the seeded "recommended" section (lazy island; scroll to load).
        await page.goto('/');
        await scrollThrough(page);
        await expect(heading(page)).toBeVisible({ timeout: 15000 });
        await expect(page.locator('a[href*="/p/"]').first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/recs-home-${test.info().project.name}.png`, fullPage: true });

        // Buyer dashboard — "Picked for you".
        await page.goto('/account/dashboard');
        await scrollThrough(page);
        await expect(heading(page)).toBeVisible({ timeout: 15000 });

        // PDP — personalised strip below related products.
        await page.goto(`/p/${slug}`);
        await scrollThrough(page);
        await expect(heading(page)).toBeVisible({ timeout: 15000 });
        await page.screenshot({ path: `e2e/screenshots/recs-pdp-${test.info().project.name}.png`, fullPage: true });
    });

    test('a guest still gets a populated home (popular fallback, no crash)', async ({ page }) => {
        await page.goto('/');
        await scrollThrough(page);
        // Guest path falls back to popular once localStorage hydrates; the page
        // must render product links and not error.
        await expect(page.locator('a[href*="/p/"]').first()).toBeVisible();
    });
});
