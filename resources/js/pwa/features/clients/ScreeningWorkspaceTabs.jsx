import React from 'react';
import PwaIcon from '../../components/ui/PwaIcon';

const tabs = [
	{ id: 'new', label: 'Nova aplicação', icon: 'plus' },
	{ id: 'history', label: 'Histórico', icon: 'history' },
];

export default function ScreeningWorkspaceTabs({ activeTab, historyCount, onChange }) {
	function handleKeyDown(event, index) {
		if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
		event.preventDefault();
		let nextIndex = index;
		if (event.key === 'Home') nextIndex = 0;
		if (event.key === 'End') nextIndex = tabs.length - 1;
		if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
		if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
		const nextTab = tabs[nextIndex];
		onChange(nextTab.id);
		window.requestAnimationFrame(() => document.getElementById(`screening-tab-${nextTab.id}`)?.focus());
	}

	return <div className="mt-4 grid grid-cols-2 rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Ações e histórico de triagens">
		{tabs.map((tab, index) => {
			const active = activeTab === tab.id;
			return <button
				key={tab.id}
				type="button"
				role="tab"
				id={`screening-tab-${tab.id}`}
				aria-selected={active}
				aria-controls={`screening-panel-${tab.id}`}
				tabIndex={active ? 0 : -1}
				onClick={() => onChange(tab.id)}
				onKeyDown={(event) => handleKeyDown(event, index)}
				className={`pwa-focus inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-2 text-sm font-bold ${active ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
			>
				<PwaIcon name={tab.icon} size={17} />{tab.label}{tab.id === 'history' ? ` (${historyCount})` : ''}
			</button>;
		})}
	</div>;
}
