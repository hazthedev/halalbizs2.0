import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test('iPay88 checkout reaches the gateway bridge', async ({ page }) => {
    const slug = execSync('php artisan e2e:cod-fixture', { cwd: process.cwd() }).toString().trim().split('\n').pop()!.trim();

    // The bridge auto-submits to the real sandbox — stub the gateway and
    // capture the POST so we can assert the submitted fields.
    let gatewayPost: string | null = null;
    await page.route('**/*payment/entry.asp', (route) => {
        gatewayPost = route.request().postData();
        return route.fulfill({ contentType: 'text/html', body: '<h1>Gateway stub</h1>' });
    });

    await page.goto('/login');
    await page.fill('input[type="email"]', 'buyer@halalbizs.test');
    await page.fill('input[type="password"]', 'password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/');

    await page.goto(`/p/${slug}`);
    await page.getByTestId('pdp-add-to-cart').locator('visible=true').first().click();
    await expect(page.getByText('Added to cart').first()).toBeVisible();

    await page.goto('/checkout');
    await page.locator('input[type="radio"][value="ipay88"]').check();
    await page.getByRole('button', { name: /place order/i }).click();

    await page.waitForURL('**/pay/**', { timeout: 20000 });
    await expect(page.getByText('Gateway stub')).toBeVisible({ timeout: 15000 });

    expect(gatewayPost).toBeTruthy();
    expect(gatewayPost!).toContain('MerchantCode=');
    expect(gatewayPost!).toContain('RefNo=MP');
    expect(gatewayPost!).toContain('SignatureType=SHA256');
    expect(gatewayPost!).toContain('Signature=');
    expect(gatewayPost!).toContain('Currency=MYR');
});
