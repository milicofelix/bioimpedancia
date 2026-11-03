import { test, expect } from '@playwright/test';
import { openOperationalApp } from './support/routes.js';

test('navegação operacional mantém histórico e botão Voltar', async ({ page }) => {
    await openOperationalApp(page);

    await page.getByRole('button', { name: 'Avaliar' }).click();
    await expect(page).toHaveURL(/#avaliacao$/);
    await expect(page.getByText('Selecione um cliente')).toBeVisible();

    await page.getByRole('button', { name: 'Assistente' }).click();
    await expect(page).toHaveURL(/#assistente$/);

    await page.goBack();
    await expect(page).toHaveURL(/#avaliacao$/);

    await page.goBack();
    await expect(page).toHaveURL(/#clientes$/);
    await expect(page.getByRole('heading', { name: 'Clientes' })).toBeVisible();
});

test('perda e retorno da conexão atualizam o estado operacional', async ({ page, context }) => {
    await openOperationalApp(page);
    await expect(page.getByTitle('Conectado')).toBeVisible();

    await context.setOffline(true);
    await expect(page.getByTitle('Sem conexão')).toBeVisible();
    await expect(page.getByText(/Nenhum dado clínico é armazenado para uso offline/)).toBeVisible();

    await context.setOffline(false);
    await expect(page.getByTitle('Conectado')).toBeVisible();
});
