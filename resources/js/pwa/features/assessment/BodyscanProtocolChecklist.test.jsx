import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import BodyscanProtocolChecklist from './BodyscanProtocolChecklist';

const completeProtocol = {
    profile_confirmed: true,
    athlete_mode_confirmed: true,
    electrodes_contact_confirmed: true,
    comparable_conditions_confirmed: true,
};

describe('BodyscanProtocolChecklist', () => {
    it('exibe e preserva todas as confirmações do protocolo', () => {
        render(<BodyscanProtocolChecklist value={completeProtocol} onChange={() => {}} />);

        expect(screen.getAllByRole('checkbox')).toHaveLength(4);
        expect(screen.getByRole('checkbox', { name: 'Modo atleta conferido.' })).toBeChecked();
        expect(screen.getByText('Orientações completas do fabricante')).toBeInTheDocument();
    });

    it('informa a alteração do modo atleta pelo campo correspondente', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(<BodyscanProtocolChecklist value={{ ...completeProtocol, athlete_mode_confirmed: false }} onChange={onChange} />);

        await user.click(screen.getByRole('checkbox', { name: 'Modo atleta conferido.' }));

        expect(onChange).toHaveBeenCalledWith('athlete_mode_confirmed', true);
    });

    it('bloqueia alterações durante o processamento', () => {
        render(<BodyscanProtocolChecklist value={completeProtocol} onChange={() => {}} disabled />);

        screen.getAllByRole('checkbox').forEach((checkbox) => expect(checkbox).toBeDisabled());
    });
});
