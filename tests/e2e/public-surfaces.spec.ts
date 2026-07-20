import { expect, test } from '@playwright/test';

const publicSurfaces = [
    { path: '/', label: 'home discovery' },
    { path: '/events-visual-1', label: 'visual one' },
    { path: '/events-visual-2', label: 'visual two' },
];

function queryValues(url: string, key: string): string[] {
    return [...new URL(url).searchParams.entries()]
        .filter(
            ([parameter]) =>
                parameter === key || parameter.startsWith(`${key}[`),
        )
        .map(([, value]) => value);
}

test('public assessment surfaces respond, hydrate, and fit the viewport', async ({
    page,
}, testInfo) => {
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

        const shouldOpenFilters =
            surface.path === '/events-visual-2' ||
            (surface.path === '/' &&
                testInfo.project.name === 'chromium-mobile');

        if (shouldOpenFilters) {
            await page.getByRole('button', { name: 'Filters' }).click();
            await expect(page.getByText('Upcoming only')).toBeVisible();
        }

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
    test.setTimeout(60_000);
    test.skip(
        testInfo.project.name !== 'chromium-desktop',
        'The full integration path only needs one browser profile.',
    );

    await page.goto('/', { waitUntil: 'networkidle' });

    const cards = page.locator('[data-event-card]');
    await expect(cards).toHaveCount(18);
    await expect(page.getByText(/Showing 18 of [\d,]+ events/)).toBeVisible();
    await expect(page.getByText(/loaded places/)).toHaveCount(0);

    await cards.first().getByRole('button').click();
    await expect(
        page.getByRole('dialog').getByRole('link', {
            name: 'View full event details',
        }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Close event details' }).click();

    const upcomingOnly = page.getByRole('checkbox', {
        name: 'Upcoming only',
    });
    await expect(upcomingOnly).toBeChecked();
    await upcomingOnly.uncheck();
    await expect(page).toHaveURL(/ongoing=1/);
    await expect(
        page.getByRole('button', { name: 'Clear filters' }),
    ).toHaveCount(0);
    await upcomingOnly.check();
    await expect
        .poll(() => new URL(page.url()).searchParams.get('ongoing'))
        .toBeNull();

    await page.getByRole('button', { name: 'Load more events' }).click();
    await expect(cards).toHaveCount(36);

    const date = page.locator('#discover-date');
    await date.selectOption('custom');
    await page
        .getByRole('group', { name: 'Custom date range' })
        .getByRole('button', { name: 'Clear' })
        .click();
    await page.waitForTimeout(500);
    await expect(cards).toHaveCount(36);

    await date.selectOption('custom');
    await page
        .locator('[data-value="2026-07-27"]:not([data-outside-view])')
        .click();
    await page
        .locator('[data-value="2026-08-08"]:not([data-outside-view])')
        .click();
    await page.getByRole('button', { name: 'Edit dates' }).click();
    const duplicateRangeStart = page.locator(
        '[data-value="2026-07-27"][data-outside-view]',
    );
    await expect(duplicateRangeStart).toHaveCSS('pointer-events', 'none');
    await expect(duplicateRangeStart).toHaveCSS(
        'background-color',
        'rgba(0, 0, 0, 0)',
    );
    await page
        .getByRole('group', { name: 'Custom date range' })
        .getByRole('button', { name: 'Clear' })
        .click();

    await date.selectOption('next-seven-days');
    await expect(page).toHaveURL(/from=/);
    await expect(page).toHaveURL(/to=/);
    await date.selectOption('any');
    await expect
        .poll(() => new URL(page.url()).searchParams.get('from'))
        .toBeNull();

    const search = page.locator('#discover-search');
    await search.fill('workshop');
    await expect(page).toHaveURL(/q=workshop/);
    await expect(search).toBeFocused();
    await search.fill('');
    await expect
        .poll(() => new URL(page.url()).searchParams.get('q'))
        .toBeNull();

    const category = page.locator('#discover-category');
    await category.click();
    const categorySearch = page.getByRole('combobox', {
        name: 'Search categories',
    });
    await categorySearch.fill('work');
    await expect(
        page.getByRole('option', { name: 'Workshop', exact: true }),
    ).toBeVisible();
    await categorySearch.fill('');
    await page.getByRole('option', { name: 'Concert', exact: true }).click();
    await page.getByRole('option', { name: 'Conference', exact: true }).click();
    await expect
        .poll(() => queryValues(page.url(), 'type'))
        .toEqual(['concert', 'conference']);
    await page.keyboard.press('Escape');
    const selectionBar = page.locator('[data-filter-selection-bar]');
    await expect(
        selectionBar.getByRole('button', { name: 'Remove Concert' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Remove Conference' }).click();
    await expect
        .poll(() => queryValues(page.url(), 'type'))
        .toEqual(['concert']);
    await expect(
        page.getByRole('button', { name: 'Apply filters' }),
    ).toHaveCount(0);
    await expect(cards.first()).toBeVisible();
    await expect(cards).toHaveCount(18);

    await page.getByRole('link', { name: 'Map', exact: true }).click();
    await expect(page).toHaveURL(/\/events-visual-2\?/);
    await expect
        .poll(() => queryValues(page.url(), 'type'))
        .toEqual(['concert']);
    await expect(
        page.getByRole('region', {
            name: 'Interactive map of event locations',
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Search this area' }),
    ).toBeVisible({ timeout: 15_000 });
    await page.getByRole('button', { name: /Filters/ }).click();
    await expect(
        page.getByRole('button', { name: 'Remove Concert' }),
    ).toBeVisible();

    const agendaDays = await page
        .locator('[data-agenda-day]')
        .evaluateAll((days) =>
            days.map((day) => ({
                key: day.getAttribute('data-agenda-day') ?? '',
                times: Array.from(
                    day.querySelectorAll('[data-agenda-time]'),
                ).map((event) => event.getAttribute('data-agenda-time') ?? ''),
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
