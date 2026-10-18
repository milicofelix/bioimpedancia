import React from 'react';
import PwaIcon from '../../components/ui/PwaIcon';

export default function RelaxmedicOcrProgress({ previewUrl }) {
	return (
		<section role="status" aria-live="polite" aria-busy="true" className="pwa-card overflow-hidden p-5">
			<div className="flex items-center gap-3">
				<span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-ricosty-100 text-ricosty-700"><PwaIcon name="sparkles" size={22} /></span>
				<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-ricosty-600">Processamento OCR</p><h2 className="mt-1 text-lg font-bold text-slate-950">Lendo o relatório</h2></div>
			</div>
			<p className="mt-3 text-sm leading-6 text-slate-600">Estamos identificando as métricas pelos nomes e conferindo números, unidades e data da medição.</p>

			<div className="mt-5 grid grid-cols-[5.5rem_1fr] gap-4">
				<div className="relative overflow-hidden rounded-xl bg-slate-100">
					{previewUrl ? <img src={previewUrl} alt="Relatório em processamento" className="h-32 w-full object-cover opacity-60" /> : <span className="pwa-skeleton block h-32" />}
					<span className="absolute inset-x-0 top-1/2 h-0.5 animate-pulse bg-ricosty-500 shadow-[0_0_10px_rgba(198,83,110,0.8)]" aria-hidden="true" />
				</div>
				<div className="space-y-3 pt-1" aria-hidden="true">
					<span className="pwa-skeleton block h-4 w-4/5 rounded" />
					<span className="pwa-skeleton block h-9 w-full rounded-lg" />
					<span className="pwa-skeleton block h-9 w-full rounded-lg" />
					<span className="pwa-skeleton block h-4 w-2/3 rounded" />
				</div>
			</div>
			<div className="mt-5 flex items-center gap-2 rounded-xl bg-ricosty-50 px-3 py-2 text-xs font-semibold text-ricosty-900"><span className="h-2 w-2 animate-pulse rounded-full bg-ricosty-500" aria-hidden="true" />Processamento em andamento. Não feche esta tela durante a leitura.</div>
		</section>
	);
}
