import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

async function passwordStep(page: Page, email: string) {
    await page.goto('/login');
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/two-factor-challenge', { timeout: 15000 });
}

test.describe('Two-factor login challenge', () => {
    test('a 2FA user must pass the email-code challenge', async ({ page }) => {
        const email = `tfa-${test.info().project.name}${Date.now()}@halalbizs.test`;
        execSync(`php artisan e2e:user ${email} --two-factor`, { cwd: process.cwd() });

        await passwordStep(page, email);
        await expect(page.locator('input[name="code"]')).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/2fa-challenge-${test.info().project.name}.png` });

        // Wrong code is rejected.
        await page.fill('input[name="code"]', '000000');
        await page.getByRole('button', { name: /verify/i }).click();
        await expect(page.getByText(/code/i).filter({ hasText: /isn.t right|no longer works|try again/i }).first()).toBeVisible({ timeout: 10000 });

        // Correct code logs in.
        const code = execSync(`php artisan e2e:otp ${email}`, { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();
        await page.fill('input[name="code"]', code);
        await page.getByRole('button', { name: /verify/i }).click();
        await page.waitForURL((url) => !url.pathname.includes('two-factor-challenge'), { timeout: 15000 });

        // Authenticated: the account page is reachable.
        await page.goto('/account');
        await expect(page.getByRole('heading', { name: 'Profile' })).toBeVisible();
    });

    test('the seeded admin passes its 2FA challenge into the panel', async ({ page }) => {
        await passwordStep(page, 'admin@halalbizs.test');
        const code = execSync('php artisan e2e:otp admin@halalbizs.test', { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();
        await page.fill('input[name="code"]', code);
        await page.getByRole('button', { name: /verify/i }).click();
        await page.waitForURL((url) => !url.pathname.includes('two-factor-challenge'), { timeout: 15000 });

        await page.goto('/admin');
        await expect(page.getByText(/GMV/i).first()).toBeVisible({ timeout: 10000 });
    });
});
