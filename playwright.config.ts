import { fileURLToPath } from 'url';
import { defineConfig, devices } from '@playwright/test';
import { randomBytes } from 'crypto';

const baseURL = process.env.E2E_BASE_URL ?? 'http://halalbizs2.0.test';
const target = new URL(baseURL);
if (target.hostname !== 'halalbizs2.0.test' || target.protocol !== 'http:' || target.username || target.password) {
    throw new Error('E2E requires the local halalbizs2.0.test app.');
}

process.env.E2E_RUN ??= `e2e-${Date.now()}`;
process.env.E2E_PASSWORD ??= randomBytes(24).toString('hex');
process.env.E2E_BUYER_EMAIL = `${process.env.E2E_RUN}-buyer@halalbizs.test`;
process.env.E2E_ADMIN_EMAIL = `${process.env.E2E_RUN}-admin@halalbizs.test`;
process.env.E2E_SELLER_EMAIL = `${process.env.E2E_RUN}-seller@halalbizs.test`;

export default defineConfig({
    testDir: './e2e/tests',
    globalSetup: fileURLToPath(new URL('./e2e/setup.ts', import.meta.url)),
    outputDir: './e2e/test-results',
    fullyParallel: false,
    workers: 1,
    // Long Livewire pages (esp. the seller order-detail journey) intermittently
    // trip Playwright's actionability/stability check under full-suite load —
    // the underlying flows pass on retry and are covered deterministically by
    // the Pest suite. A real failure still fails every attempt.
    retries: 2,
    timeout: 60_000,
    use: {
        baseURL,
        screenshot: 'only-on-failure',
        actionTimeout: 15_000,
        // Login requests contain runtime secrets; keep them out of trace files.
        trace: 'off',
        launchOptions: {
            // Herd's local DNS only resolves exact site names; store
            // subdomains resolve in-browser via Chromium host mapping.
            args: ['--host-resolver-rules=MAP *.halalbizs2.0.test 127.0.0.1'],
        },
    },
    projects: [
        {
            name: 'desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
        },
        {
            name: 'mobile',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 390, height: 844 },
                isMobile: true,
                hasTouch: true,
            },
        },
    ],
});
