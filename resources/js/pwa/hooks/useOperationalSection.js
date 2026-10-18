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

		if (!window.location.hash) {
			window.history.replaceState(null, '', '#clientes');
		}

		return () => window.removeEventListener('hashchange', handleHashChange);
	}, []);

	function navigate(nextSection) {
		if (!SECTIONS.includes(nextSection)) return;
		window.location.hash = nextSection;
		setSection(nextSection);
		const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
	}

	return { section, navigate };
}
