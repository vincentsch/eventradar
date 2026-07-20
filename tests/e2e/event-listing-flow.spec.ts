import { expect, test } from '@playwright/test';

test('the seeded event listing loads data and opens a detail through the UI', async ({
    page,
}) => {
    const dataResponsePromise = page.waitForResponse(
        (response) =>
            response.url().includes('/events/data?') &&
            response.request().method() === 'GET',
    );

    await page.goto('/events');
    const dataResponse = await dataResponsePromise;
    const payload = (await dataResponse.json()) as { total: number };

    expect(dataResponse.status()).toBe(200);
    expect(payload.total).toBeGreaterThan(0);

    const firstDetailLink = page.locator('tbody a[href^="/events/"]').first();

    await expect(firstDetailLink).toBeVisible();
    await firstDetailLink.click();
    await expect(page).toHaveURL(/\/events\/[0-9a-f-]+$/);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByRole('link', { name: /back to events/i })).toBeVisible();
});

test('the status control issues a status-scoped listing request', async ({ page }) => {
    await page.goto('/events');

    const filteredResponsePromise = page.waitForResponse((response) => {
        const url = new URL(response.url());

        return (
            url.pathname === '/events/data' &&
            url.searchParams.get('status') === 'published'
        );
    });

    await page.getByLabel('Status').selectOption('published');
    await page.getByRole('button', { name: 'Filter' }).click();

    const filteredResponse = await filteredResponsePromise;
    const filteredPayload = (await filteredResponse.json()) as {
        data: Array<{ status: string }>;
    };

    expect(filteredResponse.status()).toBe(200);
    expect(filteredPayload.data.every((event) => event.status === 'published')).toBe(true);
});
