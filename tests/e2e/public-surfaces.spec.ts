import { expect, test } from '@playwright/test';

const publicSurfaces = [
    { path: '/', label: 'home discovery' },
    { path: '/events-visual-1', label: 'visual one' },
    { path: '/events-visual-2', label: 'visual two' },
];

test('public assessment surfaces respond, hydrate, and fit the viewport', async ({
    page,
}) => {
    const runtimeFailures: string[] = [];

    page.on('pageerror', (error) =>
        runtimeFailures.push(`pageerror: ${error.message}`),
    );
    page.on('console', (message) => {
        if (message.type() === 'error') {
            runtimeFailures.push(`console: ${message.text()}`);
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 500) {
            runtimeFailures.push(
                `response: ${response.status()} ${response.request().method()} ${response.url()}`,
            );
        }
    });

    for (const surface of publicSurfaces) {
        const response = await page.goto(surface.path, {
            waitUntil: 'networkidle',
        });

        expect(
            response,
            `${surface.label} should return a document response`,
        ).not.toBeNull();
        expect(
            response?.status(),
            `${surface.label} should not redirect or fail`,
        ).toBe(200);
        await expect(page.locator('#app')).toBeVisible();
        await expect(
            page.getByRole('heading', { level: 1 }).first(),
        ).toBeVisible();

        const layout = await page.evaluate(() => ({
            viewport: window.innerWidth,
            width: Math.max(
                document.documentElement.scrollWidth,
                document.body.scrollWidth,
            ),
        }));

        expect(
            layout.width,
            `${surface.label} should not create page-level horizontal overflow`,
        ).toBeLessThanOrEqual(layout.viewport + 1);
    }

    expect(runtimeFailures).toEqual([]);
});
