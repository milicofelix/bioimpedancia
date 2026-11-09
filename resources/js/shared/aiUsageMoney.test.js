import { describe, expect, it } from 'vitest';
import { convertAiUsageUsdToBrl, formatAiUsageMoney } from './aiUsageMoney';

const normalized = (text) => text.replace(/[\u00a0\u202f]/g, ' ');

describe('Apresentação dos custos da IA', () => {
	it('apresenta USD e BRL com duas casas e convenção brasileira', () => {
		expect(normalized(formatAiUsageMoney('1.500000000000'))).toBe('US$ 1,50');
		expect(normalized(formatAiUsageMoney(7.5, 'BRL'))).toBe('R$ 7,50');
		expect(normalized(formatAiUsageMoney('0.000000000000', 'BRL'))).toBe('R$ 0,00');
	});

	it('preserva cobranças menores que um centavo sem zeros finais desnecessários', () => {
		expect(normalized(formatAiUsageMoney('0.001500000000'))).toBe('US$ 0,0015');
		expect(normalized(formatAiUsageMoney('0.000000000001'))).toBe('Menos de US$ 0,00000001');
		expect(normalized(formatAiUsageMoney('0.00000001'))).toBe('US$ 0,00000001');
	});

	it('mantém precisão da cotação sem mudar o formato normal dos totais', () => {
		expect(normalized(formatAiUsageMoney(5.123456, 'BRL', { precise: true }))).toBe('R$ 5,123456');
		expect(normalized(formatAiUsageMoney(10, 'BRL', { precise: true }))).toBe('R$ 10,00');
	});

	it('multiplica o consumo pela cotação, não pelo valor da recarga', () => {
		expect(convertAiUsageUsdToBrl('1.50', '5.00')).toBe(7.5);
		expect(convertAiUsageUsdToBrl('1.50', '10.00')).toBe(15);
		expect(convertAiUsageUsdToBrl(0, '5.00')).toBe(0);
	});

	it('não apresenta valores ausentes ou inválidos como custo zero', () => {
		for (const invalid of [null, undefined, '', ' ', 'not-a-number', NaN, Infinity, -1, false]) {
			expect(formatAiUsageMoney(invalid)).toBe('Não disponível');
			expect(convertAiUsageUsdToBrl(invalid, 5)).toBeNull();
		}
		for (const invalidRate of [null, undefined, '', 0, -1, '5,00', Infinity]) {
			expect(convertAiUsageUsdToBrl(1.5, invalidRate)).toBeNull();
		}
	});
});
