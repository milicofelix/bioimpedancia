import { relaxmedicMetricLabels, relaxmedicMetricMetadata } from '../../../domain/bioimpedance/relaxmedic';

const omronMetrics = [
	{ field: 'weight_kg', label: 'Peso', unit: 'kg', decimals: 1 },
	{ field: 'calculated_bmi', label: 'IMC calculado', unit: '', decimals: 1 },
	{ field: 'body_fat_percentage', label: 'Gordura corporal', unit: '%', decimals: 1 },
	{ field: 'skeletal_muscle_percentage', label: 'Músculo esquelético', unit: '%', decimals: 1 },
	{ field: 'visceral_fat_level', label: 'Gordura visceral', unit: '', decimals: 1 },
	{ field: 'resting_metabolism_kcal', label: 'Metabolismo basal', unit: 'kcal', decimals: 0 },
	{ field: 'body_age', label: 'Índice de idade corporal do aparelho', unit: 'anos', decimals: 0 },
];

const relaxmedicUnits = {
	weight_kg: 'kg',
	scale_bmi: '',
	body_fat_percentage: '%',
	muscle_rate_percentage: '%',
	lean_body_mass_kg: 'kg',
	subcutaneous_fat_percentage: '%',
	visceral_fat_level: '',
	body_water_percentage: '%',
	skeletal_muscle_percentage: '%',
	muscle_mass_kg: 'kg',
	bone_mass_kg: 'kg',
	protein_percentage: '%',
	resting_metabolism_kcal: 'kcal',
	body_age: 'anos',
	fat_mass_kg: 'kg',
	water_weight_kg: 'kg',
	protein_mass_kg: 'kg',
	ideal_body_weight_kg: 'kg',
};

const textFields = ['obesity_level', 'body_type'];
const integerFields = ['resting_metabolism_kcal', 'body_age'];

export function metricsForAssessment(assessment) {
	const definitions = ['Relaxmedic', 'Bodyscan Pro'].includes(assessment?.device_model)
		? Object.entries(relaxmedicMetricLabels).map(([field, label]) => ({
			field,
			label: label.replace(/ \([^)]*\)$/, ''),
			unit: relaxmedicUnits[field] ?? '',
			decimals: integerFields.includes(field) ? 0 : 1,
			text: textFields.includes(field),
			type: relaxmedicMetricMetadata[field]?.type,
			note: relaxmedicMetricMetadata[field]?.note,
		}))
		: omronMetrics;

	return definitions.filter(({ field }) => hasValue(assessment?.[field]));
}

export function previousAssessment(client, currentAssessment) {
	const currentDate = new Date(currentAssessment.evaluated_at).getTime();

	return (client?.assessments ?? [])
		.filter((item) => item.id !== currentAssessment.id && !item.is_canceled && item.device_model === currentAssessment.device_model && new Date(item.evaluated_at).getTime() < currentDate)
		.sort((left, right) => new Date(right.evaluated_at) - new Date(left.evaluated_at))[0] ?? null;
}

export function assessmentComparability(current, previous) {
	const currentProfile = assessmentProfile(current);
	if (!previous) {
		return {
			level: 'baseline',
			label: 'Linha de base',
			message: 'Não há avaliação anterior do mesmo modelo; esta medição será a linha de base.',
			warnings: [],
			sameSegmentalReference: true,
		};
	}

	const previousProfile = assessmentProfile(previous);
	const isBodyscan = current.device_model === 'Bodyscan Pro';
	const sameApplication = !isBodyscan || (currentProfile.application && currentProfile.application === previousProfile.application);
	const sameAthleteMode = !isBodyscan || (typeof currentProfile.athleteMode === 'boolean'
		&& typeof previousProfile.athleteMode === 'boolean'
		&& currentProfile.athleteMode === previousProfile.athleteMode);
	const sameLayout = Boolean(currentProfile.layout && currentProfile.layout === previousProfile.layout);
	const sameSegmentalReference = segmentalReferencesMatch(current.segmental_measurements, previous.segmental_measurements);
	const applicationChanged = isBodyscan && currentProfile.application && previousProfile.application && !sameApplication;
	const technicalPair = sameApplication
		&& currentProfile.application === 'fitdays'
		&& currentProfile.layout === 'fitdays_technical_report'
		&& previousProfile.layout === 'fitdays_technical_report';
	const warnings = [
		isBodyscan && !sameApplication ? 'Aplicativos de origem diferentes ou não confirmados: somente valores equivalentes devem ser comparados.' : null,
		!sameAthleteMode ? 'Modo atleta e modo padrão usam perfis de cálculo diferentes. Os valores não representam automaticamente melhora ou piora.' : null,
		!sameSegmentalReference ? 'As referências segmentares são diferentes. Os percentuais são exibidos sem cálculo de variação.' : null,
		sameApplication && !sameLayout ? 'Os layouts são diferentes. A comparação usa somente campos presentes nos dois relatórios.' : null,
	].filter(Boolean);
	const level = applicationChanged ? 'limited' : technicalPair && sameAthleteMode && sameSegmentalReference ? 'complete' : 'common_fields';

	return {
		level,
		label: level === 'complete' ? 'Comparação completa' : level === 'limited' ? 'Comparação limitada' : 'Comparação das métricas comuns',
		message: level === 'limited'
			? 'Fitdays e RelaxFit podem usar algoritmos diferentes; a origem permanece identificada.'
			: level === 'complete'
				? 'Mesmo equipamento, Fitdays técnico, modo de cálculo e referências segmentares.'
				: 'Somente métricas presentes nas duas avaliações, do mesmo tipo e unidade, são exibidas.',
		warnings,
		sameSegmentalReference,
		currentProfile,
		previousProfile,
	};
}

export function comparisonProfileLabel(profile) {
	if (!profile) return 'Não informado';
	const applications = { fitdays: 'Fitdays', relaxfit: 'RelaxFit' };
	const layouts = {
		fitdays_technical_report: 'relatório técnico',
		fitdays_vertical_report: 'relatório vertical',
		relaxfit_vertical_report: 'relatório vertical',
	};
	const parts = [applications[profile.application] ?? profile.application, layouts[profile.layout] ?? profile.layout];
	if (typeof profile.athleteMode === 'boolean') parts.push(profile.athleteMode ? 'modo atleta' : 'modo padrão');
	return parts.filter(Boolean).join(' · ') || 'Origem não informada';
}

export function commonExtendedMetricComparisons(current, previous) {
	if (!previous) return [];
	const previousByKey = new Map((previous.extended_metrics ?? [])
		.filter((metric) => metric.numeric_value !== null && metric.numeric_value !== undefined)
		.map((metric) => [`${metric.metric_code}|${String(metric.unit ?? '').toLowerCase()}`, metric]));

	return (current.extended_metrics ?? []).flatMap((metric) => {
		if (metric.numeric_value === null || metric.numeric_value === undefined) return [];
		const prior = previousByKey.get(`${metric.metric_code}|${String(metric.unit ?? '').toLowerCase()}`);
		if (!prior || !Number.isFinite(Number(prior.numeric_value)) || !Number.isFinite(Number(metric.numeric_value))) return [];
		return [{
			key: metric.metric_code,
			label: metric.original_label,
			unit: metric.unit ?? '',
			previous: Number(prior.numeric_value),
			current: Number(metric.numeric_value),
		}];
	});
}

export function commonImpedanceComparisons(current, previous) {
	if (!previous?.impedance_readings?.length || !current?.impedance_readings?.length) return [];
	const currentLayout = assessmentProfile(current).layout;
	if (!currentLayout || currentLayout !== assessmentProfile(previous).layout) return [];
	const previousByKey = new Map(previous.impedance_readings.map((reading) => [
		`${reading.frequency_khz}|${reading.region}|${reading.side}`,
		reading,
	]));

	return current.impedance_readings.flatMap((reading) => {
		const prior = previousByKey.get(`${reading.frequency_khz}|${reading.region}|${reading.side}`);
		if (!prior || reading.impedance_ohms === null || reading.impedance_ohms === undefined || prior.impedance_ohms === null || prior.impedance_ohms === undefined) return [];
		if (!Number.isFinite(Number(reading.impedance_ohms)) || !Number.isFinite(Number(prior.impedance_ohms))) return [];
		return [{ ...reading, previous_ohms: Number(prior.impedance_ohms), current_ohms: Number(reading.impedance_ohms) }];
	});
}

function assessmentProfile(assessment) {
	return {
		application: assessment?.measurement_application ?? null,
		layout: assessment?.source_metadata?.report_layout
			?? assessment?.extended_metrics?.[0]?.source_layout
			?? assessment?.impedance_readings?.[0]?.source_layout
			?? null,
		athleteMode: assessment?.athlete_mode_at_assessment,
	};
}

function segmentalReferencesMatch(current = [], previous = []) {
	if (!current?.length || !previous?.length) return true;
	const previousByKey = new Map(previous.map((item) => [`${item.type}:${item.region}:${item.side}`, item]));

	return current.every((item) => {
		const prior = previousByKey.get(`${item.type}:${item.region}:${item.side}`);
		return prior
			&& prior.reference_version === item.reference_version
			&& Number(prior.reference_min_percentage) === Number(item.reference_min_percentage)
			&& Number(prior.reference_max_percentage) === Number(item.reference_max_percentage);
	});
}

export function formatMetric(value, metric) {
	if (!hasValue(value)) return '-';
	if (metric.text) return value;

	const formatted = new Intl.NumberFormat('pt-BR', {
		minimumFractionDigits: metric.decimals,
		maximumFractionDigits: metric.decimals,
	}).format(Number(value));

	return `${formatted}${metric.unit ? ` ${metric.unit}` : ''}`;
}

export function metricDelta(currentValue, previousValue, metric) {
	if (metric.text || !hasValue(currentValue) || !hasValue(previousValue)) return null;
	const delta = Number(currentValue) - Number(previousValue);
	const sign = delta > 0 ? '+' : '';
	const value = new Intl.NumberFormat('pt-BR', {
		minimumFractionDigits: metric.decimals,
		maximumFractionDigits: metric.decimals,
	}).format(delta);

	return `${sign}${value}${metric.unit ? ` ${metric.unit}` : ''}`;
}

function hasValue(value) {
	return value !== null && value !== undefined && value !== '';
}
