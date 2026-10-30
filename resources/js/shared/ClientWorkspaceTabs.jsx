import React from 'react';
import PwaIcon from '../pwa/components/ui/PwaIcon';

const tabs = [
	{ id: 'assessment', label: 'Avaliação corporal', icon: 'clipboard' },
	{ id: 'screenings', label: 'Triagens', icon: 'check' },
];

export default function ClientWorkspaceTabs({ activeTab, onChange, sticky = false }) {
	function handleKeyDown(event, currentIndex) {
		if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
		event.preventDefault();

		let nextIndex = currentIndex;
		if (event.key === 'Home') nextIndex = 0;
		if (event.key === 'End') nextIndex = tabs.length - 1;
		if (event.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
		if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % tabs.length;

		const nextTab = tabs[nextIndex];
		onChange(nextTab.id);
		window.requestAnimationFrame(() => document.getElementById(`client-tab-${nextTab.id}`)?.focus());
	}

	return (
		<nav aria-label="Módulos do cliente" className={`no-print rounded-2xl border border-slate-200 bg-white/95 p-1.5 shadow-sm backdrop-blur ${sticky ? 'sticky top-[calc(env(safe-area-inset-top)+4.75rem)] z-10' : ''}`}>
			<div role="tablist" aria-label="Área de atendimento do cliente" className="grid grid-cols-2 gap-1">
				{tabs.map((tab, index) => {
					const active = activeTab === tab.id;
					return (
						<button
							key={tab.id}
							type="button"
							role="tab"
							id={`client-tab-${tab.id}`}
							aria-selected={active}
							aria-controls={`client-panel-${tab.id}`}
							tabIndex={active ? 0 : -1}
							onClick={() => onChange(tab.id)}
							onKeyDown={(event) => handleKeyDown(event, index)}
							className={`inline-flex min-h-11 min-w-0 items-center justify-center gap-1.5 rounded-xl px-2 text-xs font-extrabold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 sm:gap-2 sm:px-3 sm:text-sm ${active ? 'bg-teal-50 text-teal-800 shadow-sm ring-1 ring-teal-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'}`}
						>
							<PwaIcon name={tab.icon} size={18} />{tab.label}
						</button>
					);
				})}
			</div>
		</nav>
	);
}
