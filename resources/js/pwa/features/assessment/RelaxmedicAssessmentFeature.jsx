import React, { useEffect, useRef, useState } from 'react';
import { apiErrorMessage, apiValidationErrors, createBioimpedanceAssessment, processRelaxmedicImage } from '../../../services/bioimpedanceApi';
import { assessmentAccess } from '../clients/clientAccess';
import RelaxmedicImagePicker, { validateRelaxmedicImage } from './RelaxmedicImagePicker';
import RelaxmedicReview from './RelaxmedicReview';
import RelaxmedicOcrProgress from './RelaxmedicOcrProgress';
import { assessmentFromExtraction, emptyRelaxmedicAssessment, maskRelaxmedicValue, relaxmedicAssessmentPayload } from './relaxmedicAssessmentForm';
import useOnlineStatus from '../../hooks/useOnlineStatus';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaJourneyHeader from '../../components/ui/PwaJourneyHeader';
import PwaStepIndicator from '../../components/ui/PwaStepIndicator';

export default function RelaxmedicAssessmentFeature({ client, currentUser, scaleModel, onBackToClients, onCreated, onOpenAssistant }) {
	const previewReference = useRef('');
	const [imageFile, setImageFile] = useState(null);
	const [imageName, setImageName] = useState('');
	const [previewUrl, setPreviewUrl] = useState('');
	const [extraction, setExtraction] = useState(null);
	const [form, setForm] = useState(emptyRelaxmedicAssessment);
	const [confirmed, setConfirmed] = useState(false);
	const [processing, setProcessing] = useState(false);
	const [saving, setSaving] = useState(false);
	const [imageError, setImageError] = useState('');
	const [generalError, setGeneralError] = useState('');
	const [errors, setErrors] = useState({});
	const [createdAssessment, setCreatedAssessment] = useState(null);
	const [pickerVersion, setPickerVersion] = useState(0);
	const isOnline = useOnlineStatus();

	useEffect(() => () => {
		if (previewReference.current) URL.revokeObjectURL(previewReference.current);
	}, []);

	function clearPreview() {
		if (previewReference.current) URL.revokeObjectURL(previewReference.current);
		previewReference.current = '';
		setPreviewUrl('');
	}

	function restart() {
		clearPreview();
		setImageFile(null);
		setImageName('');
		setExtraction(null);
		setForm(emptyRelaxmedicAssessment());
		setConfirmed(false);
		setImageError('');
		setGeneralError('');
		setErrors({});
	}

	function selectImage(file, clearInputs) {
		const validationError = validateRelaxmedicImage(file);
		setImageError(validationError);
		setGeneralError('');
		setErrors({});
		setExtraction(null);
		setConfirmed(false);
		clearPreview();

		if (validationError) {
			setImageFile(null);
			setImageName('');
			clearInputs();
			return;
		}

		const url = URL.createObjectURL(file);
		previewReference.current = url;
		setPreviewUrl(url);
		setImageFile(file);
		setImageName(file.name);
	}

	async function processImage() {
		if (!imageFile || processing || !isOnline) return;
		setProcessing(true);
		setImageError('');
		setGeneralError('');
		setErrors({});

		try {
			const result = await processRelaxmedicImage(imageFile);
			setExtraction(result);
			setForm(assessmentFromExtraction(result));
			setConfirmed(false);
		} catch (error) {
			setImageError(apiValidationErrors(error).image?.[0] ?? apiErrorMessage(error, 'Não foi possível processar a imagem.'));
		} finally {
			// O arquivo deixa o estado após a tentativa. A URL temporária só permanece para a conferência visual.
			setImageFile(null);
			setPickerVersion((current) => current + 1);
			setProcessing(false);
		}
	}

	function update(field, value) {
		const maskedValue = field === 'evaluated_at' || field === 'notes' ? value : maskRelaxmedicValue(field, value);
		setForm((current) => ({ ...current, [field]: maskedValue }));
		setConfirmed(false);
		setErrors((current) => ({ ...current, [field]: undefined, relaxmedic_review_confirmed: undefined }));
	}

	async function submit(event) {
		event.preventDefault();
		if (!client || !extraction || !confirmed || saving || !isOnline) return;
		setSaving(true);
		setGeneralError('');
		setErrors({});

		try {
			const result = await createBioimpedanceAssessment(relaxmedicAssessmentPayload(form, client.id, extraction.image, confirmed));
			setCreatedAssessment(result.assessment);
			onCreated(result.client, result.assessment);
			clearPreview();
			setExtraction(null);
			setImageFile(null);
			setImageName('');
		} catch (error) {
			setErrors(apiValidationErrors(error));
			setGeneralError(apiErrorMessage(error, 'Não foi possível criar a avaliação. Confira os campos destacados.'));
		} finally {
			setSaving(false);
		}
	}

	if (!client) {
		return <BlockedState title="Selecione um cliente" description="Abra o cadastro de um cliente e toque em “Nova avaliação” para iniciar." onBack={onBackToClients} />;
	}

	if (scaleModel !== 'relaxmedic') {
		return <BlockedState title="Relaxmedic não está selecionada" description="A versão PWA desta etapa aceita apenas avaliações Relaxmedic. Altere o modelo nas configurações do painel para continuar." onBack={onBackToClients} />;
	}

	const access = assessmentAccess(client, currentUser, scaleModel);
	if (!access.allowed) return <BlockedState title="Avaliação indisponível" description={access.reason} onBack={onBackToClients} />;

	if (createdAssessment) {
		return (
			<PwaCard className="p-6 text-center">
				<PwaStepIndicator steps={['Imagem', 'Processamento', 'Conferência', 'Conclusão']} current={4} />
				<div className="mx-auto mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-ricosty-100 text-2xl font-black text-ricosty-700">✓</div>
				<h2 className="mt-3 text-xl font-bold text-slate-950">Avaliação criada com segurança</h2>
				<p className="mt-2 text-sm leading-6 text-slate-600">Os dados de {client.full_name} foram salvos após a confirmação. A prévia temporária foi removida da PWA.</p>
				<div className="mt-5 grid gap-3 sm:grid-cols-2">
					<PwaButton type="button" onClick={onBackToClients} variant="secondary" icon="arrowLeft">Voltar para clientes</PwaButton>
					<PwaButton type="button" onClick={onOpenAssistant} icon="sparkles">Abrir assistente</PwaButton>
				</div>
			</PwaCard>
		);
	}

	return (
		<div className="space-y-4">
			<PwaJourneyHeader icon="camera" eyebrow="Nova avaliação · Relaxmedic" title={client.full_name} description="A avaliação só será criada depois do processamento e da sua confirmação." />
			<PwaStepIndicator steps={['Imagem', 'Processamento', 'Conferência', 'Conclusão']} current={extraction ? 3 : processing ? 2 : 1} />
			{!isOnline ? <p role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">Sem conexão. O processamento e a criação ficam bloqueados para evitar envios incompletos ou duplicados.</p> : null}

			{processing ? <RelaxmedicOcrProgress previewUrl={previewUrl} /> : !extraction ? (
				<>
					<RelaxmedicImagePicker key={pickerVersion} previewUrl={previewUrl} fileName={imageName} disabled={!isOnline} error={imageError} onSelect={selectImage} />
					{previewUrl ? <PwaButton type="button" disabled={!imageFile || !isOnline} onClick={processImage} icon="sparkles" className="w-full">{imageFile ? 'Enviar e processar imagem' : 'Selecione novamente para tentar'}</PwaButton> : null}
				</>
			) : (
				<RelaxmedicReview previewUrl={previewUrl} form={form} extraction={extraction} confirmed={confirmed} errors={errors} saving={saving} online={isOnline} onUpdate={update} onConfirm={setConfirmed} onSubmit={submit} onRestart={restart} />
			)}
			{generalError ? <p role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{generalError}</p> : null}
		</div>
	);
}

function BlockedState({ title, description, onBack }) {
	return (
		<PwaCard className="p-6 text-center">
			<div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700"><span aria-hidden="true">!</span></div>
			<p className="text-xs font-bold uppercase tracking-wide text-amber-700">Nova avaliação</p>
			<h2 className="mt-2 text-xl font-bold text-slate-950">{title}</h2>
			<p className="mt-2 text-sm leading-6 text-slate-600">{description}</p>
			<PwaButton type="button" onClick={onBack} icon="arrowLeft" className="mt-5">Ir para clientes</PwaButton>
		</PwaCard>
	);
}
