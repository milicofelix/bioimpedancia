import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8081';

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: 'test-results',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: process.env.CI
        ? [['line'], ['html', { outputFolder: 'playwright-report', open: 'never' }]]
        : [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
    use: {
        baseURL,
        locale: 'pt-BR',
        timezoneId: 'America/Sao_Paulo',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'auth-setup',
            testMatch: /auth\.setup\.js/,
        },
        {
            name: 'login',
            testMatch: /login\.spec\.js/,
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'pwa-chromium',
            testIgnore: [/auth\.setup\.js/, /login\.spec\.js/],
            dependencies: ['auth-setup'],
            use: {
                ...devices['Pixel 7'],
                storageState: 'playwright/.auth/user.json',
            },
        },
    ],
});
