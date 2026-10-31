import React, { useEffect, useRef, useState } from 'react';
import { apiErrorMessage, apiValidationErrors, createBioimpedanceAssessment, processBodyscanProImage, processRelaxmedicImage } from '../../../services/bioimpedanceApi';
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
import useActionLock from '../../../hooks/useActionLock';
import BodyscanReview from './BodyscanReview';
import { bodyscanAssessmentPayload, bodyscanFormFromExtraction, bodyscanProtocolComplete, emptyBodyscanProtocol, updateBodyscanImpedance, updateBodyscanSegment } from './bodyscanAssessmentForm';
import BodyscanOcrProgress from './BodyscanOcrProgress';
import BodyscanProtocolChecklist from './BodyscanProtocolChecklist';
import BodyscanReportFormatGuide from './BodyscanReportFormatGuide';

export default function RelaxmedicAssessmentFeature({ client, currentUser, scaleModel, onBackToClients, onCreated, onOpenAssistant, onOpenReport, canOpenReport = false }) {
	const runAction = useActionLock();
	const previewReference = useRef('');
	const [imageFile, setImageFile] = useState(null);
	const [imageName, setImageName] = useState('');
	const [previewUrl, setPreviewUrl] = useState('');
	const [extraction, setExtraction] = useState(null);
	const [form, setForm] = useState(emptyRelaxmedicAssessment);
	const [bodyscanForm, setBodyscanForm] = useState({ general: emptyRelaxmedicAssessment(), profile: { athlete_mode_at_assessment: '', measurement_application: '' }, protocol: emptyBodyscanProtocol(), segments: [] });
	const [confirmed, setConfirmed] = useState(false);
	const [processing, setProcessing] = useState(false);
	const [saving, setSaving] = useState(false);
	const [imageError, setImageError] = useState('');
	const [generalError, setGeneralError] = useState('');
	const [errors, setErrors] = useState({});
	const [createdAssessment, setCreatedAssessment] = useState(null);
	const [pickerVersion, setPickerVersion] = useState(0);
	const [bodyscanProcessingStage, setBodyscanProcessingStage] = useState(0);
	const isOnline = useOnlineStatus();
	const isBodyscan = scaleModel === 'relaxmedic_bodyscan_pro';
	const protocolComplete = bodyscanProtocolComplete(bodyscanForm.protocol);
	const journeySteps = isBodyscan ? ['Imagem', 'Leitura geral', 'Leitura segmentar', 'Conferência', 'Conclusão'] : ['Imagem', 'Processamento', 'Conferência', 'Conclusão'];

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
		setBodyscanForm({ general: emptyRelaxmedicAssessment(), profile: { athlete_mode_at_assessment: '', measurement_application: '' }, protocol: emptyBodyscanProtocol(), segments: [] });
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
		return runAction('process-bioimpedance-image', async () => {
			if (!imageFile || processing || !isOnline) return;
			setProcessing(true);
		setBodyscanProcessingStage(0);
			setImageError('');
			setGeneralError('');
			setErrors({});

			try {
				const result = scaleModel === 'relaxmedic_bodyscan_pro'
					? await processBodyscanProImage(imageFile)
					: await processRelaxmedicImage(imageFile);
				setExtraction(result);
				if (scaleModel === 'relaxmedic_bodyscan_pro') setBodyscanForm((current) => ({ ...bodyscanFormFromExtraction(result), protocol: current.protocol }));
				else setForm(assessmentFromExtraction(result));
				setConfirmed(false);
				// A referência enviável é removida após o OCR; a URL temporária permanece apenas para conferência.
				setImageFile(null);
				setImageName('');
				setPickerVersion((current) => current + 1);
			} catch (error) {
				setImageError(apiValidationErrors(error).image?.[0] ?? apiErrorMessage(error, 'Não foi possível processar a imagem.'));
			} finally {
				setProcessing(false);
			}
		});
	}

	function updateBodyscanGeneral(field, value) {
		const maskedValue = field === 'evaluated_at' || field === 'notes' ? value : maskRelaxmedicValue(field, value);
		setBodyscanForm((current) => ({ ...current, general: { ...current.general, [field]: maskedValue } }));
		setConfirmed(false);
		setErrors((current) => ({ ...current, [field]: undefined, bodyscan_review_confirmed: undefined }));
	}

	function updateSegment(index, field, value) {
		setBodyscanForm((current) => ({ ...current, segments: updateBodyscanSegment(current.segments, index, field, value) }));
		setConfirmed(false);
		setErrors({});
	}

	function updateImpedance(index, field, value) {
		setConfirmed(false);
		setBodyscanForm((current) => ({ ...current, impedanceReadings: updateBodyscanImpedance(current.impedanceReadings ?? [], index, field, value) }));
	}

	function updateBodyscanProfile(field, value) {
		setBodyscanForm((current) => ({ ...current, profile: { ...current.profile, [field]: value } }));
		setConfirmed(false);
		setErrors((current) => ({ ...current, [field]: undefined, bodyscan_review_confirmed: undefined }));
	}

	function updateBodyscanProtocol(field, checked) {
		setBodyscanForm((current) => ({ ...current, protocol: { ...current.protocol, [field]: checked } }));
		setConfirmed(false);
	}

	function update(field, value) {
		const maskedValue = field === 'evaluated_at' || field === 'notes' ? value : maskRelaxmedicValue(field, value);
		setForm((current) => ({ ...current, [field]: maskedValue }));
		setConfirmed(false);
		setErrors((current) => ({ ...current, [field]: undefined, relaxmedic_review_confirmed: undefined }));
	}

	async function submit(event) {
		event.preventDefault();
		return runAction('create-assessment', async () => {
			if (!client || !extraction || !confirmed || saving || !isOnline) return;
			setSaving(true);
			setGeneralError('');
			setErrors({});

			try {
				const payload = scaleModel === 'relaxmedic_bodyscan_pro'
					? bodyscanAssessmentPayload(bodyscanForm, client.id, extraction.image, confirmed)
					: relaxmedicAssessmentPayload(form, client.id, extraction.image, confirmed);
				const result = await createBioimpedanceAssessment(payload);
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
		});
	}

	if (!client) {
		return <BlockedState title="Selecione um cliente" description="Abra o cadastro de um cliente e toque em “Nova avaliação” para iniciar." onBack={onBackToClients} />;
	}

	if (!['relaxmedic', 'relaxmedic_bodyscan_pro'].includes(scaleModel)) {
		return <BlockedState title="Relaxmedic não está selecionada" description="A versão PWA desta etapa aceita apenas avaliações Relaxmedic. Altere o modelo nas configurações do painel para continuar." onBack={onBackToClients} />;
	}

	const access = assessmentAccess(client, currentUser, scaleModel);
	if (!access.allowed) return <BlockedState title="Avaliação indisponível" description={access.reason} onBack={onBackToClients} />;

	if (createdAssessment) {
		return (
			<PwaCard className="p-6 text-center">
				<PwaStepIndicator steps={journeySteps} current={journeySteps.length} />
				<div className="mx-auto mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-ricosty-100 text-2xl font-black text-ricosty-700">✓</div>
				<h2 className="mt-3 text-xl font-bold text-slate-950">Avaliação criada com segurança</h2>
				<p className="mt-2 text-sm leading-6 text-slate-600">Os dados de {client.full_name} foram salvos após a confirmação. A prévia temporária foi removida da PWA.</p>
				{!canOpenReport ? <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold leading-5 text-amber-900">O relatório corporal não aparece porque esse módulo está desativado nas configurações da clínica.</p> : null}
				<div className={`mt-5 grid gap-3 ${canOpenReport ? 'sm:grid-cols-3' : 'sm:grid-cols-2'}`}>
					<PwaButton type="button" onClick={onBackToClients} variant="secondary" icon="arrowLeft">Voltar para clientes</PwaButton>
					<PwaButton type="button" onClick={onOpenAssistant} icon="sparkles">Abrir assistente</PwaButton>
					{canOpenReport ? <PwaButton type="button" onClick={onOpenReport} variant="secondary" icon="fileText">Abrir relatório</PwaButton> : null}
				</div>
			</PwaCard>
		);
	}

	return (
		<div className="space-y-4">
			<PwaJourneyHeader icon="camera" eyebrow={`Nova avaliação · ${scaleModel === 'relaxmedic_bodyscan_pro' ? 'Bodyscan Pro' : 'Relaxmedic'}`} title={client.full_name} description="A avaliação só será criada depois do processamento e da sua confirmação." />
			<PwaStepIndicator steps={journeySteps} current={extraction ? journeySteps.length - 1 : processing ? 2 + (isBodyscan ? bodyscanProcessingStage : 0) : 1} />

			{processing ? (isBodyscan ? <BodyscanOcrProgress previewUrl={previewUrl} onStageChange={setBodyscanProcessingStage} /> : <RelaxmedicOcrProgress previewUrl={previewUrl} />) : !extraction ? (
				<>
					{isBodyscan ? <BodyscanReportFormatGuide compact /> : null}
					{isBodyscan ? <BodyscanProtocolChecklist value={bodyscanForm.protocol} onChange={updateBodyscanProtocol} /> : null}
					<RelaxmedicImagePicker key={pickerVersion} reportName={scaleModel === 'relaxmedic_bodyscan_pro' ? 'Bodyscan Pro' : 'Relaxmedic'} previewUrl={previewUrl} fileName={imageName} disabled={!isOnline || (isBodyscan && !protocolComplete)} error={imageError} onSelect={selectImage} />
					{previewUrl ? <PwaButton type="button" disabled={!imageFile || !isOnline || (isBodyscan && !protocolComplete)} onClick={processImage} icon="sparkles" className="w-full">{imageFile ? 'Enviar e processar imagem' : 'Selecione novamente para tentar'}</PwaButton> : null}
				</>
			) : (
				scaleModel === 'relaxmedic_bodyscan_pro'
					? <BodyscanReview previewUrl={previewUrl} form={bodyscanForm} extraction={extraction} client={client} confirmed={confirmed} errors={errors} saving={saving} online={isOnline} onGeneralUpdate={updateBodyscanGeneral} onProfileUpdate={updateBodyscanProfile} onSegmentUpdate={updateSegment} onImpedanceUpdate={updateImpedance} onConfirm={setConfirmed} onSubmit={submit} onRestart={restart} />
					: <RelaxmedicReview previewUrl={previewUrl} form={form} extraction={extraction} confirmed={confirmed} errors={errors} saving={saving} online={isOnline} onUpdate={update} onConfirm={setConfirmed} onSubmit={submit} onRestart={restart} />
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
