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
			<div className="mt-4 grid grid-cols-[5rem_1fr] gap-4"><div className="max-h-40 overflow-hidden rounded-xl bg-slate-100">{previewUrl ? <img src={previewUrl} alt="Relatório Bodyscan em processamento" className="h-40 w-full object-cover object-top opacity-60" /> : null}</div><ol className="space-y-2">{stages.map(([title, description], index) => <li key={title} className={`flex items-start gap-2 rounded-xl border p-2 text-xs ${index === stage ? 'border-ricosty-200 bg-ricosty-50 text-ricosty-900' : index < stage ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-50 text-slate-500'}`}><span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white">{index < stage ? <PwaIcon name="check" size={13} /> : index === stage ? <PwaIcon name="refresh" size={13} className="animate-spin" /> : index + 1}</span><span><strong className="block">{title}</strong><span className="mt-0.5 block leading-4">{description}</span></span></li>)}</ol></div>
			<div className="mt-4 flex gap-2">{stages.map(([title], index) => <span key={title} className={`h-2 flex-1 rounded-full ${index <= stage ? 'bg-ricosty-500' : 'bg-slate-200'}`} aria-hidden="true" />)}</div>
			<p className="mt-3 flex items-center gap-2 text-xs font-semibold text-slate-600"><PwaIcon name="info" size={15} />O tempo depende da qualidade e do tamanho da imagem. A conclusão é confirmada pelo processamento real.</p>
		</section>
	);
}
