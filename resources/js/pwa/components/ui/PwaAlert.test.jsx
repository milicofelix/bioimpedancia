import React from 'react';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import PwaAlert from './PwaAlert';

describe('PwaAlert', () => {
    it('anuncia erros de forma assertiva', () => {
        render(<PwaAlert tone="error" title="Falha ao salvar">Revise os dados informados.</PwaAlert>);

        const alert = screen.getByRole('alert');
        expect(alert).toHaveAttribute('aria-live', 'assertive');
        expect(alert).toHaveTextContent('Falha ao salvar');
        expect(alert).toHaveTextContent('Revise os dados informados.');
    });

    it('anuncia informações sem interromper a leitura', () => {
        render(<PwaAlert tone="success" title="Avaliação salva">Os dados foram registrados.</PwaAlert>);

        const status = screen.getByRole('status');
        expect(status).toHaveAttribute('aria-live', 'polite');
        expect(status).toHaveTextContent('Avaliação salva');
    });
});
