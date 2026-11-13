import { fileURLToPath } from 'url';
import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'child_process';
import { randomBytes } from 'crypto';

const helper = fileURLToPath(new URL('../fixtures/cms-fixture.php', import.meta.url));
const run = `e2e-${Date.now()}-${randomBytes(4).toString('hex')}`;
const password = randomBytes(24).toString('hex');
const fixture = (action: string) => execFileSync('php', [helper, action], {
    cwd: process.cwd(), input: JSON.stringify({ run, password }), stdio: ['pipe', 'pipe', 'pipe'],
});

async function login(page: Page, role: string) {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.locator('input[type=email]').fill(`${run}-${role}@halalbizs.test`);
    await page.locator('input[type=password]').fill(password);
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL(url => !url.pathname.includes('login'));
}

async function body(page: Page, text: string) {
    const editor = page.locator('[data-rich-editor="body.en"] [contenteditable=true]');
    await editor.fill(text);
}

test.describe('CMS drafts, history and scheduled sections', () => {
    test.beforeAll(() => fixture('create'));
    test.afterAll(() => fixture('cleanup'));

    test('editor previews a draft, publisher publishes and restores history', async ({ page }) => {
        await login(page, 'editor');
        await page.goto('/admin/content/pages');
        await page.getByRole('button', { name: 'Add page' }).click();
        await page.locator('[wire\\:model="slug"]').fill(run);
        await page.locator('[wire\\:model="title.en"]').fill(`First ${run}`);
        await body(page, `First body ${run}`);
        const saveStarted = Date.now();
        await page.getByRole('button', { name: 'Create page' }).click();
        const row = page.locator('tr').filter({ hasText: run });
        await expect(row.getByText('Draft')).toBeVisible();
        test.info().annotations.push({ type: 'timing', description: `CMS draft save ${Date.now() - saveStarted} ms` });
        await expect(row.getByRole('button', { name: 'Publish', exact: true })).toHaveCount(0);
        const publicResponse = await page.goto(`/page/${run}`);
        expect(publicResponse?.status()).toBe(404);
        await page.goto('/admin/content/pages');
        const [preview] = await Promise.all([
            page.waitForEvent('popup'),
            row.getByRole('link', { name: 'Preview', exact: true }).click(),
        ]);
        await expect(preview.frameLocator('iframe[data-preview="web"]').getByText(`First body ${run}`)).toBeVisible();
        await preview.close();

        await login(page, 'publisher');
        await page.goto('/admin/content/pages');
        await row.getByRole('button', { name: 'Publish', exact: true }).click();
        await expect(row.getByText('Draft')).toHaveCount(0);
        await page.goto(`/page/${run}`);
        await expect(page.getByText(`First body ${run}`)).toBeVisible();
        await page.goto('/admin/content/pages');
        await row.getByRole('button', { name: 'Edit', exact: true }).click();
        await page.locator('[wire\\:model="title.en"]').fill(`Second ${run}`);
        await body(page, `Second body ${run}`);
        await page.getByRole('button', { name: 'Save page' }).click();
        await row.getByRole('button', { name: 'Publish', exact: true }).click();
        await expect(row.getByText('Draft')).toHaveCount(0);
        await row.getByRole('link', { name: 'History' }).click();
        await expect(page.getByText('Live now')).toBeVisible();
        await page.getByRole('button', { name: 'Restore as draft' }).first().click();
        await expect(page.getByText(/This item has a draft waiting/)).toBeVisible();
        await page.goto(`/page/${run}`);
        await expect(page.getByText(`Second body ${run}`)).toBeVisible();
        await page.goto('/admin/content/pages');
        await expect(row.getByText('Draft')).toBeVisible();
        await row.getByRole('button', { name: 'Publish', exact: true }).click();
        await expect(row.getByText('Draft')).toHaveCount(0);
        await page.goto(`/page/${run}`);
        await expect(page.getByText(`First body ${run}`)).toBeVisible();
    });

    test('scheduled sections cover notices, tiles, picked items and channels', async ({ page }) => {
        test.setTimeout(180_000);
        page.on('dialog', dialog => dialog.accept());
        await login(page, 'publisher');
        await page.goto('/admin/content/home-sections');
        await page.getByRole('button', { name: 'New scheduled layout' }).click();
        await page.locator('[wire\\:model="layoutName"]').fill(run);
        await page.locator('[wire\\:model="layoutStarts"]').fill('2035-01-01T10:00');
        await page.locator('[wire\\:model="layoutEnds"]').fill('2035-01-02T10:00');
        await page.getByRole('button', { name: 'Save layout' }).click();
        await expect(page.locator('#layout-choice')).toContainText(run);
        const layoutId = await page.locator('#layout-choice').inputValue();
        expect(layoutId).not.toBe('');

        // The new layout starts as a copy. Remove only its copies to make room
        // for the four repeatable types under the ten-section limit.
        const sections = page.locator('li[wire\\:key^="section-"]');
        while (await sections.count()) {
            const before = await sections.count();
            await sections.first().getByRole('button', { name: 'Remove', exact: true }).click();
            await expect(sections).toHaveCount(before - 1);
        }
        const add = async (type: string) => {
            await page.locator('#add-type').selectOption(type);
            await page.getByRole('button', { name: 'Add section' }).click();
            const form = page.locator('li[wire\\:key^="section-"] form[wire\\:submit="save"]');
            await expect(form).toBeVisible();
            return form;
        };
        const saveAndPublish = async (form: ReturnType<Page['locator']>) => {
            const key = await form.locator('xpath=..').getAttribute('wire:key');
            const section = page.locator('li[wire\\:key]').filter({ has: page.locator(`[wire\\:submit="save"]`) });
            expect(key).toBeTruthy();
            const saved = page.locator(`li[wire\\:key="${key}"]`);
            await form.getByRole('button', { name: 'Save section' }).click();
            await expect(form).toHaveCount(0);
            await expect(section).toHaveCount(0);
            await saved.getByRole('button', { name: 'Publish', exact: true }).click();
            await expect(saved.getByText('Draft')).toHaveCount(0);
        };

        let form = await add('notice');
        await form.locator('[wire\\:model="noticeText.en"]').fill(`Notice ${run}`);
        await form.locator('#section-show-on').selectOption('web');
        await saveAndPublish(form);

        form = await add('picked_products');
        await form.locator('[wire\\:model="title.en"]').fill(`Products ${run}`);
        await form.locator('[wire\\:model\\.live\\.debounce\\.300ms="pickSearch"]').fill(`${run} product`);
        await form.getByRole('button', { name: `Add ${run} product` }).click();
        await saveAndPublish(form);

        form = await add('picked_stores');
        await form.locator('[wire\\:model="title.en"]').fill(`Stores ${run}`);
        await form.locator('[wire\\:model\\.live\\.debounce\\.300ms="pickSearch"]').fill(`${run} store`);
        await form.getByRole('button', { name: `Add ${run} store` }).click();
        await form.locator('#section-show-on').selectOption('app');
        await saveAndPublish(form);

        form = await add('picture_tiles');
        const png = await page.evaluate(() => {
            const canvas = document.createElement('canvas');
            canvas.width = 400; canvas.height = 300;
            return canvas.toDataURL('image/png').split(',')[1];
        });
        for (const slot of [1, 2]) {
            await form.locator(`#tile-image-${slot}`).setInputFiles({ name: `tile-${slot}.png`, mimeType: 'image/png', buffer: Buffer.from(png, 'base64') });
            await expect(form.locator(`#tile-image-${slot}`).locator('..').locator('..').locator('img')).toBeVisible();
            await form.locator(`[wire\\:model="tileLabels.${slot}.en"]`).fill(`Tile ${slot} ${run}`);
        }
        await form.locator('[wire\\:model="tileLinks.1"]').fill('/search');
        await saveAndPublish(form);

        const [preview] = await Promise.all([
            page.waitForEvent('popup'),
            page.getByRole('link', { name: 'Preview this layout' }).click(),
        ]);
        const web = preview.frameLocator('iframe[data-preview="web"]');
        await expect(web.getByText(`Notice ${run}`)).toBeVisible();
        await expect(web.getByText(`${run} product`)).toBeVisible();
        await expect(web.getByText(`Stores ${run}`)).toHaveCount(0);
        await expect(web.getByRole('link', { name: `Tile 1 ${run}` })).toHaveAttribute('href', '/search');
        await expect(web.getByText(`Tile 2 ${run}`)).toBeVisible();
        await preview.close();

        await page.goto('/');
        await expect(page.getByText(`Notice ${run}`)).toHaveCount(0);
        await page.goto('/admin/content/home-sections');
        await page.locator('#layout-choice').selectOption(layoutId);
        await expect(page.getByRole('button', { name: 'Change name or dates' })).toBeVisible();
        await page.locator('#layout-choice').selectOption('');
        await expect(page.getByRole('button', { name: 'Change name or dates' })).toHaveCount(0);
        await page.locator('#layout-choice').selectOption(layoutId);
        await page.getByRole('button', { name: 'Change name or dates' }).click();
        const localTime = (offset: number) => {
            const date = new Date(Date.now() + offset);
            const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Kuala_Lumpur', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(date);
            const part = (type: string) => parts.find(item => item.type === type)!.value;
            return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
        };
        await page.locator('[wire\\:model="layoutStarts"]').fill(localTime(-3_600_000));
        await page.locator('[wire\\:model="layoutEnds"]').fill(localTime(3_600_000));
        await page.getByRole('button', { name: 'Save layout' }).click();
        await expect(page.getByRole('button', { name: 'Save layout' })).toHaveCount(0);
        await page.goto('/');
        await expect(page.getByText(`Notice ${run}`)).toBeVisible();
        const api = await page.request.get('/api/v1/home');
        expect(api.ok()).toBeTruthy();
        const blocks = (await api.json()).data.blocks;
        expect(blocks.some((block: { type: string; title?: string }) => block.type === 'picked_stores' && block.title === `Stores ${run}`)).toBeTruthy();
        expect(blocks.some((block: { type: string; text?: string }) => block.type === 'notice' && block.text === `Notice ${run}`)).toBeFalsy();
        expect(blocks.some((block: { type: string; title?: string }) => block.type === 'picked_products' && block.title === `Products ${run}`)).toBeTruthy();
        expect(blocks.some((block: { type: string }) => block.type === 'picture_tiles')).toBeTruthy();
        await login(page, 'buyer');
        const response = await page.goto('/admin/content/pages');
        expect(response?.status()).toBe(403);
    });
});
