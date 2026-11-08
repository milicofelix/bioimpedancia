import React from 'react';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import BodyscanConsistencyNotice from './BodyscanConsistencyNotice';

const warnings = [{
	code: 'segmental_fat_total_difference',
	segmental_sum_kg: 24.9,
	total_fat_mass_kg: 25.7,
	difference_kg: 0.8,
}];

describe('BodyscanConsistencyNotice', () => {
	it('distingue divergência numérica de falha de leitura', () => {
		render(<BodyscanConsistencyNotice warnings={warnings} />);
		const notice = screen.getByRole('status');
		expect(notice).toHaveTextContent('24,9 kg');
		expect(notice).toHaveTextContent('25,7 kg');
		expect(notice).toHaveTextContent('0,8 kg');
		expect(notice).toHaveTextContent('não indica erro de leitura');
		expect(notice).toHaveTextContent('Não altere valores apenas para fechar a soma');
		expect(screen.queryByText('Campos para conferir')).not.toBeInTheDocument();
	});

	it('não mostra aviso quando não há divergência', () => {
		const { container } = render(<BodyscanConsistencyNotice />);
		expect(container).toBeEmptyDOMElement();
	});

	it('exibe a confirmação persistida sem pedir nova confirmação', () => {
		render(<BodyscanConsistencyNotice warnings={warnings} reviewedAt="08/11/2026, 10:30" />);
		expect(screen.getByRole('status')).toHaveTextContent('Conferência profissional registrada em 08/11/2026, 10:30');
		expect(screen.queryByText(/A confirmação final também/)).not.toBeInTheDocument();
	});
});
