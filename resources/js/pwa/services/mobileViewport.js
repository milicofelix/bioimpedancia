const KEYBOARD_THRESHOLD = 160;

export function observeMobileViewport() {
	const viewport = window.visualViewport;
	const root = document.documentElement;
	if (!viewport) return () => {};

	let initialHeight = Math.max(window.innerHeight, viewport.height);

	const update = () => {
		if (viewport.height > initialHeight) initialHeight = viewport.height;
		const keyboardOpen = initialHeight - viewport.height > KEYBOARD_THRESHOLD;

		root.style.setProperty('--pwa-viewport-height', `${Math.round(viewport.height)}px`);
		root.toggleAttribute('data-keyboard-open', keyboardOpen);
	};

	update();
	viewport.addEventListener('resize', update);
	viewport.addEventListener('scroll', update);

	return () => {
		viewport.removeEventListener('resize', update);
		viewport.removeEventListener('scroll', update);
		root.style.removeProperty('--pwa-viewport-height');
		root.removeAttribute('data-keyboard-open');
	};
}
