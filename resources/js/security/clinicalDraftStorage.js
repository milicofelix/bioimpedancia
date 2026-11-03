const DRAFT_PREFIX = 'vita-scan:assessment-draft:';
const LEGACY_DRAFT_PREFIXES = ['ricosty:assessment-draft:'];
const DRAFT_TTL_MS = 8 * 60 * 60 * 1000;

function draftKey(clientId) {
	return clientId ? `${DRAFT_PREFIX}${clientId}` : null;
}

function removeKeysWithPrefixes(storage, prefixes) {
	if (!storage) return;

	const keys = [];
	for (let index = 0; index < storage.length; index += 1) {
		const key = storage.key(index);
		if (key && prefixes.some((prefix) => key.startsWith(prefix))) keys.push(key);
	}

	keys.forEach((key) => storage.removeItem(key));
}

export function purgeLegacyClinicalDrafts() {
	try {
		removeKeysWithPrefixes(window.localStorage, LEGACY_DRAFT_PREFIXES);
	} catch {
		// Storage can be unavailable in restricted browser contexts.
	}
}

export function readAssessmentDraft(clientId) {
	const key = draftKey(clientId);
	if (!key) return null;

	purgeLegacyClinicalDrafts();

	try {
		const storedDraft = window.sessionStorage.getItem(key);
		if (!storedDraft) return null;

		const draft = JSON.parse(storedDraft);
		const expiresAt = Date.parse(draft?.expires_at ?? '');
		if (!draft?.form || !Number.isFinite(expiresAt) || expiresAt <= Date.now()) {
			window.sessionStorage.removeItem(key);
			return null;
		}

		return draft;
	} catch {
		try {
			window.sessionStorage.removeItem(key);
		} catch {
			// Storage can be unavailable in restricted browser contexts.
		}
		return null;
	}
}

export function writeAssessmentDraft(clientId, form) {
	const key = draftKey(clientId);
	if (!key) return null;

	const savedAt = new Date();
	const draft = {
		schema_version: 1,
		form,
		saved_at: savedAt.toISOString(),
		expires_at: new Date(savedAt.getTime() + DRAFT_TTL_MS).toISOString(),
	};

	try {
		window.sessionStorage.setItem(key, JSON.stringify(draft));
		return draft.saved_at;
	} catch {
		return null;
	}
}

export function removeAssessmentDraft(clientId) {
	const key = draftKey(clientId);
	if (!key) return;

	try {
		window.sessionStorage.removeItem(key);
	} catch {
		// Storage can be unavailable in restricted browser contexts.
	}
}

export function clearClinicalDrafts() {
	try {
		removeKeysWithPrefixes(window.sessionStorage, [DRAFT_PREFIX, ...LEGACY_DRAFT_PREFIXES]);
	} catch {
		// Storage can be unavailable in restricted browser contexts.
	}

	try {
		removeKeysWithPrefixes(window.localStorage, [DRAFT_PREFIX, ...LEGACY_DRAFT_PREFIXES]);
	} catch {
		// Storage can be unavailable in restricted browser contexts.
	}
}
