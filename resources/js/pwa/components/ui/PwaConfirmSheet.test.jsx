import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import PwaConfirmSheet from './PwaConfirmSheet';

describe('PwaConfirmSheet', () => {
    it('não renderiza conteúdo quando está fechado', () => {
        render(<PwaConfirmSheet open={false} title="Confirmar" description="Descrição" />);

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('oferece confirmação, cancelamento e fechamento pelo Escape', async () => {
        const user = userEvent.setup();
        const onCancel = vi.fn();
        const onConfirm = vi.fn();
        render(<PwaConfirmSheet open title="Revogar link?" description="O acesso será encerrado." confirmLabel="Revogar link" onCancel={onCancel} onConfirm={onConfirm} />);

        const dialog = screen.getByRole('dialog', { name: 'Revogar link?' });
        expect(dialog).toHaveAttribute('aria-modal', 'true');

        await user.click(screen.getByRole('button', { name: 'Revogar link' }));
        expect(onConfirm).toHaveBeenCalledOnce();

        await user.click(screen.getByRole('button', { name: 'Cancelar' }));
        expect(onCancel).toHaveBeenCalledOnce();

        fireEvent.keyDown(window, { key: 'Escape' });
        expect(onCancel).toHaveBeenCalledTimes(2);
    });
});
