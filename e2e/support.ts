import { fileURLToPath } from 'url';
import { expect, test, Locator, Page } from '@playwright/test';
import { execSync as execute, execFileSync, ExecSyncOptions } from 'child_process';

export function suiteFixture(action: string, email?: string): Buffer {
    return execFileSync('php', [fileURLToPath(new URL('./fixtures/suite-fixture.php', import.meta.url))], {
        input: JSON.stringify({ action, email, run: process.env.E2E_RUN, password: process.env.E2E_PASSWORD }),
        stdio: ['pipe', 'pipe', 'pipe'],
    });
}

export function execSync(command: string, options?: ExecSyncOptions): Buffer {
    if (command.startsWith('php artisan e2e:cod-fixture')) {
        command += ` --seller=${process.env.E2E_SELLER_EMAIL} --buyer=${process.env.E2E_BUYER_EMAIL}`;
    }
    if (command.startsWith('php artisan e2e:seed-sale')) {
        command += ` --seller=${process.env.E2E_SELLER_EMAIL}`;
    }
    const result = execute(command, { ...options, stdio: 'pipe' }) as Buffer;
    if (command.startsWith('php artisan e2e:user ')) {
        suiteFixture('password', command.split(' ')[3]);
    }
    return result;
}

// Chromium mobile pans the visual viewport after focus; native keyboard
// activation avoids its pointer-coordinate mismatch on long forms.
export async function clickInView(locator: Locator) {
    await expect(locator).toBeVisible();
    await expect(locator).toBeEnabled();
    if (test.info().project.name === 'mobile') await locator.press('Enter');
    else await locator.click();
}

export async function selectPayment(page: Page, value: string) {
    const radio = page.locator(`input[type="radio"][value="${value}"]`);
    await radio.check();
    // The label color comes from the saved server value, after recalculation.
    await expect(page.locator('label').filter({ has: radio })).toHaveClass(/border-emerald/);
    await expect(page.getByRole('button', { name: /place order/i })).toBeEnabled();
}
