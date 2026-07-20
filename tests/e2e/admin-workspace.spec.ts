import { expect, test } from '@playwright/test';

test.beforeEach(async ({}, testInfo) => {
    test.skip(
        testInfo.project.name !== 'chromium-desktop',
        'The operational workspace flow is covered on desktop.',
    );
});

test('the reviewer browses, paginates, filters, and inspects database events', async ({
    context,
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('reviewer@example.test');
    await page.locator('input[name="password"]').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/admin$/);
    await expect(
        page.getByRole('heading', { name: 'Event catalogue' }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Events', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/events$/);
    await expect(page.locator('tbody tr')).toHaveCount(50);
    await expect(page.getByText(/Showing 1–50 of/)).toBeVisible();

    await page.getByRole('link', { name: 'Next' }).click();
    await expect(page).toHaveURL(/page=2/);
    await expect(page.getByText('Page 2 of', { exact: false })).toBeVisible();

    await page.getByLabel('Status').selectOption('cancelled');
    await expect(page).toHaveURL(/status=cancelled/);
    await expect(
        page.locator('tbody').getByText('cancelled').first(),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Inspect' }).first().click();
    await expect(page).toHaveURL(/\/admin\/events\/[0-9a-f-]+$/);
    await expect(
        page.getByRole('heading', { name: 'Normalized event' }),
    ).toBeVisible();
    await expect(page.getByText('Raw provenance').locator('..')).toContainText(
        /[\d,]+ bytes/,
    );

    await page.goto('/admin/events?status=published');
    await page.getByRole('link', { name: 'Inspect' }).first().click();
    const eventId = page.url().split('/').at(-1);
    expect(eventId).toBeTruthy();
    await context.clearCookies();
    await page.goto(`/events/${eventId}`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('a[href="/admin/events"]')).toHaveCount(0);
});
