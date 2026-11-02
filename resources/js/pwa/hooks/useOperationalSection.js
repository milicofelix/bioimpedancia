import { useEffect, useState } from 'react';

const SECTIONS = ['clientes', 'avaliacao', 'assistente', 'relatorio'];

function sectionFromHash() {
	const section = window.location.hash.replace('#', '');

	return SECTIONS.includes(section) ? section : 'clientes';
}

export default function useOperationalSection() {
	const [section, setSection] = useState(sectionFromHash);

	useEffect(() => {
		const handleHashChange = () => setSection(sectionFromHash());
		window.addEventListener('hashchange', handleHashChange);

		const requestedSection = window.location.hash.replace('#', '');
		if (!SECTIONS.includes(requestedSection)) {
			window.history.replaceState(null, '', '#clientes');
			setSection('clientes');
		}

		return () => window.removeEventListener('hashchange', handleHashChange);
	}, []);

	function navigate(nextSection, { replace = false } = {}) {
		if (!SECTIONS.includes(nextSection)) return;
		if (replace) window.history.replaceState(null, '', `#${nextSection}`);
		else if (window.location.hash !== `#${nextSection}`) window.location.hash = nextSection;
		setSection(nextSection);
		const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
	}

	return { section, navigate };
}
