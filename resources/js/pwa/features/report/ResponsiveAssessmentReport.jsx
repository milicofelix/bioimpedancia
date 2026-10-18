import React from 'react';
import { formatDate } from '../../../shared/formatters';
import { formatMetric, metricDelta, metricsForAssessment } from './reportMetrics';
import PwaIcon from '../../components/ui/PwaIcon';

export default function ResponsiveAssessmentReport({ client, assessment, previous }) {
	const metrics = metricsForAssessment(assessment);
	const primaryFields = new Set(['weight_kg', 'calculated_bmi', 'scale_bmi', 'body_fat_percentage']);
	const primaryMetrics = metrics.filter((metric) => primaryFields.has(metric.field)).slice(0, 3);
	const secondaryMetrics = metrics.filter((metric) => !primaryMetrics.includes(metric));

	return (
		<article className="pwa-card overflow-hidden">
			<header className="bg-white">
				<div className="flex flex-wrap items-center justify-between gap-4 border-b border-ricosty-100 px-5 py-4 sm:px-6">
					<img src="/images/brand/ricosty-logo.png" alt="Ricosty Emagrecimento e Estética" className="h-11 w-32 object-contain object-left" />
					<div className="text-right"><p className="text-[11px] font-bold uppercase tracking-[0.18em] text-ricosty-700">Documento clínico</p><p className="mt-1 text-xs font-semibold text-slate-600">Avaliação #{assessment.id}</p></div>
				</div>
				<div className="relative overflow-hidden bg-gradient-to-br from-ricosty-950 via-ricosty-900 to-amber-800 p-5 text-white sm:p-6">
					<div className="absolute -right-8 -top-12 h-40 w-40 rounded-full bg-white/10 blur-2xl" aria-hidden="true" />
					<p className="relative text-xs font-bold uppercase tracking-[0.16em] text-ricosty-100">Relatório de bioimpedância</p>
					<h2 className="mt-2 text-2xl font-bold">{client.full_name}</h2>
					<div className="mt-4 grid gap-x-4 gap-y-3 border-t border-white/20 pt-4 text-xs sm:grid-cols-3">
						<div><span className="block text-[11px] uppercase text-white/75">Medição</span><strong className="mt-0.5 block">{formatDate(assessment.evaluated_at)}</strong></div>
						<div><span className="block text-[11px] uppercase text-white/75">Equipamento</span><strong className="mt-0.5 block">{assessment.device_model}</strong></div>
						<div><span className="block text-[11px] uppercase text-white/75">Profissional</span><strong className="mt-0.5 block break-words">{assessment.professional_name ?? '-'}</strong></div>
					</div>
				</div>
			</header>

			<div className="p-4 sm:p-5">
				<p className="mb-3 text-xs font-bold uppercase tracking-[0.14em] text-ricosty-600">Principais resultados</p>
				<div className="grid gap-3 sm:grid-cols-3">
					{primaryMetrics.map((metric) => (
						<div key={metric.field} className="relative overflow-hidden rounded-2xl border border-ricosty-200 bg-gradient-to-br from-ricosty-50 to-white p-4 shadow-sm">
							<span className="absolute -right-5 -top-6 h-16 w-16 rounded-full bg-ricosty-100/70" aria-hidden="true" />
							<p className="relative text-[11px] font-bold uppercase tracking-wide text-ricosty-700">{metric.label}</p>
							<p className="relative mt-2 text-2xl font-extrabold text-ricosty-900">{formatMetric(assessment[metric.field], metric)}</p>
						</div>
					))}
				</div>
				<div className="mt-3 grid gap-3 sm:grid-cols-2">{secondaryMetrics.map((metric) => <div key={metric.field} className="rounded-xl border border-slate-200 bg-slate-50 p-3"><p className="text-[11px] font-bold uppercase tracking-wide text-slate-600">{metric.label}</p><p className="mt-1 break-words text-base font-bold text-slate-950">{formatMetric(assessment[metric.field], metric)}</p></div>)}</div>

				<section className="mt-5 rounded-2xl border border-ricosty-100 bg-ricosty-50/60 p-4">
					<h3 className="flex items-center gap-2 font-bold text-ricosty-950"><PwaIcon name="history" size={18} />Comparação com a avaliação anterior</h3>
					{previous ? (
						<>
							<p className="mt-1 text-xs text-slate-500">Anterior: {formatDate(previous.evaluated_at)} · {previous.device_model}</p>
							<div className="mt-3 space-y-2">
								{metrics.filter((metric) => !metric.text && previous[metric.field] !== null && previous[metric.field] !== undefined).map((metric) => (
									<div key={metric.field} className="grid grid-cols-[1fr_auto] items-center gap-3 rounded-lg bg-white px-3 py-2 text-xs">
										<span className="font-semibold text-slate-800">{metric.label}<span className="block font-normal text-slate-600">Anterior: {formatMetric(previous[metric.field], metric)}</span></span>
										<DeltaBadge current={assessment[metric.field]} previous={previous[metric.field]} value={metricDelta(assessment[metric.field], previous[metric.field], metric)} />
									</div>
								))}
							</div>
						</>
					) : <p className="mt-2 text-sm text-slate-600">Esta é a primeira avaliação disponível para comparação.</p>}
				</section>

				{assessment.notes ? <section className="mt-5 rounded-xl border border-violet-200 bg-violet-50 p-4"><h3 className="font-bold text-violet-950">Observação profissional</h3><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{assessment.notes}</p></section> : null}
				{assessment.is_canceled ? <p className="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">Esta avaliação foi cancelada e não pode ser compartilhada.</p> : null}
			</div>
		</article>
	);
}

function DeltaBadge({ current, previous, value }) {
	const delta = Number(current) - Number(previous);
	const icon = delta > 0 ? 'trendUp' : delta < 0 ? 'trendDown' : 'minus';
	const tone = delta > 0 ? 'bg-slate-100 text-slate-700' : delta < 0 ? 'bg-stone-100 text-stone-700' : 'bg-zinc-100 text-zinc-600';
	return <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 font-bold ring-1 ring-black/5 ${tone}`}><PwaIcon name={icon} size={13} />{value}</span>;
}
