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

async function adminLogin(page: Page) {
    await page.goto('/login');
    await page.fill('input[type="email"]', 'admin@halalbizs.test');
    await page.fill('input[type="password"]', 'password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/two-factor-challenge', { timeout: 15000 });
    const code = execSync('php artisan e2e:otp admin@halalbizs.test', { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();
    await page.fill('input[name="code"]', code);
    await page.getByRole('button', { name: /verify/i }).click();
    await page.waitForURL((url) => !url.pathname.includes('two-factor-challenge'), { timeout: 15000 });
}

async function logout(page: Page) {
    await page.evaluate(() => {
        (document.querySelector('form[action*="logout"]') as HTMLFormElement | null)?.submit();
    });
    await page.waitForTimeout(800);
}

test.describe('Help center, tickets and seasonal theming', () => {
    test('help articles + search', async ({ page }) => {
        await page.goto('/help');
        const firstArticle = page.locator('a[href*="/help/article/"]').first();
        await expect(firstArticle).toBeVisible();
        const title = (await firstArticle.textContent())!.trim();
        const term = title.split(/\s+/).find((w) => w.length >= 4) ?? title.slice(0, 4);

        await page.fill('#help-search', term);
        await page.waitForTimeout(600); // debounce
        await expect(page.locator('a[href*="/help/article/"]').first()).toBeVisible();

        await jsClick(page.locator('a[href*="/help/article/"]').first());
        await page.waitForURL('**/help/article/**');
        await expect(page.locator('h1, h2').first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/help-${test.info().project.name}.png`, fullPage: true });
    });

    test('buyer raises a ticket, admin replies, buyer sees it', async ({ page }) => {
        const stamp = `${test.info().project.name}${Date.now()}`;
        const subject = `Order help ${stamp}`;
        const adminReply = `We can help with ${stamp}.`;

        await login(page, 'buyer@halalbizs.test');
        await page.goto('/support');
        const newTicket = page.getByRole('button', { name: /new ticket/i });
        if (await newTicket.count()) await jsClick(newTicket);
        await page.locator('input[wire\\:model="subject"]').fill(subject);
        await page.locator('#ticket-body').fill('I need help with a recent order, please advise.');
        await jsClick(page.getByRole('button', { name: /create ticket/i }));
        await expect(page.getByText(subject).first()).toBeVisible({ timeout: 10000 });
        await logout(page);

        // Admin answers.
        await adminLogin(page);
        await page.goto('/admin/support/tickets');
        await jsClick(page.locator('button[wire\\:click^="select"]', { hasText: subject }).first());
        await expect(page.getByText(subject).first()).toBeVisible();
        await page.locator('#admin-reply').fill(adminReply);
        await jsClick(page.getByRole('button', { name: /send reply/i }));
        await expect(page.getByText(adminReply).first()).toBeVisible({ timeout: 10000 });
        await logout(page);

        // Buyer sees the reply.
        await login(page, 'buyer@halalbizs.test');
        await page.goto('/support');
        await jsClick(page.locator('button[wire\\:click^="select"]', { hasText: subject }).first());
        await expect(page.getByText(adminReply).first()).toBeVisible({ timeout: 10000 });
    });

    test('admin announcement bar appears and disappears', async ({ page }) => {
        const stamp = `${test.info().project.name}${Date.now()}`;
        const text = `Raya sale ${stamp}`;

        await adminLogin(page);
        await page.goto('/admin/content/theme');

        await page.locator('input[wire\\:model="announcementTextEn"]').fill(text);
        await page.locator('input[wire\\:model="announcementEnabled"]').check();
        await jsClick(page.getByRole('button', { name: /save theme/i }));
        await page.waitForTimeout(800);

        await page.goto('/');
        await expect(page.getByText(text).first()).toBeVisible({ timeout: 10000 });
        await page.screenshot({ path: `e2e/screenshots/theme-on-${test.info().project.name}.png` });

        await page.goto('/admin/content/theme');
        await page.locator('input[wire\\:model="announcementEnabled"]').uncheck();
        await jsClick(page.getByRole('button', { name: /save theme/i }));
        await page.waitForTimeout(800);

        await page.goto('/');
        await expect(page.getByText(text)).toHaveCount(0);
    });
});
