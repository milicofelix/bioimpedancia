import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ObservationSuggestion from './ObservationSuggestion';

const generatedAssistant = {
    status: 'generated',
    validation_status: 'passed',
    suggestion: 'Observação suficientemente extensa para revisão pelo profissional responsável.',
    sources: [
        { id: 'source-1', document: 'Protocolo Vita Scan', version: '1.0', section: 'Medição' },
        { id: 'source-2', document: 'Protocolo Vita Scan', version: '1.0', section: 'Conferência' },
    ],
};

const requiredCallbacks = {
    onApprove: () => {},
    onDiscard: () => {},
    onDraftChange: () => {},
};

describe('ObservationSuggestion', () => {
    it('mantém aprovação indisponível para texto curto ou operação offline', () => {
        const { rerender } = render(<ObservationSuggestion assistant={generatedAssistant} draft="Texto curto" saving={false} online error="" {...requiredCallbacks} />);

        expect(screen.getByRole('button', { name: 'Aprovar versão editada' })).toBeDisabled();

        rerender(<ObservationSuggestion assistant={generatedAssistant} draft={generatedAssistant.suggestion} saving={false} online={false} error="" {...requiredCallbacks} />);
        expect(screen.getByRole('button', { name: 'Aprovar e adicionar' })).toBeDisabled();
    });

    it('agrupa trechos do mesmo documento e permite editar antes de aprovar', async () => {
        const user = userEvent.setup();
        const onDraftChange = vi.fn();
        render(<ObservationSuggestion assistant={generatedAssistant} draft={generatedAssistant.suggestion} saving={false} online error="" {...requiredCallbacks} onDraftChange={onDraftChange} />);

        expect(screen.getByText('1 documento')).toBeInTheDocument();
        expect(screen.getByText('2 trechos utilizados')).toBeInTheDocument();

        await user.type(screen.getByRole('textbox', { name: /Observação profissional/ }), ' Ajuste');
        expect(onDraftChange).toHaveBeenCalled();
    });

    it('exibe bloqueio sem oferecer aprovação', () => {
        render(<ObservationSuggestion assistant={{ status: 'blocked', validation_status: 'blocked', summary: 'Conteúdo clínico não sustentado.', validation_errors: ['Fonte insuficiente.'] }} draft="" saving={false} online error="" {...requiredCallbacks} />);

        expect(screen.getByRole('alert')).toHaveTextContent('Sugestão bloqueada pela validação');
        expect(screen.getByText('Conteúdo clínico não sustentado.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Aprovar/ })).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Descartar' })).toBeEnabled();
    });
});
