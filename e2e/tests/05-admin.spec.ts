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
    await page.waitForURL(/\/(two-factor-challenge)?(\?.*)?$/, { timeout: 15000 });

    // Admins carry 2FA — mint a code via artisan and pass the challenge.
    if (page.url().includes('two-factor-challenge')) {
        const code = execSync(`php artisan e2e:otp ${email}`, { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();
        await page.fill('input[wire\\:model="code"]', code);
        await page.getByRole('button', { name: /verify|continue|confirm/i }).click();
        await page.waitForURL('**/');
    }
}

test.describe('M7 admin journeys', () => {
    test('dashboard, approve seller, toggle home section, orders oversight', async ({ page }) => {
        page.on('dialog', (dialog) => dialog.accept());

        const stamp = `${test.info().project.name}${Date.now()}`;
        const applicantEmail = `admin-e2e-${stamp}@halalbizs.test`;
        const out = execSync(`php artisan e2e:user ${applicantEmail} --pending-store`, { cwd: process.cwd() }).toString();
        const shopName = out.trim().split('\n')[0].trim();

        await login(page, 'admin@halalbizs.test');

        // --- Dashboard ---
        await page.goto('/admin');
        await expect(page.getByText(/GMV/i).first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m7-dashboard-${test.info().project.name}.png`, fullPage: true });

        // --- Approve the pending seller application ---
        await page.goto('/admin/sellers/applications');
        // td-scoped: the notification bell also carries the shop name (hidden).
        await expect(page.locator('td', { hasText: shopName }).first()).toBeVisible({ timeout: 15000 });
        // Queue is oldest-first and other runs may have left applications —
        // operate strictly on OUR application's row.
        await jsClick(page.locator('tr', { hasText: shopName }).getByRole('button', { name: /review/i }));
        await page.waitForTimeout(500);
        await page.screenshot({ path: `e2e/screenshots/m7-application-${test.info().project.name}.png`, fullPage: true });
        await jsClick(page.getByRole('button', { name: /approve store/i }));

        // Ground truth: poll the DB until the approval lands (sync-queue mail
        // makes the roundtrip slow), then confirm the queue UI after a reload.
        await expect
            .poll(
                () => execSync(`php artisan e2e:store-status ${applicantEmail}`, { cwd: process.cwd() }).toString().trim(),
                { timeout: 30000 },
            )
            .toBe('approved');

        await page.reload();
        await expect(page.locator('td', { hasText: shopName })).not.toBeVisible();

        // --- Toggle a home section off and verify the storefront changes ---
        await page.goto('/admin/content/home-sections');
        const popularRow = page.locator('[wire\\:key]').filter({ hasText: 'Popular now' }).first();
        await expect(popularRow).toBeVisible();
        await jsClick(popularRow.getByRole('switch'));
        await page.waitForTimeout(800);

        await page.goto('/');
        await expect(page.getByText('Popular now')).not.toBeVisible();

        await page.goto('/admin/content/home-sections');
        await jsClick(page.locator('[wire\\:key]').filter({ hasText: 'Popular now' }).first().getByRole('switch'));
        await page.waitForTimeout(800);
        await page.goto('/');
        await expect(page.getByText('Popular now').first()).toBeVisible();

        // --- Orders oversight + payments reconciliation render with data ---
        await page.goto('/admin/orders');
        await expect(page.locator('tbody tr').first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m7-orders-${test.info().project.name}.png`, fullPage: true });

        await page.goto('/admin/payments');
        await expect(page.locator('tbody tr').first()).toBeVisible();

        // --- Commission tester resolves a rate ---
        await page.goto('/admin/finance/commission');
        await expect(page.getByText(/global default|global rate/i).first()).toBeVisible();
        await page.screenshot({ path: `e2e/screenshots/m7-commission-${test.info().project.name}.png`, fullPage: true });
    });
});
