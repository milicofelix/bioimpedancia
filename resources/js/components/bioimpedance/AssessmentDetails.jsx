import React from 'react';
import {
	relaxmedicAdditionalMetricFields,
	relaxmedicMetricLabels,
} from '../../domain/bioimpedance/relaxmedic';
import { formatDate, numberBr } from '../../shared/formatters';

function metricValue(field, value) {
	if (value === null || value === undefined || value === '') return '-';
	if (['obesity_level', 'body_type'].includes(field)) return value;
	if (['resting_metabolism_kcal', 'body_age'].includes(field)) return new Intl.NumberFormat('pt-BR').format(value);

	return numberBr(value, 1);
}

export default function AssessmentDetails({ assessment }) {
	if (!assessment) return null;

	const isRelaxmedic = assessment.device_model === 'Relaxmedic';
	const metrics = isRelaxmedic
		? relaxmedicAdditionalMetricFields.filter((field) => assessment[field] !== null && assessment[field] !== undefined && assessment[field] !== '')
		: [];

	return (
		<div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
			<div className="flex flex-wrap items-center justify-between gap-2">
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-slate-500">Detalhes da avaliação</p>
					<p className="mt-1 text-sm font-semibold text-slate-900">Modelo: {assessment.device_model ?? 'Não informado'}</p>
				</div>
				<span className={`rounded-full px-3 py-1 text-xs font-bold ${isRelaxmedic ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-700'}`}>
					{isRelaxmedic ? 'Importada de imagem' : 'Entrada manual'}
				</span>
			</div>

			{metrics.length ? (
				<div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
					{metrics.map((field) => (
						<div key={field} className="rounded-xl border border-slate-200 bg-white p-3">
							<p className="text-[11px] font-bold uppercase tracking-wide text-slate-500">{relaxmedicMetricLabels[field]}</p>
							<p className="mt-1 text-base font-semibold text-slate-950">{metricValue(field, assessment[field])}</p>
						</div>
					))}
				</div>
			) : null}

			{isRelaxmedic && assessment.source_metadata ? (
				<div className="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs leading-5 text-sky-900">
					<p className="font-bold uppercase tracking-wide">Origem auditável</p>
					<p className="mt-1">Arquivo: {assessment.source_metadata.image_name} • imagem não armazenada</p>
					<p className="break-all">SHA-256: {assessment.source_metadata.image_sha256}</p>
					<p>Processada em {formatDate(assessment.source_metadata.processed_at)} • conferida em {formatDate(assessment.source_metadata.review_confirmed_at)}</p>
				</div>
			) : null}
		</div>
	);
}
