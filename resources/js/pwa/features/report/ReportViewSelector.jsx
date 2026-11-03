import React from 'react';
import PwaIcon from '../../components/ui/PwaIcon';

const views = [
	{ id: 'analysis', icon: 'eye', title: 'Analisar relatório na tela', description: 'Consultar resultados, evolução e orientações.' },
	{ id: 'export', icon: 'share', title: 'Exportar relatório', description: 'Compartilhar o acesso ou obter o documento em PDF.' },
];

export default function ReportViewSelector({ activeView, onSelect }) {
	return (
		<div className="grid min-w-0 gap-3 sm:grid-cols-2 print:hidden" role="group" aria-label="Opções do relatório">
			{views.map((view) => {
				const expanded = activeView === view.id;
				return (
					<button
						key={view.id}
						type="button"
						id={`report-${view.id}-button`}
						aria-expanded={expanded}
						aria-controls={`report-${view.id}-panel`}
						onClick={() => onSelect(view.id)}
						className={`pwa-focus pwa-pressable flex min-h-24 min-w-0 items-center gap-3 rounded-2xl border p-4 text-left transition ${expanded ? 'border-ricosty-700 bg-ricosty-50 text-ricosty-950 ring-1 ring-ricosty-700' : 'border-ricosty-100 bg-white text-slate-900 hover:bg-ricosty-50'}`}
					>
						<span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ricosty-100 text-ricosty-800"><PwaIcon name={view.icon} size={21} /></span>
						<span className="min-w-0 flex-1"><strong className="block break-words text-sm">{view.title}</strong><span className="mt-1 block text-xs leading-5 text-slate-600">{view.description}</span></span>
						<PwaIcon name="chevronRight" size={18} className={`shrink-0 transition-transform ${expanded ? 'rotate-90' : ''}`} />
					</button>
				);
			})}
		</div>
	);
}
