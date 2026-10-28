import React, { useEffect, useState } from 'react';
import PwaIcon from '../../components/ui/PwaIcon';

const stages = [
	['Leitura geral', 'Identificando data, peso e métricas do índice corporal.'],
	['Leitura segmentar', 'Conferindo gordura, equilíbrio muscular e marcadores L/R.'],
];

export default function BodyscanOcrProgress({ previewUrl, onStageChange }) {
	const [stage, setStage] = useState(0);

	useEffect(() => {
		const timeout = window.setTimeout(() => {
			setStage(1);
			onStageChange?.(1);
		}, 1800);
		return () => window.clearTimeout(timeout);
	}, [onStageChange]);

	return (
		<section role="status" aria-live="polite" aria-busy="true" className="pwa-card p-5">
			<div className="flex items-center gap-3"><span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-ricosty-100 text-ricosty-700"><PwaIcon name="sparkles" /></span><div><p className="text-xs font-bold uppercase tracking-wide text-ricosty-600">Bodyscan Pro</p><h2 className="font-bold text-slate-950">{stages[stage][0]}</h2></div></div>
			<p className="mt-2 text-sm text-slate-600">{stages[stage][1]}</p>
			<div className="mt-4 grid grid-cols-[5rem_1fr] gap-4"><div className="max-h-40 overflow-hidden rounded-xl bg-slate-100">{previewUrl ? <img src={previewUrl} alt="Relatório Bodyscan em processamento" className="h-40 w-full object-cover object-top opacity-60" /> : null}</div><div className="space-y-3 pt-2" aria-hidden="true"><span className="pwa-skeleton block h-4 w-4/5 rounded"/><span className="pwa-skeleton block h-10 w-full rounded"/><span className="pwa-skeleton block h-10 w-full rounded"/></div></div>
			<div className="mt-4 flex gap-2">{stages.map(([title], index) => <span key={title} className={`h-2 flex-1 rounded-full ${index <= stage ? 'bg-ricosty-500' : 'bg-slate-200'}`} aria-hidden="true" />)}</div>
		</section>
	);
}
