import { workspace } from './fixtures.js';

export async function mockOperationalWorkspace(page, overrides = {}) {
    const payload = {
        ...workspace,
        ...overrides,
        clinic: { ...workspace.clinic, ...(overrides.clinic ?? {}) },
    };

    await page.route('**/bioimpedance/operational', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payload) });
    });
}

export async function openOperationalApp(page, overrides = {}) {
    await mockOperationalWorkspace(page, overrides);
    await page.goto('/app#clientes');
    await page.getByRole('heading', { name: 'Clientes' }).waitFor();
}

export async function openFixtureClient(page) {
    await page.getByRole('button', { name: /Abrir cliente Cliente E2E Vita Scan/ }).click();
    await page.getByRole('heading', { name: 'Cliente E2E Vita Scan' }).waitFor();
}
