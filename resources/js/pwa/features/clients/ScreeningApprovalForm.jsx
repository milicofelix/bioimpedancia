import React, { useState } from 'react';
import PwaButton from '../../components/ui/PwaButton';

const confirmations = [
	['recall_period_confirmed', 'Confirmo o período de recordação proposto.'],
	['questions_confirmed', 'Revisei e aprovo a redação das perguntas.'],
	['ranges_confirmed', 'Revisei e aprovo as faixas descritivas.'],
	['disclaimers_confirmed', 'Revisei os avisos, limites e linguagem não diagnóstica.'],
	['electronic_signature_confirmed', 'Confirmo esta aprovação eletrônica em meu nome e registro profissional.'],
];

function requestError(error) {
	const errors = error?.response?.data?.errors;
	return errors ? Object.values(errors).flat()[0] : error?.response?.data?.message || 'Não foi possível registrar a aprovação.';
}

export default function ScreeningApprovalForm({ questionnaire, onApproved }) {
	const [form, setForm] = useState({
		profession: '',
		professional_registration: '',
		notes: '',
		recall_period_confirmed: false,
		questions_confirmed: false,
		ranges_confirmed: false,
		disclaimers_confirmed: false,
		electronic_signature_confirmed: false,
	});
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');
	const allConfirmed = confirmations.every(([field]) => form[field]);

	function update(field, value) {
		setForm((current) => ({ ...current, [field]: value }));
	}

	async function submit(event) {
		event.preventDefault();
		if (saving || !allConfirmed || !window.confirm(`Aprovar e ativar definitivamente a versão ${questionnaire.version}?`)) return;
		setSaving(true); setError('');
		try {
			const { data } = await window.axios.patch(`/screening/questionnaires/${questionnaire.id}/approve`, form);
			onApproved(data.questionnaire);
		} catch (requestFailure) {
			setError(requestError(requestFailure));
		} finally {
			setSaving(false);
		}
	}

	return (
		<form onSubmit={submit} className="mt-4 rounded-2xl border border-amber-300 bg-amber-50/70 p-4">
			<p className="text-xs font-extrabold uppercase tracking-wide text-amber-900">Aprovação clínica/editorial necessária</p>
			<h4 className="mt-1 font-extrabold text-slate-900">{questionnaire.name}</h4>
			<p className="mt-1 text-xs leading-5 text-slate-600">{questionnaire.version} · {questionnaire.questions_count} perguntas · período de {questionnaire.recall_period_days} dias · {questionnaire.ranges_count} faixas descritivas</p>
			<p className="mt-3 rounded-xl bg-white/80 p-3 text-xs leading-5 text-slate-700"><strong>Limite obrigatório:</strong> {questionnaire.disclaimer}</p>

			<div className="mt-3 grid gap-3 sm:grid-cols-2">
				<label className="text-sm font-bold text-slate-700">Profissão<input value={form.profession} onChange={(event) => update('profession', event.target.value)} required maxLength="120" className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-normal" placeholder="Ex.: Nutricionista" /></label>
				<label className="text-sm font-bold text-slate-700">Conselho e registro<input value={form.professional_registration} onChange={(event) => update('professional_registration', event.target.value)} required maxLength="80" className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-normal" placeholder="Ex.: CRN 00000" /></label>
			</div>

			<fieldset className="mt-4 space-y-2"><legend className="text-sm font-extrabold text-slate-800">Confirmações obrigatórias</legend>{confirmations.map(([field, label]) => <label key={field} className="flex min-h-11 items-start gap-3 rounded-xl bg-white/80 p-3 text-sm leading-5 text-slate-700"><input type="checkbox" checked={form[field]} onChange={(event) => update(field, event.target.checked)} className="mt-0.5 h-5 w-5 shrink-0" /><span>{label}</span></label>)}</fieldset>

			<label className="mt-3 block text-sm font-bold text-slate-700">Observações da revisão<textarea value={form.notes} onChange={(event) => update('notes', event.target.value)} maxLength="2000" rows="3" className="pwa-focus mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="Opcional" /></label>
			{error ? <p className="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800" role="alert">{error}</p> : null}
			<PwaButton type="submit" disabled={!allConfirmed || saving} loading={saving} icon="check" className="mt-4 w-full">Aprovar e ativar versão</PwaButton>
		</form>
	);
}
