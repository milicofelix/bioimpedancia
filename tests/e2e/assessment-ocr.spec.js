import { test, expect } from '@playwright/test';
import { assessmentResponse, relaxmedicExtraction } from './support/fixtures.js';
import { openFixtureClient, openOperationalApp } from './support/routes.js';

test('seleciona imagem, revisa OCR e cria avaliação após confirmação humana', async ({ page }) => {
    await page.route('**/bioimpedance/relaxmedic/process-image', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ extraction: relaxmedicExtraction }) });
    });
    await page.route('**/bioimpedance/assessments', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(assessmentResponse()) });
    });

    await openOperationalApp(page);
    await openFixtureClient(page);
    await page.getByRole('button', { name: 'Nova avaliação' }).first().click();
    await expect(page).toHaveURL(/#avaliacao$/);

    await page.locator('input[type="file"]').setInputFiles({
        name: 'relatorio-e2e.jpg',
        mimeType: 'image/jpeg',
        buffer: Buffer.from([0xff, 0xd8, 0xff, 0xd9]),
    });
    await page.getByRole('button', { name: 'Enviar e processar imagem' }).click();

    await expect(page.getByRole('heading', { name: 'Compare e corrija os valores' })).toBeVisible();
    await page.getByRole('tab', { name: 'Valores extraídos' }).click();
    await expect(page.getByLabel('Peso (kg)')).toHaveValue('97');
    await page.getByRole('checkbox', { name: /Conferi a imagem/ }).check();
    await page.getByRole('button', { name: 'Confirmar e criar avaliação' }).click();

    await expect(page.getByRole('heading', { name: 'Avaliação criada com segurança' })).toBeVisible();
    await expect(page.getByText(/imagem temporária foi removida/)).toBeVisible();
});

test('falha de OCR mantém a avaliação não criada e mostra mensagem operacional', async ({ page }) => {
    await page.route('**/bioimpedance/relaxmedic/process-image', async (route) => {
        await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'Imagem incompatível.', errors: { image: ['Não foi possível identificar o relatório enviado.'] } }) });
    });

    await openOperationalApp(page);
    await openFixtureClient(page);
    await page.getByRole('button', { name: 'Nova avaliação' }).first().click();
    await page.locator('input[type="file"]').setInputFiles({ name: 'ilegivel.jpg', mimeType: 'image/jpeg', buffer: Buffer.from([0xff, 0xd8, 0xff, 0xd9]) });
    await page.getByRole('button', { name: 'Enviar e processar imagem' }).click();

    await expect(page.getByRole('alert')).toContainText('Não foi possível identificar o relatório enviado.');
    await expect(page.getByRole('button', { name: 'Confirmar e criar avaliação' })).not.toBeVisible();
});
