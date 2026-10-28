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
	const definitions = assessment?.device_model === 'Relaxmedic'
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
