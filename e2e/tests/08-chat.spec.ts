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

test.describe('Buyer ↔ seller chat', () => {
    test('buyer messages a store, seller replies, buyer sees the reply', async ({ page }) => {
        const slug = execSync('php artisan e2e:cod-fixture', { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();
        const stamp = `${test.info().project.name}${Date.now()}`;
        const buyerMsg = `Hello, is this ${stamp} in stock?`;
        const sellerMsg = `Yes ${stamp}, ready to ship.`;

        // ===== Buyer opens chat from the PDP =====
        await login(page, 'buyer@halalbizs.test');
        await page.goto(`/p/${slug}`);
        await jsClick(page.getByTestId('pdp-chat'));
        await page.waitForURL('**/account/messages**');

        const composer = page.locator('textarea#chat-body');
        await expect(composer).toBeVisible();
        await composer.fill(buyerMsg);
        await jsClick(page.locator('button[aria-label="Send message"]'));
        // Scope to the message bubble (the list snippet also carries the text).
        await expect(page.locator('.whitespace-pre-line', { hasText: buyerMsg })).toBeVisible({ timeout: 10000 });
        await page.screenshot({ path: `e2e/screenshots/chat-buyer-${test.info().project.name}.png`, fullPage: true });
        await logout(page);

        // ===== Seller replies =====
        await login(page, 'cod-seller@halalbizs.test');
        await page.goto('/seller/messages');
        // Open the conversation carrying the buyer's message.
        await jsClick(page.locator('button[wire\\:click^="openConversation"]'));
        await expect(page.locator('.whitespace-pre-line', { hasText: buyerMsg })).toBeVisible({ timeout: 10000 });

        const sellerComposer = page.locator('textarea#chat-body');
        await sellerComposer.fill(sellerMsg);
        await jsClick(page.locator('button[aria-label="Send message"]'));
        await expect(page.locator('.whitespace-pre-line', { hasText: sellerMsg })).toBeVisible({ timeout: 10000 });
        await page.screenshot({ path: `e2e/screenshots/chat-seller-${test.info().project.name}.png`, fullPage: true });
        await logout(page);

        // ===== Buyer sees the reply =====
        await login(page, 'buyer@halalbizs.test');
        await page.goto('/account/messages');
        await jsClick(page.locator('button[wire\\:click^="openConversation"]'));
        await expect(page.locator('.whitespace-pre-line', { hasText: sellerMsg })).toBeVisible({ timeout: 10000 });
    });
});
