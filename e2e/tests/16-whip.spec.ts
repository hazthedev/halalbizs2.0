import { fileURLToPath } from 'url';
import { expect, test } from '@playwright/test';
import { execFileSync } from 'child_process';
import { randomBytes } from 'crypto';

const root = process.env.HB_E2E_APP_ROOT || process.cwd();
const helper = fileURLToPath(new URL('../fixtures/whip-fixture.php', import.meta.url));

test('WHIP discovery, import, tracker, reminders and buyer details', async ({ page }) => {
    const id = `e2e-${Date.now()}-${randomBytes(5).toString('hex')}`;
    const password = randomBytes(24).toString('hex');
    const run = (action: string) => {
        const output = execFileSync('php', [helper], {
            cwd: root,
            input: JSON.stringify({ id, password, action }),
            encoding: 'utf8',
        });
        return output ? JSON.parse(output) : {};
    };

    try {
        const { category } = run('setup');
        await page.goto('/login');
        await page.locator('input[type="email"]').fill(`${id}@halalbizs.test`);
        await page.locator('input[type="password"]').fill(password);
        await page.getByRole('button', { name: /log in/i }).click();
        await page.waitForURL('**/');

        await page.goto('/seller');
        await expect(page.getByRole('heading', { name: 'My WHIP applications' })).toBeVisible();
        await expect(page.getByRole('main').getByText(id, { exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Get certified' })).toHaveAttribute('href', /\/e2e-whip\/apply$/);
        await page.context().route('**/e2e-whip/apply', route => route.fulfill({ body: '<h1>Local certification application</h1>', contentType: 'text/html' }));
        const [application] = await Promise.all([page.waitForEvent('popup'), page.getByRole('link', { name: 'Get certified' }).click()]);
        await expect(application.getByRole('heading', { name: 'Local certification application' })).toBeVisible();
        await application.close();
        await page.getByRole('button', { name: 'Follow application' }).click();
        await expect(page.getByText('Following', { exact: true })).toBeVisible();
        await expect(page.getByText('Submitted', { exact: true })).toBeVisible();
        run('certify');
        await page.getByRole('button', { name: 'Refresh status' }).click();
        await expect(page.getByText('Certified', { exact: true })).toBeVisible();
        await expect(page.getByText('Following', { exact: true })).toBeVisible();

        await page.goto('/seller/certificates');
        await expect(page.getByRole('link', { name: 'Get certified' })).toBeVisible();
        await page.getByRole('button', { name: 'Find my certificates' }).click();
        await expect(page.getByText(id.toUpperCase(), { exact: true })).toBeVisible();
        await expect(page.getByText('E2E sauces')).toBeVisible();
        await page.getByRole('button', { name: 'Link certificate' }).click();
        await expect(page.getByRole('heading', { name: 'Import certified products' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Get certified' })).toHaveCount(0);
        await page.locator('#importCategoryId').selectOption(String(category));
        await page.getByRole('button', { name: 'Import product drafts' }).click();
        await expect(page.getByText(/1 product drafts imported/i)).toBeVisible();
        await page.getByRole('link', { name: 'Review my product drafts' }).click();
        await expect(page.getByText(`E2E Sauce ${id}`).first()).toBeVisible();

        await page.route('**/e2e-whip/renew', route => route.fulfill({ body: '<h1>Local certificate renewal</h1>', contentType: 'text/html' }));
        run('reminder');
        await page.goto('/seller/notifications');
        await page.getByRole('button', { name: new RegExp(`Certificate ${id.toUpperCase()} expires`) }).click();
        await expect(page).toHaveURL(/\/e2e-whip\/renew$/);
        const { slug } = run('buyer');
        await page.context().clearCookies();
        await page.goto(`/p/${slug}`);
        await expect(page.getByText('Halal certificate verified')).toBeVisible();
        await expect(page.locator('p').filter({ hasText: 'E2E Test CB' })).toBeVisible();
        await expect(page.getByText(/valid to/i).first()).toBeVisible();
        await expect(page.getByText(id.toUpperCase(), { exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Read the record' })).toBeVisible();

        run('unverify');
        await page.goto('/login');
        await page.locator('input[type="email"]').fill(`${id}@halalbizs.test`);
        await page.locator('input[type="password"]').fill(password);
        await page.getByRole('button', { name: /log in/i }).click();
        await page.waitForURL('**/');
        await page.goto('/seller/certificates');
        await page.getByRole('button', { name: 'Find my certificates' }).click();
        await expect(page.getByRole('alert')).toContainText('SSM number needs to be checked');
    } finally {
        run('cleanup');
    }
});
