const { test, expect } = require('@playwright/test');

const viewports = [
    { name: 'small mobile', width: 360, height: 800 },
    { name: 'standard mobile', width: 390, height: 844 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'small desktop', width: 1024, height: 900 },
    { name: 'desktop', width: 1440, height: 1000 },
];

for (const viewport of viewports) {
    test(`public LMS pages render without horizontal overflow on ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });

        for (const path of ['/', '/courses', '/login']) {
            const response = await page.goto(`http://127.0.0.1:8000${path}`, {
                waitUntil: 'domcontentloaded',
            });

            expect(response, `${path} should return a response`).not.toBeNull();
            expect(response.status(), `${path} should load successfully`).toBe(200);
            await expect(page.locator('body')).toBeVisible();

            const overflow = await page.evaluate(() => (
                document.documentElement.scrollWidth - document.documentElement.clientWidth
            ));
            expect(
                overflow,
                `${path} overflows horizontally by ${overflow}px at ${viewport.width}px`,
            ).toBeLessThanOrEqual(1);
        }
    });
}
