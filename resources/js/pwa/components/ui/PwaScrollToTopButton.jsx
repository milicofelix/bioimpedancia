import React, { useEffect, useRef, useState } from 'react';
import PwaIcon from './PwaIcon';

const MINIMUM_SCROLL_DISTANCE = 800;
const VIEWPORT_MULTIPLIER = 0.9;

function visibilityThreshold() {
	return Math.max(MINIMUM_SCROLL_DISTANCE, window.innerHeight * VIEWPORT_MULTIPLIER);
}

export default function PwaScrollToTopButton() {
	const [visible, setVisible] = useState(false);
	const animationFrame = useRef(null);

	useEffect(() => {
		function updateVisibility() {
			if (animationFrame.current !== null) return;

			animationFrame.current = window.requestAnimationFrame(() => {
				animationFrame.current = null;
				const scrollPosition = window.scrollY || document.documentElement.scrollTop;
				const shouldBeVisible = scrollPosition >= visibilityThreshold();
				setVisible((current) => current === shouldBeVisible ? current : shouldBeVisible);
			});
		}

		window.addEventListener('scroll', updateVisibility, { passive: true });
		window.addEventListener('resize', updateVisibility, { passive: true });
		updateVisibility();

		return () => {
			window.removeEventListener('scroll', updateVisibility);
			window.removeEventListener('resize', updateVisibility);
			if (animationFrame.current !== null) window.cancelAnimationFrame(animationFrame.current);
		};
	}, []);

	function scrollToTop() {
		const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
		window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
		window.requestAnimationFrame(() => {
			document.getElementById('operational-content')?.focus({ preventScroll: true });
		});
	}

	return (
		<button
			type="button"
			onClick={scrollToTop}
			aria-label="Voltar ao topo do relatório"
			aria-hidden={!visible}
			tabIndex={visible ? 0 : -1}
			title="Voltar ao topo"
			className={`pwa-scroll-top pwa-focus fixed z-20 inline-flex h-12 w-12 items-center justify-center rounded-full border border-ricosty-200 bg-white/95 p-0 text-ricosty-800 shadow-lg shadow-ricosty-950/10 backdrop-blur transition duration-200 ${visible ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-3 opacity-0'}`}
		>
			<PwaIcon name="arrowLeft" size={19} className="rotate-90" />
			<span className="sr-only">Voltar ao topo</span>
		</button>
	);
}
