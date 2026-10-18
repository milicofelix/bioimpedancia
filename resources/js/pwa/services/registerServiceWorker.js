let registrationScheduled = false;

export function registerOperationalServiceWorker() {
	if (!('serviceWorker' in navigator) || registrationScheduled) return;
	registrationScheduled = true;

	const register = () => {
		navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(() => {
			// A interface continua disponível online mesmo quando o navegador não oferece service worker.
		});
	};

	if (document.readyState === 'complete') {
		register();
		return;
	}

	window.addEventListener('load', register, { once: true });
}
