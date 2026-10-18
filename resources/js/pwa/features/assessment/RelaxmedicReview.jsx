import React, { useMemo, useState } from 'react';
import { relaxmedicMetricLabels } from '../../../domain/bioimpedance/relaxmedic';
import { localDatetimeNow } from '../../../shared/formatters';
import { relaxmedicIntegerFields, relaxmedicTextFields } from './relaxmedicAssessmentForm';

function fieldError(errors, field) {
	const value = errors[field];
	return Array.isArray(value) ? value[0] : value;
}

export default function RelaxmedicReview({ previewUrl, form, extraction, confirmed, errors, saving, online, onUpdate, onConfirm, onSubmit, onRestart }) {
	const [panel, setPanel] = useState('image');
	const flaggedFields = useMemo(() => new Set([
		...(extraction?.missing_fields ?? []),
		...(extraction?.suspicious_values ?? []).map((item) => (typeof item === 'string' ? item : item.field)).filter(Boolean),
	]), [extraction]);
	const generalError = fieldError(errors, 'source_metadata') ?? fieldError(errors, 'relaxmedic_review_confirmed');
	const measuredAtSuspicious = flaggedFields.has('measured_at') || !extraction?.measured_at;
	const warnings = (extraction?.suspicious_values ?? []).map((item) => item?.reason).filter(Boolean);

	return (
		<form onSubmit={onSubmit} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
			<div className="flex items-start justify-between gap-3">
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-emerald-700">2. Conferência humana</p>
					<h2 className="mt-1 text-lg font-bold text-slate-950">Compare e corrija os valores</h2>
				</div>
				<button type="button" onClick={onRestart} className="shrink-0 text-xs font-bold text-slate-600 underline">Trocar imagem</button>
			</div>

			<p className="mt-2 text-sm leading-6 text-slate-600">Campos em amarelo não foram encontrados ou precisam de atenção. Qualquer valor pode ser corrigido antes de salvar.</p>
			{warnings.length ? (
				<div role="alert" className="mt-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
					<p className="font-bold">A leitura encontrou pontos para conferir:</p>
					<ul className="mt-1 list-disc space-y-1 pl-5">{warnings.map((warning, index) => <li key={`${warning}-${index}`}>{warning}</li>)}</ul>
				</div>
			) : null}
			<div className="mt-4 grid grid-cols-2 gap-2 lg:hidden" role="tablist" aria-label="Conferência do relatório">
				<button type="button" role="tab" aria-selected={panel === 'image'} onClick={() => setPanel('image')} className={`rounded-xl px-3 py-2 text-sm font-bold ${panel === 'image' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'}`}>Imagem</button>
				<button type="button" role="tab" aria-selected={panel === 'values'} onClick={() => setPanel('values')} className={`rounded-xl px-3 py-2 text-sm font-bold ${panel === 'values' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'}`}>Valores extraídos</button>
			</div>

			<div className="mt-4 lg:grid lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-5">
				<div className={`${panel === 'image' ? 'block' : 'hidden'} lg:block`}>
					<div className="sticky top-20 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 p-2">
						<img src={previewUrl} alt="Relatório Relaxmedic para conferência" className="mx-auto max-h-[65vh] rounded-lg object-contain" />
					</div>
				</div>

				<div className={`${panel === 'values' ? 'block' : 'hidden'} space-y-4 lg:block`}>
					<label className={`block rounded-xl border p-3 text-sm font-semibold text-slate-700 ${fieldError(errors, 'evaluated_at') ? 'border-rose-400 bg-rose-50' : measuredAtSuspicious ? 'border-amber-400 bg-amber-50' : 'border-slate-200'}`}>
						Data e hora da medição
						{measuredAtSuspicious ? <span className="ml-2 rounded-full bg-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-900">Conferir</span> : null}
						<input type="datetime-local" required max={localDatetimeNow()} value={form.evaluated_at} onChange={(event) => onUpdate('evaluated_at', event.target.value)} className={`mt-1 w-full rounded-xl border px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-400 ${fieldError(errors, 'evaluated_at') ? 'border-rose-400 bg-rose-50' : 'border-slate-300'}`} />
						{fieldError(errors, 'evaluated_at') ? <span className="mt-1 block text-xs text-rose-700">{fieldError(errors, 'evaluated_at')}</span> : null}
					</label>

					<div className="grid gap-3 sm:grid-cols-2">
						{Object.entries(relaxmedicMetricLabels).map(([field, label]) => {
							const suspicious = flaggedFields.has(field);
							const error = fieldError(errors, field);
							return (
								<label key={field} className={`rounded-xl border p-3 text-sm font-semibold ${error ? 'border-rose-400 bg-rose-50' : suspicious ? 'border-amber-400 bg-amber-50' : 'border-slate-200 bg-white'} text-slate-700`}>
									<span className="flex items-center justify-between gap-2"><span>{label}</span>{suspicious ? <span className="rounded-full bg-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-900">Conferir</span> : null}</span>
									<input type="text" inputMode={relaxmedicTextFields.includes(field) ? 'text' : relaxmedicIntegerFields.includes(field) ? 'numeric' : 'decimal'} required={field === 'weight_kg'} value={form[field]} onChange={(event) => onUpdate(field, event.target.value)} className="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal outline-none focus:ring-2 focus:ring-emerald-400" />
									{error ? <span className="mt-1 block text-xs text-rose-700">{error}</span> : null}
								</label>
							);
						})}
					</div>

					<label className="block text-sm font-semibold text-slate-700">Observações<input type="text" maxLength={2000} value={form.notes} onChange={(event) => onUpdate('notes', event.target.value)} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-3 font-normal outline-none focus:ring-2 focus:ring-emerald-400" /></label>
				</div>
			</div>

			<div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
				<label className="flex cursor-pointer items-start gap-3 text-sm font-semibold text-emerald-950">
					<input type="checkbox" checked={confirmed} onChange={(event) => onConfirm(event.target.checked)} className="mt-0.5 h-5 w-5 rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500" />
					<span>Conferi a imagem e confirmo que os valores acima estão corretos.</span>
				</label>
			</div>
			{generalError ? <p role="alert" className="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{generalError}</p> : null}
			<button type="submit" disabled={!confirmed || saving || !online} className="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">{saving ? 'Criando avaliação...' : 'Confirmar e criar avaliação'}</button>
		</form>
	);
}
