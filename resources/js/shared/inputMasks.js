export function normalizeInteger(value) {
	return String(value ?? '').replace(/\D/g, '');
}

export function heightMask(value) {
	const cleaned = String(value ?? '').replace(/[^\d,.]/g, '').replace('.', ',');

	if (cleaned.includes(',')) {
		const [meters = '', centimeters = ''] = cleaned.split(',');
		return `${meters.slice(0, 1)},${centimeters.slice(0, 2)}`;
	}

	return cleaned.slice(0, 3);
}

export function phoneMask(value) {
	const digits = normalizeInteger(value).slice(0, 11);

	if (digits.length <= 2) return digits;
	if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;

	return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

export function cpfMask(value) {
	const digits = normalizeInteger(value).slice(0, 11);

	if (digits.length <= 3) return digits;
	if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
	if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;

	return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
}
