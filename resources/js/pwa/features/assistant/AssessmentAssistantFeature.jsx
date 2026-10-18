import React, { useEffect, useState } from 'react';
import { apiErrorMessage, apiValidationErrors, approveAssessmentObservation, generateAssessmentObservation } from '../../../services/bioimpedanceApi';
import { formatDate, numberBr } from '../../../shared/formatters';
import { canUseClinicalFeatures } from '../clients/clientAccess';
import AssistantProgress from './AssistantProgress';
import ObservationSuggestion from './ObservationSuggestion';
import { assistantProcessingSteps } from './assistantSteps';

export default function AssessmentAssistantFeature({ client, assessment, currentUser, onBackToClients, onApproved, onOpenReport }) {
	const [assistant, setAssistant] = useState(null);
	const [draft, setDraft] = useState('');
	const [generating, setGenerating] = useState(false);
	const [saving, setSaving] = useState(false);
	const [processingStep, setProcessingStep] = useState(0);
	const [error, setError] = useState('');
	const [approved, setApproved] = useState(false);

	useEffect(() => {
		if (!generating) return undefined;

		const interval = window.setInterval(() => {
			setProcessingStep((current) => Math.min(current + 1, assistantProcessingSteps.length - 1));
		}, 1200);

		return () => window.clearInterval(interval);
	}, [generating]);

	async function generate() {
		if (!assessment || assessment.is_canceled || generating) return;
		setGenerating(true);
		setProcessingStep(0);
		setAssistant(null);
		setDraft('');
		setError('');
		setApproved(false);

		try {
			const result = await generateAssessmentObservation(assessment.id);
			setAssistant(result);
			setDraft(result?.suggestion ?? '');
		} catch (requestError) {
			setError(apiErrorMessage(requestError, 'O assistente está indisponível no momento. Tente novamente mais tarde.'));
		} finally {
			setGenerating(false);
		}
	}

	async function approve() {
		if (!assistant || draft.trim().length < 20 || saving) return;
		setSaving(true);
		setError('');

		try {
			const result = await approveAssessmentObservation(assessment.id, {
				notes: draft.trim(),
				review_action: assistant.suggestion === draft ? 'approved' : 'edited',
				assistant_output_id: assistant.output_id,
			});
			onApproved(result.client, result.assessment);
			setApproved(true);
			setAssistant(null);
			setDraft('');
		} catch (requestError) {
			const validationErrors = apiValidationErrors(requestError);
			setError(validationErrors.notes?.[0] ?? validationErrors.bioimpedance_assessment_id?.[0] ?? apiErrorMessage(requestError, 'Não foi possível adicionar a observação.'));
		} finally {
			setSaving(false);
		}
	}

	function discard() {
		setAssistant(null);
		setDraft('');
		setError('');
		setApproved(false);
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
			<section className="rounded-2xl bg-slate-950 p-5 text-white shadow-sm">
				<p className="text-xs font-bold uppercase tracking-wide text-violet-300">Assistente da avaliação</p>
				<h2 className="mt-2 text-lg font-bold">{client.full_name}</h2>
				<p className="mt-1 text-sm text-slate-300">{formatDate(assessment.evaluated_at)} · {assessment.device_model} · {numberBr(assessment.weight_kg, 1)} kg</p>
			</section>

			{approved ? (
				<section className="rounded-2xl border border-emerald-200 bg-white p-5 text-center shadow-sm">
					<div className="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-emerald-100 text-lg font-black text-emerald-700">✓</div>
					<h2 className="mt-3 text-lg font-bold text-slate-950">Observação adicionada</h2>
					<p className="mt-2 text-sm leading-6 text-slate-600">A aprovação foi registrada na avaliação e na auditoria. O texto já poderá ser usado no relatório.</p>
					<div className="mt-4 grid gap-3 sm:grid-cols-2"><button type="button" onClick={() => setApproved(false)} className="rounded-xl border border-violet-200 px-4 py-3 text-sm font-bold text-violet-700">Gerar outra sugestão</button><button type="button" onClick={onOpenReport} className="rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white">Abrir relatório</button></div>
				</section>
			) : generating ? (
				<AssistantProgress currentStep={processingStep} />
			) : assistant ? (
				<ObservationSuggestion assistant={assistant} draft={draft} saving={saving} error={error} onDraftChange={setDraft} onApprove={approve} onDiscard={discard} />
			) : (
				<section className="rounded-2xl border border-violet-200 bg-white p-5 shadow-sm">
					<h2 className="text-lg font-bold text-slate-950">Gerar observação assistida</h2>
					<p className="mt-2 text-sm leading-6 text-slate-600">O assistente consulta dados da avaliação e fontes aprovadas. Nada será adicionado sem sua revisão e aprovação.</p>
					{assessment.notes ? <div className="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600"><strong className="text-slate-800">Observação atual:</strong><p className="mt-1 leading-6">{assessment.notes}</p></div> : null}
					{error ? <p role="alert" className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{error}</p> : null}
					<button type="button" onClick={generate} className="mt-5 w-full rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white">Gerar observação</button>
				</section>
			)}
		</div>
	);
}

function AssistantState({ title, description, onBack, tone = 'amber' }) {
	const colors = tone === 'rose' ? 'border-rose-200 text-rose-700' : 'border-amber-200 text-amber-700';

	return (
		<section className={`rounded-2xl border bg-white p-6 text-center shadow-sm ${colors}`}>
			<p className="text-xs font-bold uppercase tracking-wide">Assistente</p>
			<h2 className="mt-2 text-xl font-bold text-slate-950">{title}</h2>
			<p className="mt-2 text-sm leading-6 text-slate-600">{description}</p>
			<button type="button" onClick={onBack} className="mt-5 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white">Ir para clientes</button>
		</section>
	);
}
