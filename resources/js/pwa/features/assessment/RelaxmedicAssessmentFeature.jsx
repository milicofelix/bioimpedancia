import React, { useEffect, useRef, useState } from 'react';
import { apiErrorMessage, apiValidationErrors, createBioimpedanceAssessment, processRelaxmedicImage } from '../../../services/bioimpedanceApi';
import { assessmentAccess } from '../clients/clientAccess';
import RelaxmedicImagePicker, { validateRelaxmedicImage } from './RelaxmedicImagePicker';
import RelaxmedicReview from './RelaxmedicReview';
import { assessmentFromExtraction, emptyRelaxmedicAssessment, maskRelaxmedicValue, relaxmedicAssessmentPayload } from './relaxmedicAssessmentForm';

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
		if (!imageFile || processing) return;
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
		if (!client || !extraction || !confirmed || saving) return;
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
			<section className="rounded-2xl border border-emerald-200 bg-white p-6 text-center shadow-sm">
				<div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-xl font-black text-emerald-700">✓</div>
				<h2 className="mt-3 text-xl font-bold text-slate-950">Avaliação criada com segurança</h2>
				<p className="mt-2 text-sm leading-6 text-slate-600">Os dados de {client.full_name} foram salvos após a confirmação. A prévia temporária foi removida da PWA.</p>
				<div className="mt-5 grid gap-3 sm:grid-cols-2">
					<button type="button" onClick={onBackToClients} className="rounded-xl border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700">Voltar para clientes</button>
					<button type="button" onClick={onOpenAssistant} className="rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white">Abrir assistente</button>
				</div>
			</section>
		);
	}

	return (
		<div className="space-y-4">
			<section className="rounded-2xl bg-slate-950 p-5 text-white shadow-sm">
				<p className="text-xs font-bold uppercase tracking-wide text-emerald-300">Nova avaliação · Relaxmedic</p>
				<h2 className="mt-2 text-lg font-bold">{client.full_name}</h2>
				<p className="mt-1 text-sm text-slate-300">A avaliação só será criada depois do processamento e da sua confirmação.</p>
			</section>

			{!extraction ? (
				<>
					<RelaxmedicImagePicker key={pickerVersion} previewUrl={previewUrl} fileName={imageName} disabled={processing} error={imageError} onSelect={selectImage} />
					{previewUrl ? <button type="button" disabled={!imageFile || processing} onClick={processImage} className="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">{processing ? 'Processando imagem...' : imageFile ? 'Enviar e processar imagem' : 'Selecione novamente para tentar'}</button> : null}
				</>
			) : (
				<RelaxmedicReview previewUrl={previewUrl} form={form} extraction={extraction} confirmed={confirmed} errors={errors} saving={saving} onUpdate={update} onConfirm={setConfirmed} onSubmit={submit} onRestart={restart} />
			)}
			{generalError ? <p role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{generalError}</p> : null}
		</div>
	);
}

function BlockedState({ title, description, onBack }) {
	return (
		<section className="rounded-2xl border border-amber-200 bg-white p-6 text-center shadow-sm">
			<p className="text-xs font-bold uppercase tracking-wide text-amber-700">Nova avaliação</p>
			<h2 className="mt-2 text-xl font-bold text-slate-950">{title}</h2>
			<p className="mt-2 text-sm leading-6 text-slate-600">{description}</p>
			<button type="button" onClick={onBack} className="mt-5 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white">Ir para clientes</button>
		</section>
	);
}
