import React from 'react';

const modules = [
	{ id: 'clientes', title: 'Clientes', description: 'Cadastro, busca e histórico do cliente.' },
	{ id: 'avaliacao', title: 'Nova avaliação', description: 'Fluxos Omron e Relaxmedic adaptados ao celular.' },
	{ id: 'assistente', title: 'Assistente', description: 'Geração e aprovação de observações da avaliação.' },
	{ id: 'relatorio', title: 'Relatório', description: 'Consulta, PDF e compartilhamento seguro.' },
];

export default function OperationalOverview({ clientsCount, scaleModel }) {
	const scaleModelLabel = {
		omron_hbf_514c: 'Omron HBF-514C',
		relaxmedic: 'Relaxmedic',
	}[scaleModel] ?? scaleModel ?? 'não informada';

	return (
		<section aria-labelledby="operational-overview-title">
			<div className="flex items-end justify-between gap-4">
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Preparação da PWA</p>
					<h2 id="operational-overview-title" className="mt-1 text-lg font-bold text-slate-950">Fluxo operacional</h2>
				</div>
				<span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm">{clientsCount} clientes</span>
			</div>

			<div className="mt-4 space-y-3">
				{modules.map((module, index) => (
					<article id={module.id} key={module.id} className="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
						<div className="flex items-start gap-3">
							<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-sm font-black text-emerald-700">{index + 1}</span>
							<div>
								<h3 className="font-bold text-slate-950">{module.title}</h3>
								<p className="mt-1 text-sm leading-5 text-slate-600">{module.description}</p>
							</div>
						</div>
					</article>
				))}
			</div>

			<div className="mt-4 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
				<p className="font-bold">Configuração atual</p>
				<p className="mt-1">Balança: {scaleModelLabel}. As próximas fases conectarão cada módulo a este shell.</p>
			</div>
		</section>
	);
}
