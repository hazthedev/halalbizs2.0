import { test, expect, Page, Locator } from '@playwright/test';
import { execSync } from 'child_process';

function artisan(cmd: string) {
    return execSync(`php artisan ${cmd}`, { cwd: process.cwd() }).toString().trim();
}

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

async function adminLogin(page: Page) {
    await page.goto('/login');
    await page.fill('input[type="email"]', 'admin@halalbizs.test');
    await page.fill('input[type="password"]', 'password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/two-factor-challenge', { timeout: 15000 });
    const code = artisan('e2e:otp admin@halalbizs.test').split('\n').pop()!.trim();
    await page.fill('input[name="code"]', code);
    await page.getByRole('button', { name: /verify/i }).click();
    await page.waitForURL((url) => !url.pathname.includes('two-factor-challenge'), { timeout: 15000 });
}

// Seed a completed sale so every dashboard has real spend/revenue/GMV to chart.
test.beforeAll(() => {
    artisan('e2e:cod-fixture');
    artisan('e2e:seed-sale buyer@halalbizs.test');
});

async function assertChartAndPeriodSwitch(page: Page, name: string) {
    await expect(page.locator('.apexcharts-canvas').first()).toBeVisible({ timeout: 15000 });
    await page.screenshot({ path: `e2e/screenshots/dash-${name}-${test.info().project.name}.png`, fullPage: true });

    const periodButtons = page.locator('button[wire\\:click*="period"]');
    if (await periodButtons.count() > 1) {
        await jsClick(periodButtons.last());
        await page.waitForTimeout(700); // Livewire roundtrip + chart updateOptions
        await expect(page.locator('.apexcharts-canvas').first()).toBeVisible();
    }
}

test.describe('Interactive dashboards', () => {
    test('buyer dashboard renders charts and reacts to the period picker', async ({ page }) => {
        await login(page, 'buyer@halalbizs.test');
        await page.goto('/account/dashboard');
        await expect(page.getByText(/total spent/i).first()).toBeVisible();
        await assertChartAndPeriodSwitch(page, 'buyer');
    });

    test('seller dashboard renders the revenue chart and reacts to the period picker', async ({ page }) => {
        await login(page, 'cod-seller@halalbizs.test');
        await page.goto('/seller');
        await assertChartAndPeriodSwitch(page, 'seller');
    });

    test('admin dashboard renders charts and reacts to the period picker', async ({ page }) => {
        await adminLogin(page);
        await page.goto('/admin');
        await expect(page.getByText(/GMV/i).first()).toBeVisible();
        await assertChartAndPeriodSwitch(page, 'admin');
    });
});
