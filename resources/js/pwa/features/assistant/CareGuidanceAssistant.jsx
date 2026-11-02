import React, { useEffect, useState } from 'react';
import { apiErrorMessage, apiValidationErrors, approveAssessmentCareGuidance, generateAssessmentCareGuidance } from '../../../services/bioimpedanceApi';
import useActionLock from '../../../hooks/useActionLock';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';
import CareGuidanceProgress from './CareGuidanceProgress';
import CareGuidanceEvidence from './CareGuidanceEvidence';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaInfoCard from '../../components/ui/PwaInfoCard';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';

export default function CareGuidanceAssistant({ assessment, online, onApproved }) {
	const runAction = useActionLock();
	const [assistant, setAssistant] = useState(null);
	const [draft, setDraft] = useState('');
	const [generating, setGenerating] = useState(false);
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');
	const edited = Boolean(assistant?.suggestion && draft !== assistant.suggestion);
	const blocked = Boolean(assistant && (assistant.status !== 'generated' || (assistant.validation_status && assistant.validation_status !== 'passed')));

	useEffect(() => {
		setAssistant(null);
		setDraft('');
		setError('');
		setGenerating(false);
		setSaving(false);
	}, [assessment.id]);

	async function generate() {
		return runAction('generate-care-guidance', async () => {
			if (!online || generating) return;
			setGenerating(true);
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

	if (generating) return <CareGuidanceProgress />;

	return (
		<PwaCard className="border-teal-200 p-5">
			<PwaSectionHeader icon="sparkles" eyebrow="Orientação" title="Próximos cuidados" description="Orientações educativas baseadas na observação aprovada, sempre sujeitas à revisão profissional." tone="teal" />
			{assessment.care_guidance ? <div className="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4"><p className="flex items-center gap-2 text-xs font-bold uppercase text-emerald-800"><PwaIcon name="check" size={15} />Conteúdo aprovado para o relatório</p><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{assessment.care_guidance}</p></div> : null}
			{assistant ? <div className="mt-4 space-y-4">
				<PwaAlert tone="attention" title="Pendente de revisão">Confirme se as orientações são adequadas ao histórico e às limitações conhecidas do cliente.</PwaAlert>
				{assistant.validation_errors?.length ? <PwaAlert tone="error" title="Orientação bloqueada"><ul className="list-disc pl-5">{assistant.validation_errors.map((message) => <li key={message}>{message}</li>)}</ul></PwaAlert> : null}
				{blocked ? <PwaAlert tone="info" title="Aprovação indisponível">Gere uma nova sugestão para que somente um conteúdo aprovado pelas regras de segurança possa seguir para o relatório.</PwaAlert> : null}
				<label className="block text-sm font-bold text-slate-800">Orientações sugeridas<textarea rows="14" maxLength={4000} value={draft} onChange={(event) => setDraft(event.target.value)} disabled={blocked} className="pwa-focus mt-2 w-full rounded-xl border border-teal-200 bg-white p-3 font-normal leading-6 disabled:bg-slate-100" /></label>
				{edited ? <p className="text-xs font-bold text-violet-700">Texto editado pelo profissional.</p> : null}
				<CareGuidanceEvidence assistant={assistant} />
				<div className="grid gap-2 sm:grid-cols-2"><PwaButton type="button" variant="secondary" onClick={() => { setAssistant(null); setDraft(''); }}>{blocked ? 'Voltar e tentar novamente' : 'Descartar'}</PwaButton><PwaButton type="button" icon="check" onClick={approve} disabled={blocked || saving || draft.trim().length < 100 || !online} loading={saving}>{saving ? 'Aprovando...' : blocked ? 'Aprovação bloqueada' : 'Aprovar para o relatório'}</PwaButton></div>
			</div> : <PwaButton type="button" icon="sparkles" onClick={generate} disabled={!online} className="mt-4 w-full">{assessment.care_guidance ? 'Gerar nova sugestão' : 'Sugerir próximos cuidados'}</PwaButton>}
			{error ? <PwaAlert tone="error" title="Não foi possível concluir a ação" className="mt-3">{error}</PwaAlert> : null}
			<PwaInfoCard icon="shield" title="Limite do assistente" className="mt-3"><p className="text-xs leading-5 text-slate-600">Vitaminas e suplementos dependem de avaliação individual e não são indicados automaticamente.</p></PwaInfoCard>
		</PwaCard>
	);
}
