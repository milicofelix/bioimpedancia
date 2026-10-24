import React, { useEffect, useState } from 'react';
import { apiErrorMessage, apiValidationErrors, approveAssessmentCareGuidance, generateAssessmentCareGuidance } from '../../../services/bioimpedanceApi';
import useActionLock from '../../../hooks/useActionLock';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';
import CareGuidanceProgress, { careGuidanceProcessingSteps } from './CareGuidanceProgress';
import CareGuidanceEvidence from './CareGuidanceEvidence';

export default function CareGuidanceAssistant({ assessment, online, onApproved }) {
	const runAction = useActionLock();
	const [assistant, setAssistant] = useState(null);
	const [draft, setDraft] = useState('');
	const [generating, setGenerating] = useState(false);
	const [processingStep, setProcessingStep] = useState(0);
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');
	const edited = Boolean(assistant?.suggestion && draft !== assistant.suggestion);
	const blocked = Boolean(assistant && assistant.status !== 'generated');

	useEffect(() => {
		if (!generating) return undefined;

		const interval = window.setInterval(() => {
			setProcessingStep((current) => Math.min(current + 1, careGuidanceProcessingSteps.length - 1));
		}, 1200);

		return () => window.clearInterval(interval);
	}, [generating]);

	useEffect(() => {
		setAssistant(null);
		setDraft('');
		setError('');
		setGenerating(false);
		setProcessingStep(0);
		setSaving(false);
	}, [assessment.id]);

	async function generate() {
		return runAction('generate-care-guidance', async () => {
			if (!online || generating) return;
			setGenerating(true);
			setProcessingStep(0);
			setError('');
			try {
				const result = await generateAssessmentCareGuidance(assessment.id);
				setAssistant(result);
				setDraft(result.suggestion ?? '');
			} catch (requestError) {
				setError(apiErrorMessage(requestError, 'Não foi possível gerar orientações seguras.'));
			} finally {
				setGenerating(false);
			}
		});
	}

	async function approve() {
		return runAction('approve-care-guidance', async () => {
			if (!assistant || draft.trim().length < 100 || saving || !online) return;
			setSaving(true);
			setError('');
			try {
				const result = await approveAssessmentCareGuidance(assessment.id, {
					care_guidance: draft.trim(),
					review_action: edited ? 'edited' : 'approved',
					assistant_output_id: assistant.output_id,
				});
				onApproved(result.client, result.assessment);
				setAssistant(null);
				setDraft('');
			} catch (requestError) {
				const errors = apiValidationErrors(requestError);
				setError(errors.care_guidance?.[0] ?? apiErrorMessage(requestError, 'Não foi possível aprovar as orientações.'));
			} finally {
				setSaving(false);
			}
		});
	}

	if (generating) return <CareGuidanceProgress currentStep={processingStep} />;

	return (
		<PwaCard className="border-teal-200 p-5">
			<div className="flex items-start gap-3"><span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal-100 text-teal-800"><PwaIcon name="sparkles" size={21} /></span><div><p className="text-xs font-bold uppercase tracking-wide text-teal-700">Agente de orientações</p><h2 className="text-lg font-bold text-slate-950">Próximos cuidados</h2></div></div>
			<p className="mt-3 text-sm leading-6 text-slate-700">Gera orientações educativas de alimentação, movimento e acompanhamento com base na observação aprovada. Não realiza diagnóstico nem prescrição.</p>
			{assessment.care_guidance ? <div className="mt-4 rounded-xl border border-teal-200 bg-teal-50 p-4"><p className="text-xs font-bold uppercase text-teal-800">Orientações aprovadas no relatório</p><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{assessment.care_guidance}</p></div> : null}
			{assistant ? <div className="mt-4 space-y-4">
				<div className="rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-5 text-amber-950"><strong>Revisão obrigatória:</strong> confirme se as orientações são adequadas ao histórico e às limitações conhecidas do cliente.</div>
				{assistant.validation_errors?.length ? <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><strong>A sugestão foi bloqueada:</strong><ul className="mt-1 list-disc pl-5">{assistant.validation_errors.map((message) => <li key={message}>{message}</li>)}</ul></div> : null}
				<label className="block text-sm font-bold text-slate-800">Orientações sugeridas<textarea rows="14" maxLength={4000} value={draft} onChange={(event) => setDraft(event.target.value)} disabled={blocked} className="pwa-focus mt-2 w-full rounded-xl border border-teal-200 bg-white p-3 font-normal leading-6 disabled:bg-slate-100" /></label>
				{edited ? <p className="text-xs font-bold text-violet-700">Texto editado pelo profissional.</p> : null}
				<CareGuidanceEvidence assistant={assistant} />
				<div className="grid gap-2 sm:grid-cols-2"><PwaButton type="button" variant="secondary" onClick={() => { setAssistant(null); setDraft(''); }}>{blocked ? 'Voltar e tentar novamente' : 'Descartar'}</PwaButton><PwaButton type="button" icon="check" onClick={approve} disabled={blocked || saving || draft.trim().length < 100 || !online} loading={saving}>{saving ? 'Aprovando...' : 'Aprovar para o relatório'}</PwaButton></div>
			</div> : <PwaButton type="button" icon="sparkles" onClick={generate} disabled={!online} className="mt-4 w-full">{assessment.care_guidance ? 'Gerar nova sugestão' : 'Sugerir próximos cuidados'}</PwaButton>}
			{error ? <p role="alert" className="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">{error}</p> : null}
			<p className="mt-3 text-xs leading-5 text-slate-600">Vitaminas e suplementos dependem de avaliação individual e não são indicados automaticamente pelo agente.</p>
		</PwaCard>
	);
}
