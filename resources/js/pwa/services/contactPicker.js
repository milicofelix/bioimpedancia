const CLIENT_CONTACT_PROPERTIES = ['name', 'tel', 'email'];

export function supportsContactPicker() {
	return window.isSecureContext
		&& 'contacts' in navigator
		&& typeof navigator.contacts?.select === 'function';
}

function firstValue(values) {
	return Array.isArray(values) ? String(values.find(Boolean) ?? '').trim() : '';
}

function brazilianPhone(value) {
	const digits = String(value ?? '').replace(/\D/g, '');
	return digits.length > 11 && digits.startsWith('55') ? digits.slice(2) : digits;
}

export async function pickClientContact() {
	if (!supportsContactPicker()) {
		throw new Error('CONTACT_PICKER_UNSUPPORTED');
	}

	const supportedProperties = typeof navigator.contacts.getProperties === 'function'
		? await navigator.contacts.getProperties()
		: CLIENT_CONTACT_PROPERTIES;
	const requestedProperties = CLIENT_CONTACT_PROPERTIES.filter((property) => supportedProperties.includes(property));

	if (!requestedProperties.length) {
		throw new Error('CONTACT_PICKER_NO_PROPERTIES');
	}

	const [contact] = await navigator.contacts.select(requestedProperties, { multiple: false });
	if (!contact) return null;

	return {
		full_name: firstValue(contact.name),
		phone: brazilianPhone(firstValue(contact.tel)),
		email: firstValue(contact.email),
	};
}
