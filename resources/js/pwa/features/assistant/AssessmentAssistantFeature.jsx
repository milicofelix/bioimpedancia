import React, { useEffect, useState } from 'react';
import { apiErrorMessage, apiValidationErrors, approveAssessmentObservation, generateAssessmentObservation } from '../../../services/bioimpedanceApi';
import { formatDate, numberBr } from '../../../shared/formatters';
import { canUseClinicalFeatures } from '../clients/clientAccess';
import AssistantProgress from './AssistantProgress';
import ObservationSuggestion from './ObservationSuggestion';
import { assistantProcessingSteps } from './assistantSteps';
import useOnlineStatus from '../../hooks/useOnlineStatus';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaJourneyHeader from '../../components/ui/PwaJourneyHeader';
import useActionLock from '../../../hooks/useActionLock';

export default function AssessmentAssistantFeature({ client, assessment, currentUser, onBackToClients, onApproved, onOpenReport }) {
	const runAction = useActionLock();
	const [assistant, setAssistant] = useState(null);
	const [draft, setDraft] = useState('');
	const [generating, setGenerating] = useState(false);
	const [saving, setSaving] = useState(false);
	const [processingStep, setProcessingStep] = useState(0);
	const [error, setError] = useState('');
	const [approved, setApproved] = useState(false);
	const [approvedAsEdited, setApprovedAsEdited] = useState(false);
	const isOnline = useOnlineStatus();

	useEffect(() => {
		if (!generating) return undefined;

		const interval = window.setInterval(() => {
			setProcessingStep((current) => Math.min(current + 1, assistantProcessingSteps.length - 1));
		}, 1200);

		return () => window.clearInterval(interval);
	}, [generating]);

	async function generate() {
		return runAction('generate-observation', async () => {
			if (!assessment || assessment.is_canceled || generating || !isOnline) return;
			setGenerating(true);
			setProcessingStep(0);
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

	return (
		<div className="space-y-4">
			<PwaJourneyHeader icon="sparkles" eyebrow="Assistente da avaliação" title={client.full_name} description="Revise a sugestão clínica antes de adicioná-la à avaliação." tone="assistant">
				<span className="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">{formatDate(assessment.evaluated_at)}</span>
				<span className="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">{assessment.device_model} · {numberBr(assessment.weight_kg, 1)} kg</span>
			</PwaJourneyHeader>

			{approved ? (
				<PwaCard className="p-5 text-center">
					<div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-lg font-black text-emerald-700">✓</div>
					<h2 className="mt-3 text-lg font-bold text-slate-950">Observação adicionada</h2>
					<p className="mt-2 text-sm leading-6 text-slate-600">A aprovação humana foi registrada na avaliação e na auditoria. O texto já poderá ser usado no relatório.</p>
					<div className={`mx-auto mt-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold ${approvedAsEdited ? 'bg-violet-100 text-violet-700' : 'bg-emerald-100 text-emerald-700'}`}><span aria-hidden="true">{approvedAsEdited ? '✎' : '✓'}</span>{approvedAsEdited ? 'Versão editada aprovada' : 'Sugestão original aprovada'}</div>
					<div className="mt-4 grid gap-3 sm:grid-cols-2"><PwaButton type="button" onClick={() => setApproved(false)} variant="secondary" icon="refresh">Gerar outra sugestão</PwaButton><PwaButton type="button" onClick={onOpenReport} icon="fileText">Abrir relatório</PwaButton></div>
				</PwaCard>
			) : generating ? (
				<AssistantProgress currentStep={processingStep} />
			) : assistant ? (
				<ObservationSuggestion assistant={assistant} draft={draft} saving={saving} online={isOnline} error={error} onDraftChange={setDraft} onApprove={approve} onDiscard={discard} />
			) : (
				<PwaCard className="p-5">
					<div className="flex items-center gap-3"><span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-ricosty-600 text-xl text-white shadow-lg shadow-violet-100">✦</span><div><p className="text-xs font-bold uppercase tracking-wide text-violet-700">IA com revisão humana</p><h2 className="text-lg font-bold text-slate-950">Gerar observação assistida</h2></div></div>
					<p className="mt-2 text-sm leading-6 text-slate-600">O assistente consulta dados da avaliação e fontes aprovadas. Nada será adicionado sem sua revisão e aprovação.</p>
					<div className="mt-4 grid gap-2 text-center text-xs font-bold text-violet-900 sm:grid-cols-3"><span className="rounded-xl bg-violet-50 px-2 py-2">1. IA gera</span><span className="rounded-xl bg-violet-50 px-2 py-2">2. Você revisa</span><span className="rounded-xl bg-violet-50 px-2 py-2">3. Você aprova</span></div>
					{assessment.notes ? <div className="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600"><strong className="text-slate-800">Observação atual:</strong><p className="mt-1 leading-6">{assessment.notes}</p></div> : null}
					{error ? <p role="alert" className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{error}</p> : null}
					<PwaButton type="button" onClick={generate} disabled={!isOnline} icon="sparkles" className="mt-5 w-full">Gerar observação</PwaButton>
				</PwaCard>
			)}
		</div>
	);
}

function AssistantState({ title, description, onBack, tone = 'amber' }) {
	const colors = tone === 'rose' ? 'border-rose-200 text-rose-700' : 'border-amber-200 text-amber-700';

	return (
		<PwaCard className={`p-6 text-center ${colors}`}>
			<p className="text-xs font-bold uppercase tracking-wide">Assistente</p>
			<h2 className="mt-2 text-xl font-bold text-slate-950">{title}</h2>
			<p className="mt-2 text-sm leading-6 text-slate-600">{description}</p>
			<PwaButton type="button" onClick={onBack} icon="arrowLeft" className="mt-5">Ir para clientes</PwaButton>
		</PwaCard>
	);
}
