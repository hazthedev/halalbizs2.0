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

test.describe('Product video and notification bell', () => {
    test('a product video renders on the PDP', async ({ page }) => {
        const slug = execSync('php artisan e2e:cod-fixture --with-video', { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();

        await page.goto(`/p/${slug}`);
        await expect(page.locator('video')).toHaveCount(1);
        await page.screenshot({ path: `e2e/screenshots/video-pdp-${test.info().project.name}.png`, fullPage: true });
    });

    test('the seller bell lights up after a buyer message', async ({ page }) => {
        const slug = execSync('php artisan e2e:cod-fixture', { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();
        const msg = `Question ${test.info().project.name}${Date.now()}`;

        await login(page, 'buyer@halalbizs.test');
        await page.goto(`/p/${slug}`);
        await jsClick(page.getByTestId('pdp-chat'));
        await page.waitForURL('**/account/messages**');
        await page.locator('textarea#chat-body').fill(msg);
        await jsClick(page.locator('button[aria-label="Send message"]'));
        await expect(page.locator('.whitespace-pre-line', { hasText: msg })).toBeVisible({ timeout: 10000 });
        await logout(page);

        await login(page, 'cod-seller@halalbizs.test');
        await page.goto('/seller');
        await expect(page.getByTestId('bell-unread-dot')).toBeVisible({ timeout: 10000 });
        await page.screenshot({ path: `e2e/screenshots/bell-${test.info().project.name}.png` });
    });
});
