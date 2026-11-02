import { test, expect } from '@playwright/test';
import { e2eCredentials } from './support/environment.js';

test('login válido abre uma área autenticada e logout encerra a sessão', async ({ page }) => {
    const credentials = e2eCredentials();
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(credentials.email);
    await page.getByLabel('Senha').fill(credentials.password);
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page).toHaveURL(/\/(app|dashboard)(#.*)?$/);
    await page.goto('/app');
    await page.getByRole('button', { name: 'Sair da conta' }).click();
    await expect(page).toHaveURL(/\/login$/);
});

test('login inválido informa o erro sem revelar detalhes internos', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill('inexistente@example.test');
    await page.getByLabel('Senha').fill('senha-incorreta');
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText(/credenciais|corrija os campos/i).first()).toBeVisible();
});
