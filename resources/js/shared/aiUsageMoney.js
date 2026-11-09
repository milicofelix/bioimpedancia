function numericAmount(value) {
	if (value == null || !['string', 'number'].includes(typeof value) || String(value).trim() === '') return null;
	const amount = Number(value);
	return Number.isFinite(amount) && amount >= 0 ? (amount === 0 ? 0 : amount) : null;
}

export function formatAiUsageMoney(value, currency = 'USD', { precise = false } = {}) {
	const amount = numericAmount(value);
	if (amount == null) return 'Não disponível';
	const formatter = new Intl.NumberFormat('pt-BR', {
		style: 'currency',
		currency,
		minimumFractionDigits: 2,
		maximumFractionDigits: precise || (amount > 0 && amount < 0.01) ? 8 : 2,
	});
	// Do not present a real sub-cent charge as free after rounding.
	if (amount > 0 && amount < 0.00000001) return `Menos de ${formatter.format(0.00000001)}`;
	return formatter.format(amount);
}

export function convertAiUsageUsdToBrl(value, rate) {
	const amount = numericAmount(value);
	const exchangeRate = numericAmount(rate);
	if (amount == null || exchangeRate == null || exchangeRate === 0) return null;
	const converted = amount * exchangeRate;
	return Number.isFinite(converted) ? converted : null;
}
