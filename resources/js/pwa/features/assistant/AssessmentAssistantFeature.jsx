import React, { useEffect, useState } from 'react';
import { apiErrorMessage, apiValidationErrors, approveAssessmentObservation, generateAssessmentObservation } from '../../../services/bioimpedanceApi';
import { formatDate, numberBr } from '../../../shared/formatters';
import { canUseClinicalFeatures } from '../clients/clientAccess';
import AssistantProgress from './AssistantProgress';
import ObservationSuggestion from './ObservationSuggestion';
import useOnlineStatus from '../../hooks/useOnlineStatus';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaJourneyHeader from '../../components/ui/PwaJourneyHeader';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaIcon from '../../components/ui/PwaIcon';
import PwaInfoCard from '../../components/ui/PwaInfoCard';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';
import PwaState from '../../components/ui/PwaState';
import useActionLock from '../../../hooks/useActionLock';
import CareGuidanceAssistant from './CareGuidanceAssistant';

export default function AssessmentAssistantFeature({ client, assessment, currentUser, onBackToClients, onApproved, onOpenReport, canOpenReport = true }) {
	const runAction = useActionLock();
	const [assistant, setAssistant] = useState(null);
	const [draft, setDraft] = useState('');
	const [generating, setGenerating] = useState(false);
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');
	const [approved, setApproved] = useState(false);
	const [approvedAsEdited, setApprovedAsEdited] = useState(false);
	const [approvedAssessment, setApprovedAssessment] = useState(null);
	const isOnline = useOnlineStatus();

	useEffect(() => {
		setAssistant(null);
		setDraft('');
		setError('');
		setApproved(false);
		setApprovedAsEdited(false);
		setApprovedAssessment(null);
	}, [assessment?.id]);

	async function generate() {
		return runAction('generate-observation', async () => {
			if (!assessment || assessment.is_canceled || generating || !isOnline) return;
			setGenerating(true);
			setAssistant(null);
			setDraft('');
			setError('');
			setApproved(false);
			setApprovedAsEdited(false);

			try {
				const result = await generateAssessmentObservation(assessment.id);
				setAssistant(result);
				setDraft(result?.suggestion ?? '');
			} catch (requestError) {
				setError(apiErrorMessage(requestError, 'O assistente está indisponível no momento. Tente novamente mais tarde.'));
			} finally {
				setGenerating(false);
			}
		});
	}

	async function approve() {
		return runAction('approve-observation', async () => {
			if (!assistant || draft.trim().length < 20 || saving || !isOnline) return;
			setSaving(true);
			setError('');

			try {
				const editedByProfessional = assistant.suggestion !== draft;
				const result = await approveAssessmentObservation(assessment.id, {
					notes: draft.trim(),
					review_action: editedByProfessional ? 'edited' : 'approved',
					assistant_output_id: assistant.output_id,
				});
				onApproved(result.client, result.assessment);
				setApprovedAssessment(result.assessment);
				setApproved(true);
				setApprovedAsEdited(editedByProfessional);
				setAssistant(null);
				setDraft('');
			} catch (requestError) {
				const validationErrors = apiValidationErrors(requestError);
				setError(validationErrors.notes?.[0] ?? validationErrors.bioimpedance_assessment_id?.[0] ?? apiErrorMessage(requestError, 'Não foi possível adicionar a observação.'));
			} finally {
				setSaving(false);
			}
		});
	}

	function discard() {
		setAssistant(null);
		setDraft('');
		setError('');
		setApproved(false);
		setApprovedAsEdited(false);
	}

	if (!client || !assessment) {
		return <AssistantState title="Selecione uma avaliação" description="Abra o histórico de um cliente e escolha a avaliação que deseja revisar." onBack={onBackToClients} />;
	}

	if (!canUseClinicalFeatures(currentUser)) {
		return <AssistantState title="Acesso restrito" description="Somente profissionais e administradores podem gerar e aprovar observações." onBack={onBackToClients} />;
	}

	if (assessment.is_canceled) {
		return <AssistantState title="Avaliação cancelada" description="Avaliações canceladas não podem receber sugestões ou observações oficiais." onBack={onBackToClients} tone="rose" />;
	}

	const currentAssessment = approvedAssessment ?? assessment;

	return (
		<div className="space-y-4">
			<PwaJourneyHeader icon="sparkles" eyebrow="Assistente da avaliação" title={client.full_name} description="Revise a sugestão clínica antes de adicioná-la à avaliação." tone="assistant">
				<span className="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">{formatDate(currentAssessment.evaluated_at)}</span>
				<span className="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">{currentAssessment.device_model} · {numberBr(currentAssessment.weight_kg, 1)} kg</span>
			</PwaJourneyHeader>

			{approved ? (
				<PwaCard className="border-emerald-200 bg-emerald-50/40 p-5">
					<PwaSectionHeader icon="check" eyebrow="Conteúdo aprovado" title="Observação adicionada" description="A observação foi registrada e já pode compor o relatório." tone="teal" />
					<div className={`mt-4 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold ${approvedAsEdited ? 'bg-violet-100 text-violet-700' : 'bg-emerald-100 text-emerald-700'}`}><PwaIcon name={approvedAsEdited ? 'user' : 'check'} size={14} />{approvedAsEdited ? 'Versão revisada pelo profissional' : 'Versão aprovada sem edição'}</div>
					<div className={`mt-4 grid gap-3 ${canOpenReport ? 'sm:grid-cols-2' : ''}`}><PwaButton type="button" onClick={() => setApproved(false)} variant="secondary" icon="refresh">Gerar outra sugestão</PwaButton>{canOpenReport ? <PwaButton type="button" onClick={onOpenReport} icon="fileText">Abrir relatório</PwaButton> : null}</div>
				</PwaCard>
			) : generating ? (
				<AssistantProgress />
			) : assistant ? (
				<ObservationSuggestion assistant={assistant} draft={draft} saving={saving} online={isOnline} error={error} onDraftChange={setDraft} onApprove={approve} onDiscard={discard} />
			) : (
				<PwaCard className="border-violet-200 p-5">
					<PwaSectionHeader icon="sparkles" eyebrow="Observação" title="Gerar sugestão para revisão" description="O conteúdo só será adicionado após sua conferência e aprovação." tone="brand" />
					<div className="mt-4 grid gap-2 text-xs font-bold text-violet-900 sm:grid-cols-3">{[['sparkles', 'Gerar sugestão'], ['eye', 'Revisar conteúdo'], ['check', 'Aprovar versão']].map(([icon, label], index) => <span key={label} className="flex items-center gap-2 rounded-xl bg-violet-50 px-3 py-2"><span className="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-violet-700"><PwaIcon name={icon} size={14} /></span>{index + 1}. {label}</span>)}</div>
					{assessment.notes ? <PwaInfoCard icon="fileText" title="Observação atual" className="mt-4"><p className="text-sm leading-6 text-slate-700">{assessment.notes}</p></PwaInfoCard> : null}
					{error ? <PwaAlert tone="error" title="Não foi possível gerar a observação" className="mt-4">{error}</PwaAlert> : null}
					<PwaButton type="button" onClick={generate} disabled={!isOnline} icon="sparkles" className="mt-5 w-full">Gerar observação</PwaButton>
				</PwaCard>
			)}

			{currentAssessment.has_approved_ai_observation || currentAssessment.care_guidance ? <CareGuidanceAssistant key={currentAssessment.id} assessment={currentAssessment} online={isOnline} onApproved={(updatedClient, updatedAssessment) => { setApprovedAssessment(updatedAssessment); onApproved(updatedClient, updatedAssessment); }} /> : null}
		</div>
	);
}

function AssistantState({ title, description, onBack, tone = 'amber' }) {
	return (
		<PwaState type={tone === 'rose' ? 'error' : 'empty'} icon={tone === 'rose' ? 'alertTriangle' : 'sparkles'} title={title} description={description} action={<PwaButton type="button" onClick={onBack} icon="arrowLeft">Ir para clientes</PwaButton>} />
	);
}
