import React from 'react';
import { formatDate } from '../../../shared/formatters';
import { assessmentComparability, commonExtendedMetricComparisons, commonImpedanceComparisons, comparisonProfileLabel, formatMetric, metricDelta, metricsForAssessment } from './reportMetrics';
import PwaIcon from '../../components/ui/PwaIcon';
import SegmentalBodyMap from '../../../components/bioimpedance/SegmentalBodyMap';
import SegmentalComparison from '../../../components/bioimpedance/SegmentalComparison';
import VitaScanBrand from '../../../components/brand/VitaScanBrand';

export default function ResponsiveAssessmentReport({ client, assessment, previous }) {
	const metrics = metricsForAssessment(assessment);
	const primaryFields = new Set(['weight_kg', 'calculated_bmi', 'scale_bmi', 'body_fat_percentage']);
	const primaryMetrics = metrics.filter((metric) => primaryFields.has(metric.field)).slice(0, 3);
	const secondaryMetrics = metrics.filter((metric) => !primaryMetrics.includes(metric));
	const specifications = assessment.device_technical_specifications;
	const informationalReferences = assessment.analysis?.reference?.informational_references ?? [];
	const comparability = assessmentComparability(assessment, previous);
	const extendedComparisons = commonExtendedMetricComparisons(assessment, previous);
	const impedanceComparisons = commonImpedanceComparisons(assessment, previous);

	return (
		<article className="pwa-card min-w-0 overflow-hidden">
			<header className="bg-white">
				<div className="flex flex-wrap items-center justify-between gap-4 border-b border-ricosty-100 px-5 py-4 sm:px-6">
					<VitaScanBrand headingLevel="h2" size="compact" showTagline={false} />
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

			<div className="min-w-0 p-4 sm:p-5">
				{specifications ? <p className="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-700"><strong>Especificações do equipamento:</strong> {specifications.electrodes} eletrodos · dupla frequência de {specifications.frequencies_khz.join(' kHz + ')} kHz · temperatura operacional de {specifications.minimum_temperature_c} °C a {specifications.maximum_temperature_c} °C. O manual não informa margem de erro; os valores são apresentados conforme o relatório importado.</p> : null}
				<p className="mb-3 text-xs font-bold uppercase tracking-[0.14em] text-ricosty-600">Principais resultados</p>
				<div className="grid gap-3 sm:grid-cols-3">
					{primaryMetrics.map((metric) => (
						<div key={metric.field} className="relative overflow-hidden rounded-2xl border border-ricosty-200 bg-gradient-to-br from-ricosty-50 to-white p-4 shadow-sm">
							<span className="absolute -right-5 -top-6 h-16 w-16 rounded-full bg-ricosty-100/70" aria-hidden="true" />
							<p className="relative text-[11px] font-bold uppercase tracking-wide text-ricosty-700">{metric.label}</p>
							<p className="relative mt-2 text-2xl font-extrabold text-ricosty-900">{formatMetric(assessment[metric.field], metric)}</p>
							{metric.type ? <p className="relative mt-2 text-[10px] font-bold uppercase tracking-wide text-sky-700">{metric.type}</p> : null}
							{metric.note ? <p className="relative mt-1 text-xs leading-5 text-slate-600">{metric.note}</p> : null}
						</div>
					))}
				</div>
				<div className="mt-3 grid gap-3 sm:grid-cols-2">{secondaryMetrics.map((metric) => <div key={metric.field} className="rounded-xl border border-slate-200 bg-slate-50 p-3"><p className="text-[11px] font-bold uppercase tracking-wide text-slate-600">{metric.label}</p><p className="mt-1 break-words text-base font-bold text-slate-950">{formatMetric(assessment[metric.field], metric)}</p>{metric.type ? <p className="mt-2 text-[10px] font-bold uppercase tracking-wide text-sky-700">{metric.type}</p> : null}{metric.note ? <p className="mt-1 text-xs leading-5 text-slate-600">{metric.note}</p> : null}</div>)}</div>

				{['Relaxmedic', 'Bodyscan Pro'].includes(assessment.device_model) ? <section className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-xs leading-5 text-sky-950"><strong>Como interpretar:</strong> os dados de composição corporal são estimativas produzidas por algoritmos do equipamento. Coerência matemática entre massa gorda, massa magra e peso não comprova precisão fisiológica. Compare tendências em condições semelhantes e não use IMC ou “peso ideal” isoladamente para definir objetivos.</section> : null}
				{informationalReferences.length ? <section className="mt-5 rounded-2xl border border-slate-200 bg-white p-4"><h3 className="font-bold text-slate-950">Referências informativas do manual</h3><p className="mt-1 text-xs leading-5 text-slate-600">Faixas genéricas, sem separação por sexo ou idade e sem classificação clínica. O manual descreve uso doméstico e não apresenta validação clínica. Apenas o IMC utiliza classificação automática convencional.</p><div className="mt-3 grid gap-2 sm:grid-cols-2">{informationalReferences.map((reference) => <div key={reference.key} className="rounded-xl bg-slate-50 p-3"><p className="text-xs font-bold text-slate-800">{reference.label}</p><p className="mt-1 text-base font-extrabold text-slate-950">{reference.manual_range}</p><p className="mt-1 text-xs leading-5 text-slate-600">{reference.note}</p></div>)}</div></section> : null}
				{assessment.segmental_measurements?.length ? <section className="mt-5 min-w-0"><p className="mb-3 break-words text-xs font-bold uppercase tracking-[0.14em] text-ricosty-600">Análise segmentar Bodyscan</p><div className="grid min-w-0 grid-cols-1 gap-4 print:grid-cols-2"><SegmentalBodyMap measurements={assessment.segmental_measurements} type="fat" /><SegmentalBodyMap measurements={assessment.segmental_measurements} type="muscle" /></div></section> : null}
				{assessment.segmental_measurements?.length ? <SegmentalComparison current={assessment.segmental_measurements} previous={previous?.segmental_measurements ?? []} previousDate={previous ? formatDate(previous.evaluated_at) : null} comparability={comparability} /> : null}
				{assessment.age_at_assessment >= 60 ? <section className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-950"><strong>Atenção à funcionalidade:</strong> em pessoas com 60 anos ou mais, o planejamento deve considerar preservação de massa, força, equilíbrio, mobilidade e autonomia. O percentual muscular estimado não comprova, isoladamente, força ou função preservada.</section> : null}

				<section className="mt-5 rounded-2xl border border-ricosty-100 bg-ricosty-50/60 p-4">
					<h3 className="flex items-center gap-2 font-bold text-ricosty-950"><PwaIcon name="history" size={18} />Comparação com a avaliação anterior</h3>
					<div className={`mt-3 rounded-xl border p-3 text-xs leading-5 ${comparability.level === 'limited' || comparability.warnings.length ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-sky-200 bg-sky-50 text-sky-950'}`}>
						<strong>{comparability.label}.</strong> {comparability.message}
						{previous ? <p className="mt-1"><strong>Anterior:</strong> {comparisonProfileLabel(comparability.previousProfile)}<br /><strong>Atual:</strong> {comparisonProfileLabel(comparability.currentProfile)}</p> : null}
						{comparability.warnings.map((warning) => <p key={warning} className="mt-1">{warning}</p>)}
					</div>
					{previous ? (
						<>
							<p className="mt-1 text-xs text-slate-500">Anterior: {formatDate(previous.evaluated_at)} · {previous.device_model}</p>
							<div className="mt-3 space-y-2">
								{metrics.filter((metric) => !metric.text && previous[metric.field] !== null && previous[metric.field] !== undefined).map((metric) => (
									<div key={metric.field} className="grid grid-cols-[1fr_auto] items-center gap-3 rounded-lg bg-white px-3 py-3 text-xs">
										<div>
											<p className="font-semibold text-slate-800">{metric.label}</p>
											<p className="mt-1 flex flex-wrap items-center gap-2 text-slate-600">
												<span><span className="mr-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">Anterior</span>{formatMetric(previous[metric.field], metric)}</span>
												<span aria-hidden="true" className="text-ricosty-500">→</span>
												<strong className="text-slate-950"><span className="mr-1 text-[10px] uppercase tracking-wide text-slate-500">Atual</span>{formatMetric(assessment[metric.field], metric)}</strong>
											</p>
										</div>
										<DeltaBadge current={assessment[metric.field]} previous={previous[metric.field]} value={metricDelta(assessment[metric.field], previous[metric.field], metric)} />
									</div>
								))}
							</div>
							{extendedComparisons.length ? <div className="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white"><p className="bg-slate-50 px-3 py-2 text-xs font-bold text-slate-800">Métricas estendidas presentes nos dois relatórios</p>{extendedComparisons.map((metric) => <div key={metric.key} className="grid grid-cols-[1fr_auto] gap-3 border-t border-slate-100 px-3 py-2 text-xs"><span>{metric.label}</span><strong>{metric.previous.toLocaleString('pt-BR')} {metric.unit} → {metric.current.toLocaleString('pt-BR')} {metric.unit}</strong></div>)}</div> : null}
							{impedanceComparisons.length ? <div className="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white"><p className="bg-slate-50 px-3 py-2 text-xs font-bold text-slate-800">Impedância presente nas duas avaliações</p>{impedanceComparisons.map((reading) => <div key={`${reading.frequency_khz}-${reading.region}-${reading.side}`} className="grid grid-cols-[1fr_auto] gap-3 border-t border-slate-100 px-3 py-2 text-xs"><span>{reading.frequency_khz} kHz · {impedanceLabel(reading)}</span><strong>{reading.previous_ohms.toLocaleString('pt-BR')} Ω → {reading.current_ohms.toLocaleString('pt-BR')} Ω</strong></div>)}</div> : null}
						</>
					) : <p className="mt-2 text-sm text-slate-600">Esta é a primeira avaliação disponível para comparação.</p>}
				</section>

				{assessment.notes ? <section className="mt-5 rounded-xl border border-violet-200 bg-violet-50 p-4"><h3 className="font-bold text-violet-950">Observação profissional</h3><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{assessment.notes}</p></section> : null}
				{assessment.care_guidance ? <section className="mt-5 rounded-xl border border-teal-200 bg-teal-50 p-4"><h3 className="font-bold text-teal-950">Orientações aprovadas</h3><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{assessment.care_guidance}</p>{assessment.care_guidance_sources?.length ? <div className="mt-3 border-t border-teal-200 pt-3"><p className="text-xs font-bold uppercase text-teal-800">Referências</p><ul className="mt-1 space-y-1 text-xs text-slate-600">{assessment.care_guidance_sources.map((source) => <li key={source.id}>{source.document}{source.version ? ` · versão ${source.version}` : ''} — {source.section}</li>)}</ul></div> : null}<p className="mt-3 text-xs text-slate-600">Orientações educativas aprovadas pelo profissional; não substituem avaliação médica, nutricional ou de educação física.</p></section> : null}
				{assessment.is_canceled ? <p className="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">Esta avaliação foi cancelada e não pode ser compartilhada.</p> : null}
			</div>
		</article>
	);
}

function impedanceLabel(reading) {
	const regions = { arm: 'Braço', trunk: 'Tronco', leg: 'Perna' };
	const sides = { left: 'esquerdo', right: 'direito', center: 'centro' };
	return `${regions[reading.region] ?? reading.region} ${sides[reading.side] ?? reading.side}`;
}

function DeltaBadge({ current, previous, value }) {
	const delta = Number(current) - Number(previous);
	const icon = delta > 0 ? 'trendUp' : delta < 0 ? 'trendDown' : 'minus';
	const tone = delta > 0 ? 'bg-slate-100 text-slate-700' : delta < 0 ? 'bg-stone-100 text-stone-700' : 'bg-zinc-100 text-zinc-600';
	return <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 font-bold ring-1 ring-black/5 ${tone}`}><PwaIcon name={icon} size={13} />{value}</span>;
}
