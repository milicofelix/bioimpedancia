import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ReportSharing from './ReportSharing';
import useOnlineStatus from '../../hooks/useOnlineStatus';
import { createAssessmentShare, revokeAssessmentShare } from '../../../services/bioimpedanceApi';

vi.mock('../../hooks/useOnlineStatus', () => ({ default: vi.fn() }));
vi.mock('../../../services/bioimpedanceApi', () => ({
    apiErrorMessage: (_error, fallback) => fallback,
    createAssessmentShare: vi.fn(),
    revokeAssessmentShare: vi.fn(),
}));

const activeShare = {
    id: 9,
    expires_at: '2030-01-20T12:00:00.000Z',
    revoked_at: null,
    is_active: true,
    view_count: 0,
};

describe('ReportSharing', () => {
    beforeEach(() => {
        useOnlineStatus.mockReturnValue(true);
    });

    it('explica e bloqueia a criação para perfil sem permissão', () => {
        render(<ReportSharing assessment={{ id: 15, shares: [] }} canCreate={false} canRevoke={false} onAssessmentUpdated={() => {}} />);

        expect(screen.getByRole('button', { name: 'Criar link temporário' })).toBeDisabled();
        expect(screen.getByText('Criação indisponível para este perfil')).toBeInTheDocument();
    });

    it('bloqueia criação e revogação enquanto estiver offline', () => {
        useOnlineStatus.mockReturnValue(false);
        render(<ReportSharing assessment={{ id: 15, shares: [activeShare] }} canCreate canRevoke onAssessmentUpdated={() => {}} />);

        expect(screen.getByText('Sem conexão')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Criar link temporário' })).toBeDisabled();
        expect(screen.getByRole('button', { name: /Revogar link que expira/ })).toBeDisabled();
    });

    it('cria um link e atualiza a avaliação exibida', async () => {
        const user = userEvent.setup();
        const onAssessmentUpdated = vi.fn();
        createAssessmentShare.mockResolvedValue({
            share: { ...activeShare, url: 'https://example.test/r/token', message: 'Relatório disponível' },
            assessment: { id: 15, shares: [activeShare] },
        });
        render(<ReportSharing assessment={{ id: 15, shares: [] }} canCreate canRevoke onAssessmentUpdated={onAssessmentUpdated} />);

        await user.selectOptions(screen.getByRole('combobox', { name: 'Validade do link' }), '15');
        await user.click(screen.getByRole('button', { name: 'Criar link temporário' }));

        await waitFor(() => expect(createAssessmentShare).toHaveBeenCalledWith(15, expect.objectContaining({ expires_in_days: 15 })));
        expect(await screen.findByText('https://example.test/r/token')).toBeInTheDocument();
        expect(onAssessmentUpdated).toHaveBeenCalledWith(expect.objectContaining({ id: 15 }));
    });

    it('exige confirmação antes de revogar um link ativo', async () => {
        const user = userEvent.setup();
        const onAssessmentUpdated = vi.fn();
        revokeAssessmentShare.mockResolvedValue({ assessment: { id: 15, shares: [{ ...activeShare, is_active: false, revoked_at: '2030-01-10T12:00:00.000Z' }] } });
        render(<ReportSharing assessment={{ id: 15, shares: [activeShare] }} canCreate canRevoke onAssessmentUpdated={onAssessmentUpdated} />);

        await user.click(screen.getByRole('button', { name: /Revogar link que expira/ }));
        expect(screen.getByRole('dialog', { name: 'Revogar este link?' })).toBeInTheDocument();
        expect(revokeAssessmentShare).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: 'Revogar link' }));

        await waitFor(() => expect(revokeAssessmentShare).toHaveBeenCalledWith(9));
        expect(onAssessmentUpdated).toHaveBeenCalledWith(expect.objectContaining({ id: 15 }));
    });
});
