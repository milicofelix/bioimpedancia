import { test, expect } from '@playwright/test';
import { activeShare, assessment, client } from './support/fixtures.js';
import { openFixtureClient, openOperationalApp } from './support/routes.js';

test('abre relatório, disponibiliza PDF e controla compartilhamento temporário', async ({ page, context }) => {
    const shareWithUrl = { ...activeShare, url: 'https://example.test/r/e2e-token', message: 'Relatório Vita Scan disponível.' };
    const assessmentWithShare = { ...assessment, shares: [shareWithUrl] };

    await page.route('**/bioimpedance/assessments/501/shares', async (route) => {
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ share: shareWithUrl, assessment: assessmentWithShare }) });
    });
    await page.route('**/bioimpedance/report-shares/901/revoke', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ share: { ...activeShare, is_active: false, revoked_at: '2026-11-02T12:00:00.000Z' }, assessment: { ...assessment, shares: [{ ...activeShare, is_active: false, revoked_at: '2026-11-02T12:00:00.000Z' }] } }) });
    });
    await context.route('**/bioimpedance/assessments/501/pdf**', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/pdf', body: '%PDF-1.4\n%%EOF' });
    });

    await openOperationalApp(page, { clients: [{ ...client, assessments: [assessment] }] });
    await openFixtureClient(page);
    await page.getByRole('button', { name: 'Abrir relatório recente' }).click();

    await expect(page).toHaveURL(/#relatorio$/);
    await expect(page.getByText('Relatório de bioimpedância', { exact: true })).toBeVisible();
    const pdfLink = page.getByRole('link', { name: /Conferir PDF/ });
    await expect(pdfLink).toHaveAttribute('href', /\/bioimpedance\/assessments\/501\/pdf\?inline=1/);
    const pdfPagePromise = context.waitForEvent('page');
    await pdfLink.click();
    const pdfPage = await pdfPagePromise;
    await expect(pdfPage).toHaveURL(/\/bioimpedance\/assessments\/501\/pdf\?inline=1/);
    await pdfPage.close();

    await page.getByRole('button', { name: 'Criar link temporário' }).click();
    await expect(page.getByText('https://example.test/r/e2e-token').first()).toBeVisible();
    await expect(page.getByText('Histórico de links')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Abrir link' })).toHaveAttribute('href', 'https://example.test/r/e2e-token');

    await page.getByRole('button', { name: /Revogar link que expira/ }).click();
    await expect(page.getByRole('dialog', { name: 'Revogar este link?' })).toBeVisible();
    await page.getByRole('button', { name: 'Revogar link' }).click();
    await expect(page.getByText(/Link revogado/)).toBeVisible();
});
