import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './e2e/tests',
    outputDir: './e2e/test-results',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    use: {
        baseURL: 'http://halalbizs2.0.test',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
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
