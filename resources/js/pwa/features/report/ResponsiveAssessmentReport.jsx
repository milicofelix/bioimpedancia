import React from 'react';
import { formatDate } from '../../../shared/formatters';
import { formatMetric, metricDelta, metricsForAssessment } from './reportMetrics';

export default function ResponsiveAssessmentReport({ client, assessment, previous }) {
	const metrics = metricsForAssessment(assessment);

	return (
		<article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
			<header className="bg-gradient-to-br from-slate-950 to-slate-800 p-5 text-white">
				<p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-300">Relatório de bioimpedância</p>
				<h2 className="mt-2 text-xl font-bold">{client.full_name}</h2>
				<div className="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-200">
					<span className="rounded-full bg-white/10 px-3 py-1">{formatDate(assessment.evaluated_at)}</span>
					<span className="rounded-full bg-white/10 px-3 py-1">{assessment.device_model}</span>
					<span className="rounded-full bg-white/10 px-3 py-1">Profissional: {assessment.professional_name ?? '-'}</span>
				</div>
			</header>

			<div className="p-4 sm:p-5">
				<div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
					{metrics.map((metric) => (
						<div key={metric.field} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
							<p className="text-[10px] font-bold uppercase tracking-wide text-slate-500">{metric.label}</p>
							<p className="mt-1 text-base font-bold text-slate-950">{formatMetric(assessment[metric.field], metric)}</p>
						</div>
					))}
				</div>

				<section className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4">
					<h3 className="font-bold text-sky-950">Comparação com a avaliação anterior</h3>
					{previous ? (
						<>
							<p className="mt-1 text-xs text-sky-700">Anterior: {formatDate(previous.evaluated_at)} · {previous.device_model}</p>
							<div className="mt-3 space-y-2">
								{metrics.filter((metric) => !metric.text && previous[metric.field] !== null && previous[metric.field] !== undefined).map((metric) => (
									<div key={metric.field} className="grid grid-cols-[1fr_auto] items-center gap-3 rounded-lg bg-white px-3 py-2 text-xs">
										<span className="font-semibold text-slate-700">{metric.label}<span className="block font-normal text-slate-400">Anterior: {formatMetric(previous[metric.field], metric)}</span></span>
										<span className="rounded-full bg-sky-100 px-2 py-1 font-bold text-sky-800">{metricDelta(assessment[metric.field], previous[metric.field], metric)}</span>
									</div>
								))}
							</div>
						</>
					) : <p className="mt-2 text-sm text-sky-800">Esta é a primeira avaliação disponível para comparação.</p>}
				</section>

				{assessment.notes ? <section className="mt-5 rounded-xl border border-violet-200 bg-violet-50 p-4"><h3 className="font-bold text-violet-950">Observação profissional</h3><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{assessment.notes}</p></section> : null}
				{assessment.is_canceled ? <p className="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">Esta avaliação foi cancelada e não pode ser compartilhada.</p> : null}
			</div>
		</article>
	);
}
