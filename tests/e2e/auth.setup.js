import { test as setup, expect } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { e2eCredentials } from './support/environment.js';

const authFile = 'playwright/.auth/user.json';

setup('autenticar profissional para as jornadas operacionais', async ({ page }) => {
    const credentials = e2eCredentials();
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(credentials.email);
    await page.getByLabel('Senha').fill(credentials.password);
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page).toHaveURL(/\/(app|dashboard)(#.*)?$/);
    await mkdir('playwright/.auth', { recursive: true });
    await page.context().storageState({ path: authFile });
});
