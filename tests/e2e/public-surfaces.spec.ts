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

test('discovery filters, pagination, details, and map work with live data', async ({
    page,
}, testInfo) => {
    test.skip(
        testInfo.project.name !== 'chromium-desktop',
        'The full integration path only needs one browser profile.',
    );

    await page.goto('/', { waitUntil: 'networkidle' });

    const cards = page.locator('[data-event-card]');
    await expect(cards).toHaveCount(18);

    await cards.first().getByRole('button').click();
    await expect(
        page.getByRole('dialog').getByRole('link', {
            name: 'View full event details',
        }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Close event details' }).click();

    await page.getByRole('button', { name: 'Load more events' }).click();
    await expect(cards).toHaveCount(36);

    const category = page.locator('#discover-category');
    const selectedType = await category.locator('option').nth(1).getAttribute('value');
    expect(selectedType).toBeTruthy();
    await category.selectOption(selectedType!);
    await page.getByRole('button', { name: 'Apply filters' }).click();

    await expect(page).toHaveURL(new RegExp(`type=${selectedType}`));
    await expect(cards.first()).toBeVisible();
    await expect(cards).toHaveCount(18);

    await page.getByRole('link', { name: 'Map', exact: true }).click();
    await expect(page).toHaveURL(
        new RegExp(`/events-visual-2\\?.*type=${selectedType}`),
    );
    await expect(
        page.getByRole('region', {
            name: 'Interactive map of event locations',
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Search this area' }),
    ).toBeVisible({ timeout: 15_000 });

    const agendaDays = await page.locator('[data-agenda-day]').evaluateAll((days) =>
        days.map((day) => ({
            key: day.getAttribute('data-agenda-day') ?? '',
            times: Array.from(day.querySelectorAll('[data-agenda-time]')).map(
                (event) => event.getAttribute('data-agenda-time') ?? '',
            ),
        })),
    );
    const dayKeys = agendaDays.map((day) => day.key);
    expect(dayKeys).toEqual([...new Set(dayKeys)].sort());

    for (const day of agendaDays) {
        expect(day.times).toEqual([...day.times].sort());
    }

    await page.getByRole('button', { name: 'Search this area' }).click();
    await expect(page).toHaveURL(/north=/);
    await expect(page).toHaveURL(/south=/);
    await expect(page).toHaveURL(/east=/);
    await expect(page).toHaveURL(/west=/);
});
