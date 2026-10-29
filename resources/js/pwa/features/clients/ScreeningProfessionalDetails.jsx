import React, { useEffect, useState } from 'react';
import { formatDate } from '../../../shared/formatters';
import PwaButton from '../../components/ui/PwaButton';

function requestErrorMessage(error) {
	const errors = error?.response?.data?.errors;
	return errors ? Object.values(errors).flat()[0] : error?.response?.data?.message || 'Não foi possível carregar os detalhes da triagem.';
}

export default function ScreeningProfessionalDetails({ sessionId, onClose }) {
	const [details, setDetails] = useState(null);
	const [professionalNotes, setProfessionalNotes] = useState('');
	const [referralNotes, setReferralNotes] = useState('');
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');
	const [feedback, setFeedback] = useState('');

	useEffect(() => {
		let active = true;
		setLoading(true); setError(''); setFeedback('');
		window.axios.get(`/screening/sessions/${sessionId}`).then(({ data }) => {
			if (!active) return;
			setDetails(data.session);
			setProfessionalNotes(data.session.professional_review?.professional_notes ?? '');
			setReferralNotes(data.session.professional_review?.referral_notes ?? '');
		}).catch((requestError) => {
			if (active) setError(requestErrorMessage(requestError));
		}).finally(() => {
			if (active) setLoading(false);
		});

		return () => { active = false; };
	}, [sessionId]);

	async function saveReview() {
		if (saving) return;
		setSaving(true); setError(''); setFeedback('');
		try {
			const { data } = await window.axios.patch(`/screening/sessions/${sessionId}/professional-review`, {
				professional_notes: professionalNotes || null,
				referral_notes: referralNotes || null,
			});
			setDetails(data.session);
			setProfessionalNotes(data.session.professional_review?.professional_notes ?? '');
			setReferralNotes(data.session.professional_review?.referral_notes ?? '');
			setFeedback('Registro profissional salvo e incluído na auditoria.');
		} catch (requestError) {
			setError(requestErrorMessage(requestError));
		} finally {
			setSaving(false);
		}
	}

	return (
		<section className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4" aria-labelledby="screening-professional-details-title">
			<div className="flex items-start justify-between gap-3">
				<div><p className="text-xs font-bold uppercase tracking-wide text-teal-700">Uso interno</p><h4 id="screening-professional-details-title" className="mt-1 font-extrabold text-slate-900">Detalhes profissionais da triagem</h4></div>
				<PwaButton type="button" variant="ghost" onClick={onClose} className="min-h-11 px-3">Fechar</PwaButton>
			</div>

			{loading ? <div className="mt-4 space-y-2" role="status"><div className="h-16 animate-pulse rounded-xl bg-slate-200" /><div className="h-28 animate-pulse rounded-xl bg-slate-200" /></div> : null}
			{error ? <p className="mt-3 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-800" role="alert">{error}</p> : null}
			{feedback ? <p className="mt-3 rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800" role="status">{feedback}</p> : null}

			{details ? <div className="mt-4 space-y-4">
				<div className="grid gap-2 rounded-xl bg-white p-3 text-sm sm:grid-cols-2">
					<p><strong>Cliente:</strong> {details.client_name}</p><p><strong>Profissional:</strong> {details.requested_by_name || 'Não informado'}</p>
					<p className="sm:col-span-2"><strong>Questionário:</strong> {details.questionnaire_name} · {details.questionnaire_version}</p>
					<p><strong>Criada:</strong> {formatDate(details.created_at)}</p><p><strong>Concluída:</strong> {formatDate(details.finalized_at)}</p>
					<p><strong>Resultado:</strong> {details.questionnaire_code === 'VITA-TAA' ? 'Escolha visual descritiva, sem pontuação' : `${details.result.total_score} pontos · ${details.result.description}`}</p><p><strong>Documento:</strong> {details.result.document_authenticity_code}</p>
					<p><strong>Retenção prevista:</strong> {formatDate(details.retention_expires_at)}</p><p><strong>Fluxo:</strong> {details.consent.minor_flow ? 'Menor com responsável legal' : 'Cliente adulto'}</p>
				</div>

				<div><h5 className="text-sm font-extrabold text-slate-800">Respostas</h5><div className="mt-2 space-y-2">{details.answers.map((answer) => <article key={answer.question_code} className={`rounded-xl border p-3 ${answer.requires_individual_attention ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white'}`}><div className="flex items-start justify-between gap-2"><p className="text-sm font-semibold text-slate-800">{answer.question_text}</p>{answer.requires_individual_attention ? <span className="shrink-0 rounded-full bg-amber-200 px-2 py-1 text-[10px] font-bold uppercase text-amber-900">Conversar</span> : null}</div><p className="mt-2 text-sm text-slate-600">{answer.response_label}{answer.question_code === 'VIS01' ? ' · escolha descritiva, sem pontuação' : ` · ${answer.score} ponto(s)`}</p></article>)}</div></div>

				<div className="rounded-xl border border-teal-200 bg-teal-50 p-3"><h5 className="text-sm font-extrabold text-teal-900">Consentimento</h5><p className="mt-1 text-sm text-teal-900">{details.consent.text}</p>{details.consent.minor_flow ? <p className="mt-2 text-sm font-semibold text-teal-900">Responsável: {details.consent.guardian_name} · {details.consent.guardian_relationship}</p> : null}<p className="mt-2 text-xs text-teal-800">Aceito em {formatDate(details.consent.accepted_at)}{details.consent.minor_flow ? ` · responsável confirmou em ${formatDate(details.consent.guardian_consent_accepted_at)}` : ''}</p></div>

				<label className="block text-sm font-bold text-slate-800">Observações profissionais<textarea value={professionalNotes} onChange={(event) => setProfessionalNotes(event.target.value)} maxLength={5000} rows={5} className="pwa-focus mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="Contextualize o resultado sem registrar diagnóstico automático." /></label>
				<label className="block text-sm font-bold text-slate-800">Encaminhamento<textarea value={referralNotes} onChange={(event) => setReferralNotes(event.target.value)} maxLength={5000} rows={4} className="pwa-focus mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="Registre eventual orientação ou encaminhamento profissional." /></label>
				<PwaButton type="button" onClick={saveReview} loading={saving} disabled={saving} icon="clipboard" className="w-full">Salvar registro profissional</PwaButton>

				<details className="rounded-xl border border-slate-200 bg-white p-3"><summary className="min-h-11 cursor-pointer py-2 text-sm font-extrabold text-slate-800">Histórico de auditoria ({details.audits.length})</summary><ol className="mt-2 space-y-3 border-l border-slate-200 pl-4">{details.audits.map((audit, index) => <li key={`${audit.action}-${audit.created_at}-${index}`}><p className="text-sm font-semibold text-slate-800">{audit.description}</p><p className="text-xs text-slate-500">{formatDate(audit.created_at)} · {audit.user_name}</p></li>)}</ol></details>
			</div> : null}
		</section>
	);
}
