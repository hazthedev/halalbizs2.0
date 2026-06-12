import { test, expect } from '@playwright/test';

test('app responds at halalbizs2.0.test', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);
    await page.screenshot({ path: `e2e/screenshots/smoke-${test.info().project.name}.png`, fullPage: true });
});
