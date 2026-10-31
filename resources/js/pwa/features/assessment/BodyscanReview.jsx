import React, { useMemo, useState } from 'react';
import { relaxmedicMetricLabels } from '../../../domain/bioimpedance/relaxmedic';
import { localDatetimeNow } from '../../../shared/formatters';
import PwaButton from '../../components/ui/PwaButton';
import PwaIcon from '../../components/ui/PwaIcon';
import { relaxmedicIntegerFields, relaxmedicTextFields } from './relaxmedicAssessmentForm';
import { segmentClassificationOptions, segmentLabels } from './bodyscanAssessmentForm';
import SegmentalBodyMap from '../../../components/bioimpedance/SegmentalBodyMap';
import { bodyscanLayoutPresentation, isVerticalBodyscanLayout } from './BodyscanReportFormatGuide';

const valueFields = ['mass_kg', 'reference_percentage', 'classification', 'reference_min_percentage', 'reference_max_percentage'];
const segmentFieldLabels = {
	mass_kg: 'massa',
	reference_percentage: 'percentual de referência',
	classification: 'classificação',
	reference_min_percentage: 'faixa mínima',
	reference_max_percentage: 'faixa máxima',
};
const extendedMetricLabels = {
	waist_hip_ratio: 'WHR',
	body_score: 'Pontuação corporal',
	recommended_target_weight: 'Peso-alvo recomendado',
	weight_control: 'Controle de peso',
	fat_control: 'Controle de gordura',
	muscle_control: 'Controle muscular',
	proprietary_obesity_index: 'Índice proprietário de obesidade',
	asmi: 'ASMI',
	body_balance_assessment: 'Avaliação do equilíbrio corporal',
};

const weightControlCodes = new Set(['recommended_target_weight', 'weight_control', 'fat_control', 'muscle_control']);

function ExtendedMetrics({ metrics, title = 'Métricas estendidas', controlSection = false }) {
	if (!metrics?.length) return null;

	return (
		<section className="rounded-2xl border border-sky-200 bg-sky-50/40 p-4">
			<h3 className="font-bold text-slate-950">{title}</h3>
			<p className="mt-1 text-xs leading-5 text-slate-600">{controlSection ? 'Metas e controles proprietários apresentados pelo aplicativo. Não constituem prescrição clínica.' : 'Referências, faixas e classificações proprietárias do Fitdays. Não representam classificação clínica ou diagnóstico.'}</p>
			<div className="mt-3 grid gap-2 sm:grid-cols-2">
				{metrics.map((metric) => {
					const value = metric.text_value ?? [metric.numeric_value, metric.unit].filter((part) => part !== null && part !== undefined && part !== '').join(' ');
					return <div key={metric.metric_code} className="rounded-xl border border-sky-100 bg-white p-3 text-xs"><strong className="block text-slate-900">{extendedMetricLabels[metric.metric_code] ?? metric.original_label}</strong><span className="mt-1 block text-base font-bold text-slate-800">{value}</span>{metric.application_classification ? <span className="mt-1 block text-slate-600">Classificação do aplicativo: {metric.application_classification}</span> : null}{metric.printed_minimum !== null && metric.printed_minimum !== undefined && metric.printed_maximum !== null && metric.printed_maximum !== undefined ? <span className="block text-slate-600">Faixa impressa: {metric.printed_minimum}–{metric.printed_maximum} {metric.unit ?? ''}</span> : null}{metric.weight_proportion_percentage !== null && metric.weight_proportion_percentage !== undefined ? <span className="block text-slate-600">Proporção do peso: {metric.weight_proportion_percentage}%</span> : null}<span className="mt-1 block text-slate-500">Leitura bruta: {metric.raw_extracted_value}</span></div>;
				})}
			</div>
			<p className="mt-3 text-xs font-semibold text-sky-900">{controlSection ? 'O peso-alvo é uma referência proprietária do aplicativo.' : 'A pontuação corporal não deve ser utilizada para diagnóstico.'}</p>
		</section>
	);
}

const impedanceRegionLabels = {
	'arm|left': 'Braço esquerdo (L)',
	'arm|right': 'Braço direito (R)',
	'trunk|center': 'Tronco',
	'leg|left': 'Perna esquerda (L)',
	'leg|right': 'Perna direita (R)',
};

const impedanceStateLabels = {
	clear: 'Legível',
	uncertain: 'Conferir',
	unreadable: 'Ilegível',
};

function ImpedanceSection({ readings, statuses, errors, onUpdate }) {
	if (!readings?.length) return null;

	return (
		<section className="rounded-2xl border border-indigo-200 bg-indigo-50/30 p-4">
			<h3 className="font-bold text-slate-950">Impedância bioelétrica segmentar</h3>
			<p className="mt-1 text-xs leading-5 text-slate-600">Leitura técnica em ohms. L e R são determinados pelos cabeçalhos da tabela original. Estes valores não geram diagnóstico.</p>
			<div className="mt-3 space-y-4">
				{[20, 100].map((frequency) => <div key={frequency}><h4 className="text-sm font-bold text-indigo-950">{frequency} kHz</h4><div className="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">{readings.map((reading, index) => ({ reading, index })).filter(({ reading }) => Number(reading.frequency_khz) === frequency).map(({ reading, index }) => { const key = `${frequency}|${reading.region}|${reading.side}`; const status = statuses?.[`impedance_readings.${key}`]; const flagged = reading.reading_state !== 'clear' || !String(reading.impedance_ohms ?? '').trim() || ['missing', 'suspicious'].includes(status); const error = firstError(errors, `impedance_readings.${index}.impedance_ohms`) ?? firstError(errors, `impedance_readings.${index}.reading_state`); return <fieldset key={key} className={`rounded-xl border p-3 ${error ? 'border-rose-300 bg-rose-50' : flagged ? 'border-amber-300 bg-amber-50' : 'border-indigo-100 bg-white'}`}><legend className="px-1 text-xs font-bold text-slate-800">{impedanceRegionLabels[`${reading.region}|${reading.side}`]}</legend><label className="mt-1 block text-xs font-semibold text-slate-700">Impedância (Ω)<input inputMode="decimal" value={reading.impedance_ohms ?? ''} onChange={(event) => onUpdate(index, 'impedance_ohms', event.target.value)} placeholder="Valor ilegível" className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 font-normal" /></label><label className="mt-2 block text-xs font-semibold text-slate-700">Estado<select required value={reading.reading_state ?? ''} onChange={(event) => onUpdate(index, 'reading_state', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 font-normal"><option value="">Selecione</option>{Object.entries(impedanceStateLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>{reading.raw_value ? <span className="mt-2 block text-[11px] text-slate-500">Leitura bruta: {reading.raw_value}</span> : null}{error ? <span role="alert" className="mt-2 block text-xs font-semibold text-rose-700">{error}</span> : null}</fieldset>; })}</div></div>)}
			</div>
			<p className="mt-3 text-xs font-semibold text-indigo-900">Valores ausentes, ilegíveis ou exatamente repetidos entre regiões devem ser comparados com a tabela original.</p>
		</section>
	);
}

function firstError(errors, key) {
	const value = errors?.[key];
	return Array.isArray(value) ? value[0] : value;
}

function ageAtMeasurement(birthDate, measuredAt) {
	if (!birthDate || !measuredAt) return null;
	const birth = new Date(`${birthDate}T12:00:00`);
	const measured = new Date(measuredAt);
	if (Number.isNaN(birth.getTime()) || Number.isNaN(measured.getTime())) return null;
	let age = measured.getFullYear() - birth.getFullYear();
	if (measured.getMonth() < birth.getMonth() || (measured.getMonth() === birth.getMonth() && measured.getDate() < birth.getDate())) age -= 1;
	return age >= 0 ? age : null;
}

function SegmentSection({ title, type, segments, statuses, errors, onUpdate }) {
	return (
		<section className="rounded-2xl border border-slate-200 bg-white p-4">
			<div className="flex items-center gap-2"><PwaIcon name="clipboard" size={18} /><h3 className="font-bold text-slate-950">{title}</h3></div>
			<p className="mt-1 text-xs text-slate-600"><strong>L</strong> = lado esquerdo · <strong>R</strong> = lado direito</p>
			<div className="mt-3 space-y-3">
				{segments.map(({ segment, index }) => {
					const prefix = `segmental.${type}.${segment.region}.${segment.side}`;
					const incoherentRange = Number(String(segment.reference_min_percentage).replace(',', '.')) > Number(String(segment.reference_max_percentage).replace(',', '.'));
					const flagged = incoherentRange || valueFields.some((field) => ['missing', 'unrecognized', 'suspicious'].includes(statuses?.[`${prefix}.${field}`]));
					const rowError = valueFields.map((field) => firstError(errors, `segmental_measurements.${index}.${field}`)).find(Boolean);
					return (
						<fieldset key={`${segment.region}-${segment.side}`} className={`rounded-xl border border-l-4 p-3 ${rowError ? 'border-rose-300 bg-rose-50' : flagged ? 'border-amber-300 border-l-amber-500 bg-amber-50/40' : 'border-slate-200 border-l-emerald-400'}`}>
							<legend className="px-1 text-sm font-bold text-slate-800">{segmentLabels[`${segment.region}|${segment.side}`]} {flagged ? <span className="ml-1 rounded-full bg-amber-200 px-2 py-0.5 text-[10px] text-amber-950">Conferir</span> : null}</legend>
							<div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
								<SegmentInput label="Massa (kg)" value={segment.mass_kg} onChange={(value) => onUpdate(index, 'mass_kg', value)} />
								<SegmentInput label="Referência (%)" value={segment.reference_percentage} onChange={(value) => onUpdate(index, 'reference_percentage', value)} />
								<label className="col-span-2 text-xs font-semibold text-slate-700 sm:col-span-1">Classificação<select required value={segment.classification ?? ''} onChange={(event) => onUpdate(index, 'classification', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2"><option value="">Selecione</option>{segmentClassificationOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
								<SegmentInput label="Faixa mín. (%)" value={segment.reference_min_percentage} onChange={(value) => onUpdate(index, 'reference_min_percentage', value)} />
								<SegmentInput label="Faixa máx. (%)" value={segment.reference_max_percentage} onChange={(value) => onUpdate(index, 'reference_max_percentage', value)} />
							</div>
							{incoherentRange ? <p role="alert" className="mt-2 text-xs font-semibold text-amber-800">Percentual incoerente: a faixa mínima está acima da máxima.</p> : null}
							{rowError ? <p role="alert" className="mt-2 text-xs font-semibold text-rose-700">{rowError}</p> : null}
						</fieldset>
					);
				})}
			</div>
		</section>
	);
}

function SegmentInput({ label, value, onChange }) {
	return <label className="text-xs font-semibold text-slate-700">{label}<input required inputMode="decimal" value={value} onChange={(event) => onChange(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-2 font-normal outline-none focus:ring-2 focus:ring-emerald-400" /></label>;
}

export default function BodyscanReview({ previewUrl, form, extraction, client, confirmed, errors, saving, online, onGeneralUpdate, onProfileUpdate, onSegmentUpdate, onImpedanceUpdate, onConfirm, onSubmit, onRestart }) {
	const [panel, setPanel] = useState('values');
	const [confirmationAttempted, setConfirmationAttempted] = useState(false);
	const statuses = extraction?.field_statuses ?? {};
	const grouped = useMemo(() => ({
		fat: form.segments.map((segment, index) => ({ segment, index })).filter(({ segment }) => segment.type === 'fat'),
		muscle: form.segments.map((segment, index) => ({ segment, index })).filter(({ segment }) => segment.type === 'muscle'),
	}), [form.segments]);
	const confirmationPendingItems = useMemo(() => {
		const pending = [];
		if (!form.profile?.measurement_application) pending.push('Selecione o aplicativo de origem.');
		if (String(form.profile?.athlete_mode_at_assessment ?? '') === '') pending.push('Confirme se o modo atleta estava ativado ou desativado.');
		if (form.segments.length !== 10) pending.push(`Confira os dez segmentos corporais: ${form.segments.length} de 10 foram identificados.`);
		form.segments.forEach((segment) => {
			const missing = valueFields.filter((field) => String(segment[field] ?? '').trim() === '');
			if (!missing.length) return;
			const label = segmentLabels[`${segment.region}|${segment.side}`] ?? 'Segmento não identificado';
			const type = segment.type === 'fat' ? 'gordura' : segment.type === 'muscle' ? 'músculo' : 'tipo não identificado';
			pending.push(`${label} (${type}): preencha ${missing.map((field) => segmentFieldLabels[field]).join(', ')}.`);
		});
		return pending;
	}, [form.profile?.athlete_mode_at_assessment, form.profile?.measurement_application, form.segments]);
	const incomplete = confirmationPendingItems.length > 0;
	const qualityWarnings = [...new Set(extraction?.quality_issues ?? [])];
	const readingWarnings = [...new Set((extraction?.suspicious_values ?? []).map((item) => item.reason).filter(Boolean))];
	const measurementAge = ageAtMeasurement(client?.birth_date, form.general.evaluated_at);
	const availableMetricFields = new Set(extraction?.available_metric_fields ?? Object.keys(relaxmedicMetricLabels));
	const compositionFields = Object.entries(relaxmedicMetricLabels).filter(([field]) => availableMetricFields.has(field));
	const extendedMetrics = (extraction?.extended_metrics ?? []).filter((metric) => !weightControlCodes.has(metric.metric_code));
	const weightControlMetrics = (extraction?.extended_metrics ?? []).filter((metric) => weightControlCodes.has(metric.metric_code));
	const isTechnicalLayout = extraction?.report_layout === 'fitdays_technical_report';
	const printedProfile = extraction?.printed_measurement_profile ?? {};
	const registeredSex = client?.biological_sex ?? null;
	const registeredHeight = client?.height_cm === null || client?.height_cm === undefined ? null : Number(client.height_cm);
	const profileComparison = {
		biological_sex: !isTechnicalLayout || !printedProfile.biological_sex || printedProfile.biological_sex === registeredSex,
		age_years: !isTechnicalLayout || printedProfile.age_years === null || printedProfile.age_years === undefined || Number(printedProfile.age_years) === measurementAge,
		height_cm: !isTechnicalLayout || printedProfile.height_cm === null || printedProfile.height_cm === undefined || (registeredHeight !== null && Math.abs(Number(printedProfile.height_cm) - registeredHeight) < 0.1),
	};
	const hasProfileDivergence = isTechnicalLayout && Object.values(profileComparison).some((matches) => !matches);
	const layoutPresentation = bodyscanLayoutPresentation[extraction?.report_layout];
	const verticalWithoutImpedance = isVerticalBodyscanLayout(extraction?.report_layout) && !(form.impedanceReadings ?? []).length;
	function handleConfirmationChange(checked) {
		if (checked && confirmationPendingItems.length) {
			setConfirmationAttempted(true);
			onConfirm(false);
			return;
		}
		setConfirmationAttempted(false);
		onConfirm(checked);
	}

	return (
		<form onSubmit={onSubmit} aria-busy={saving} className="pwa-card p-4 sm:p-5">
			<div className="flex items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Conferência humana</p><h2 className="mt-1 text-lg font-bold text-slate-950">Compare todos os dados Bodyscan</h2></div><PwaButton type="button" variant="ghost" icon="refresh" onClick={() => { if (window.confirm('Descartar a leitura e todas as correções para trocar a imagem?')) onRestart(); }}>Trocar imagem</PwaButton></div>
			{extraction?.side_consistency?.possible_swap ? <p role="alert" className="mt-3 rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm font-bold text-rose-800">Possível troca entre os lados L (esquerdo) e R (direito). Confira os marcadores diretamente na imagem.</p> : null}
			{qualityWarnings.length ? <details className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm"><summary className="min-h-11 cursor-pointer font-bold">Qualidade da imagem · {qualityWarnings.length} {qualityWarnings.length === 1 ? 'observação' : 'observações'}</summary><p className="mb-2 text-xs text-amber-900">Estas mensagens tratam apenas de nitidez, resolução ou enquadramento.</p><ul className="list-disc space-y-1 pl-5">{qualityWarnings.map((warning) => <li key={warning}>{warning}</li>)}</ul></details> : null}
			{readingWarnings.length ? <details className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm"><summary className="min-h-11 cursor-pointer font-bold">Campos para conferir · {readingWarnings.length}</summary><p className="mb-2 text-xs text-amber-900">Confira somente os textos, números, unidades ou lados que não puderam ser lidos com segurança.</p><ul className="list-disc space-y-1 pl-5">{readingWarnings.map((warning) => <li key={warning}>{warning}</li>)}</ul></details> : null}
			<div className="mt-3 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs leading-5 text-sky-950">
				<div className="flex flex-wrap items-center gap-2"><strong>Layout detectado:</strong><span>{layoutPresentation ? `${layoutPresentation.application} · ${layoutPresentation.label}` : 'Não identificado'}</span>{layoutPresentation ? <span className={`rounded-full px-2 py-0.5 text-[10px] font-black uppercase ${layoutPresentation.tone === 'recommended' ? 'bg-emerald-100 text-emerald-800' : 'bg-white text-sky-800'}`}>{layoutPresentation.badge}</span> : null}</div>
				{extraction?.layout_profile_version ? <span className="mt-1 block text-sky-800">Perfil de leitura: {extraction.layout_profile_version}</span> : null}
			</div>
			{verticalWithoutImpedance ? <div role="status" className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><strong className="text-slate-900">Relatório vertical processado normalmente.</strong><p className="mt-1 text-xs leading-5">Este formato não apresenta a tabela de impedância bioelétrica. Isso é esperado e não impede a conferência nem a criação da avaliação.</p></div> : null}
			<p className="mt-3 text-xs text-slate-500">O arquivo selecionado foi removido da fila de envio após o processamento. A prévia local permanece somente durante esta conferência e será limpa ao concluir ou trocar a imagem.</p>
			{hasProfileDivergence ? <p role="alert" className="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm font-bold text-amber-900">O perfil impresso diverge do cadastro. Confira sexo, idade e altura antes de confirmar a avaliação.</p> : null}
			<div className="mt-4 grid grid-cols-2 gap-2 lg:hidden"><button type="button" onClick={() => setPanel('image')} className={`min-h-11 rounded-xl font-bold ${panel === 'image' ? 'bg-ricosty-700 text-white' : 'bg-slate-100'}`}>Imagem</button><button type="button" onClick={() => setPanel('values')} className={`min-h-11 rounded-xl font-bold ${panel === 'values' ? 'bg-ricosty-700 text-white' : 'bg-slate-100'}`}>Valores</button></div>
			<div className="mt-4 lg:grid lg:grid-cols-[minmax(0,.7fr)_minmax(0,1.3fr)] lg:gap-5">
				<div className={`${panel === 'image' ? 'block' : 'hidden'} lg:block`}><div className="sticky top-20 max-h-[75vh] overflow-y-auto rounded-xl border bg-slate-100 p-2"><img src={previewUrl} alt="Relatório Bodyscan Pro original para conferência" className="mx-auto h-auto w-full rounded-lg object-contain" /></div></div>
				<div className={`${panel === 'values' ? 'block' : 'hidden'} space-y-4 lg:block`}>
					<section className="rounded-2xl border border-ricosty-200 bg-ricosty-50/40 p-4">
						<h3 className="font-bold text-slate-950">Perfil usado na medição</h3>
						<p className="mt-1 text-xs leading-5 text-slate-600">Confirme o perfil configurado no aplicativo. No relatório técnico, os dados impressos são comparados ao cadastro sem substituição automática.</p>
						<div className="mt-3 grid gap-2 sm:grid-cols-3">
							<ProfileValue label="Sexo no cadastro" value={client?.biological_sex === 'female' ? 'Feminino' : client?.biological_sex === 'male' ? 'Masculino' : 'Não informado'} printedValue={isTechnicalLayout ? (printedProfile.biological_sex === 'female' ? 'Feminino' : printedProfile.biological_sex === 'male' ? 'Masculino' : 'Ilegível') : null} matches={profileComparison.biological_sex} />
							<ProfileValue label="Idade na medição" value={measurementAge !== null ? `${measurementAge} anos` : 'Calculada ao salvar'} printedValue={isTechnicalLayout ? `${printedProfile.age_years ?? 'Ilegível'} anos` : null} matches={profileComparison.age_years} />
							<ProfileValue label="Altura no cadastro" value={client?.height_cm ? `${String(client.height_cm).replace('.', ',')} cm` : 'Não informada'} printedValue={isTechnicalLayout ? `${printedProfile.height_cm ?? 'Ilegível'} cm` : null} matches={profileComparison.height_cm} />
						</div>
						{isTechnicalLayout && printedProfile.raw_value ? <p className="mt-2 text-xs text-slate-500">Cabeçalho lido: {printedProfile.raw_value}</p> : null}
						<div className="mt-3 grid gap-3 sm:grid-cols-2">
							<label className="text-sm font-semibold text-slate-800">Aplicativo de origem<select required value={form.profile?.measurement_application ?? ''} onChange={(event) => onProfileUpdate('measurement_application', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3"><option value="">Selecione após conferir</option><option value="relaxfit">RelaxFit</option><option value="fitdays">Fitdays</option></select>{firstError(errors, 'measurement_application') ? <span className="mt-1 block text-xs text-rose-700">{firstError(errors, 'measurement_application')}</span> : null}</label>
							<label className="text-sm font-semibold text-slate-800">Modo atleta<select required value={form.profile?.athlete_mode_at_assessment ?? ''} onChange={(event) => onProfileUpdate('athlete_mode_at_assessment', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3"><option value="">Selecione após conferir</option><option value="0">Desativado</option><option value="1">Ativado</option></select>{firstError(errors, 'athlete_mode_at_assessment') ? <span className="mt-1 block text-xs text-rose-700">{firstError(errors, 'athlete_mode_at_assessment')}</span> : null}</label>
						</div>
						<p className="mt-3 text-xs font-semibold text-slate-600">Equipamento RM-BD0015A · Manual de referência V1.1222</p>
					</section>
					<section className="rounded-2xl border border-slate-200 bg-white p-4"><h3 className="font-bold text-slate-950">Composição corporal</h3><label className="mt-3 block text-sm font-semibold">Data e hora<input type="datetime-local" required max={localDatetimeNow()} value={form.general.evaluated_at} onChange={(event) => onGeneralUpdate('evaluated_at', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border px-3" /></label><div className="mt-3 grid gap-2 sm:grid-cols-2">{compositionFields.map(([field, label]) => { const flagged = ['missing', 'unrecognized', 'suspicious'].includes(statuses[`metrics.${field}`]); const rawText = extraction?.raw_values?.metric_text?.[field]; return <label key={field} className={`rounded-xl border p-3 text-xs font-semibold ${flagged ? 'border-amber-300 bg-amber-50' : 'border-slate-200'}`}>{label}{flagged ? <span className="ml-2 text-[10px] font-bold text-amber-800">CONFERIR</span> : null}{field === 'obesity_level' ? <select required value={form.general[field]} onChange={(event) => onGeneralUpdate(field, event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 font-normal"><option value="">Selecione após conferir</option>{(extraction?.allowed_values?.obesity_level ?? []).map((option) => <option key={option} value={option}>{option}</option>)}</select> : <input required type="text" inputMode={relaxmedicTextFields.includes(field) ? 'text' : relaxmedicIntegerFields.includes(field) ? 'numeric' : 'decimal'} value={form.general[field]} onChange={(event) => onGeneralUpdate(field, event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 font-normal" />}{rawText ? <span className="mt-1 block font-normal text-slate-500">Leitura bruta: {rawText}</span> : null}{firstError(errors, field) ? <span className="mt-1 block text-rose-700">{firstError(errors, field)}</span> : null}</label>; })}</div></section>
					<ExtendedMetrics metrics={extendedMetrics} />
					<div className="grid gap-4 print:grid-cols-2 lg:grid-cols-2"><SegmentalBodyMap measurements={form.segments} type="fat" /><SegmentalBodyMap measurements={form.segments} type="muscle" /></div>
					<SegmentSection title="Gordura segmentar" type="fat" segments={grouped.fat} statuses={statuses} errors={errors} onUpdate={onSegmentUpdate} />
					<SegmentSection title="Equilíbrio muscular" type="muscle" segments={grouped.muscle} statuses={statuses} errors={errors} onUpdate={onSegmentUpdate} />
					<ImpedanceSection readings={form.impedanceReadings ?? []} statuses={statuses} errors={errors} onUpdate={onImpedanceUpdate} />
					<ExtendedMetrics metrics={weightControlMetrics} title="Controle de peso do aplicativo" controlSection />
					<label className="block text-sm font-semibold">Observações<input maxLength={2000} value={form.general.notes} onChange={(event) => onGeneralUpdate('notes', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border px-3" /></label>
				</div>
			</div>
			<label className={`mt-5 flex cursor-pointer items-start gap-3 rounded-2xl border p-4 text-sm font-semibold ${confirmationAttempted && incomplete ? 'border-amber-400 bg-amber-50' : 'border-ricosty-200 bg-ricosty-50'}`}><input type="checkbox" checked={confirmed} onChange={(event) => handleConfirmationChange(event.target.checked)} aria-describedby={confirmationAttempted && incomplete ? 'bodyscan-confirmation-pending' : undefined} className="mt-0.5 h-5 w-5" /><span>Comparei a imagem original e confirmo o perfil usado na medição, as métricas gerais e estendidas, os segmentos corporais e, quando disponível, a tabela técnica de impedância.</span></label>
			{confirmationAttempted && incomplete ? <div id="bodyscan-confirmation-pending" role="alert" className="mt-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"><strong className="block">Ainda não é possível confirmar a conferência.</strong><p className="mt-1 text-xs leading-5">Revise as pendências abaixo e marque novamente a confirmação:</p><ul className="mt-2 list-disc space-y-1 pl-5">{confirmationPendingItems.map((item) => <li key={item}>{item}</li>)}</ul></div> : null}
			{firstError(errors, 'source_metadata') || firstError(errors, 'bodyscan_review_confirmed') || firstError(errors, 'segmental_measurements') ? <p role="alert" className="mt-3 rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700">{firstError(errors, 'source_metadata') ?? firstError(errors, 'bodyscan_review_confirmed') ?? firstError(errors, 'segmental_measurements')}</p> : null}
			<PwaButton type="submit" disabled={incomplete || !confirmed || saving || !online} loading={saving} icon="check" className="mt-4 w-full">{saving ? 'Criando avaliação...' : 'Confirmar e criar avaliação'}</PwaButton>
		</form>
	);
}

function ProfileValue({ label, value, printedValue = null, matches = true }) {
	return <div className={`rounded-xl border px-3 py-2 ${matches ? 'border-white bg-white' : 'border-amber-300 bg-amber-50'}`}><span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">{label}</span><strong className="text-sm text-slate-900">{value}</strong>{printedValue !== null ? <span className={`mt-1 block text-xs ${matches ? 'text-emerald-700' : 'font-bold text-amber-900'}`}>Impresso: {printedValue} · {matches ? 'confere' : 'diverge'}</span> : null}</div>;
}
