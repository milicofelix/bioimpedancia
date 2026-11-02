import { clearClinicalDrafts } from './clinicalDraftStorage';

const PWA_CACHE_PREFIXES = ['vita-scan-pwa-', 'ricosty-pwa-'];
let redirectingToLogin = false;

export async function clearOperationalCaches() {
	clearClinicalDrafts();

	if ('serviceWorker' in navigator) {
		const registration = await navigator.serviceWorker.getRegistration('/');
		registration?.active?.postMessage({ type: 'CLEAR_OPERATIONAL_CACHES' });
	}

	if ('caches' in window) {
		const keys = await window.caches.keys();
		await Promise.all(keys.filter((key) => PWA_CACHE_PREFIXES.some((prefix) => key.startsWith(prefix))).map((key) => window.caches.delete(key)));
	}
}

export async function redirectExpiredSession() {
	if (redirectingToLogin || window.location.pathname === '/login') return;
	redirectingToLogin = true;

	try {
		await clearOperationalCaches();
	} finally {
		window.location.replace('/login?expired=1');
	}
}
