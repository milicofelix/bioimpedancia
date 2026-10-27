import { assessmentFromExtraction, maskRelaxmedicValue, relaxmedicAssessmentPayload } from './relaxmedicAssessmentForm';

export const segmentLabels = {
	'arm|left': 'Braço esquerdo (L)',
	'arm|right': 'Braço direito (R)',
	'trunk|center': 'Tronco',
	'leg|left': 'Perna esquerda (L)',
	'leg|right': 'Perna direita (R)',
};

export const segmentClassificationOptions = [
	{ value: 'low', label: 'Baixo' },
	{ value: 'standard', label: 'Padrão' },
	{ value: 'high', label: 'Alto' },
];

export function bodyscanFormFromExtraction(extraction) {
	return {
		general: assessmentFromExtraction(extraction),
		segments: (extraction?.segmental_measurements ?? []).map((segment) => ({
			...segment,
			mass_kg: displayNumber(segment.mass_kg),
			reference_percentage: displayNumber(segment.reference_percentage),
			reference_min_percentage: displayNumber(segment.reference_min_percentage),
			reference_max_percentage: displayNumber(segment.reference_max_percentage),
		})),
	};
}

export function updateBodyscanSegment(segments, index, field, value) {
	return segments.map((segment, currentIndex) => currentIndex === index
		? { ...segment, [field]: field === 'classification' ? value : maskRelaxmedicValue(field, value) }
		: segment);
}

export function bodyscanAssessmentPayload(form, clientId, sourceMetadata, confirmed) {
	return {
		...relaxmedicAssessmentPayload(form.general, clientId, sourceMetadata, false),
		relaxmedic_review_confirmed: undefined,
		bodyscan_review_confirmed: confirmed,
		segmental_measurements: form.segments.map(({ type, region, side, mass_kg, reference_percentage, classification, reference_min_percentage, reference_max_percentage }) => ({
			type, region, side, classification,
			mass_kg: normalizeDecimal(mass_kg),
			reference_percentage: normalizeDecimal(reference_percentage),
			reference_min_percentage: normalizeDecimal(reference_min_percentage),
			reference_max_percentage: normalizeDecimal(reference_max_percentage),
		})),
	};
}

function displayNumber(value) {
	return value === null || value === undefined ? '' : String(value).replace('.', ',');
}

function normalizeDecimal(value) {
	return String(value ?? '').replace(',', '.').replace(/[^\d.]/g, '');
}
