let registrationScheduled = false;
let operationalRegistration = null;
let reloadingForUpdate = false;
let reloadWhenControlled = false;

export const PWA_UPDATE_AVAILABLE_EVENT = 'ricosty:pwa-update-available';

function notifyUpdateAvailable() {
	window.dispatchEvent(new CustomEvent(PWA_UPDATE_AVAILABLE_EVENT));
}

function watchForUpdate(registration) {
	operationalRegistration = registration;
	if (registration.waiting && navigator.serviceWorker.controller) notifyUpdateAvailable();

	registration.addEventListener('updatefound', () => {
		const installingWorker = registration.installing;
		if (!installingWorker) return;

		installingWorker.addEventListener('statechange', () => {
			if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) notifyUpdateAvailable();
		});
	});
}

export function registerOperationalServiceWorker() {
	if (!('serviceWorker' in navigator) || registrationScheduled) return;
	registrationScheduled = true;
	navigator.serviceWorker.addEventListener('controllerchange', () => {
		if (!reloadWhenControlled || reloadingForUpdate) return;
		reloadingForUpdate = true;
		window.location.reload();
	});

	const register = () => {
		navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).then(watchForUpdate).catch(() => {
			// A interface continua disponível online mesmo quando o navegador não oferece service worker.
		});
	};

	if (document.readyState === 'complete') {
		register();
		return;
	}

	window.addEventListener('load', register, { once: true });
}

export async function activateOperationalUpdate() {
	if (!('serviceWorker' in navigator)) return false;
	const registration = operationalRegistration ?? await navigator.serviceWorker.getRegistration('/');
	if (!registration?.waiting) return false;

	reloadWhenControlled = true;
	registration.waiting.postMessage({ type: 'SKIP_WAITING' });
	return true;
}
