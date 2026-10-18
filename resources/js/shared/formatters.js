export function localDatetimeNow() {
	const date = new Date();
	const timezoneOffset = date.getTimezoneOffset() * 60000;

	return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
}

export function formatDate(value) {
	if (!value) return '-';

	return new Intl.DateTimeFormat('pt-BR', {
		dateStyle: 'short',
		timeStyle: 'short',
	}).format(new Date(value));
}

export function formatIssueDate(value) {
	if (!value) return '-';

	return new Intl.DateTimeFormat('pt-BR').format(new Date(value));
}

export function toDatetimeLocal(value) {
	if (!value) return localDatetimeNow();

	const date = new Date(value);
	const timezoneOffset = date.getTimezoneOffset() * 60000;

	return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
}

export function numberBr(value, decimals = 1) {
	if (value === null || value === undefined || value === '') return '-';

	return new Intl.NumberFormat('pt-BR', {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	}).format(Number(value));
}

export function assessmentNumber(id) {
	return String(id ?? 1).padStart(4, '0');
}
