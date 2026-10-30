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

export const bodyscanProtocolKeys = [
	'profile_confirmed',
	'athlete_mode_confirmed',
	'electrodes_contact_confirmed',
	'comparable_conditions_confirmed',
];

export const emptyBodyscanProtocol = () => ({
	profile_confirmed: false,
	athlete_mode_confirmed: false,
	electrodes_contact_confirmed: false,
	comparable_conditions_confirmed: false,
});

export function bodyscanProtocolComplete(protocol) {
	return bodyscanProtocolKeys.every((key) => protocol?.[key] === true);
}

export function bodyscanFormFromExtraction(extraction) {
	return {
		general: assessmentFromExtraction(extraction),
		profile: {
			athlete_mode_at_assessment: '',
			measurement_application: extraction?.source_application ?? extraction?.image?.source_application ?? '',
		},
		protocol: extraction?.measurement_protocol ?? emptyBodyscanProtocol(),
		segments: (extraction?.segmental_measurements ?? []).map((segment) => ({
			...segment,
			mass_kg: displayNumber(segment.mass_kg),
			reference_percentage: displayNumber(segment.reference_percentage),
			reference_min_percentage: displayNumber(segment.reference_min_percentage),
			reference_max_percentage: displayNumber(segment.reference_max_percentage),
		})),
		impedanceReadings: (extraction?.impedance_readings ?? []).map((reading) => ({
			...reading,
			impedance_ohms: displayNumber(reading.impedance_ohms),
		})),
	};
}

export function updateBodyscanSegment(segments, index, field, value) {
	return segments.map((segment, currentIndex) => currentIndex === index
		? { ...segment, [field]: field === 'classification' ? value : maskRelaxmedicValue(field, value) }
		: segment);
}

export function updateBodyscanImpedance(readings, index, field, value) {
	return readings.map((reading, currentIndex) => currentIndex === index
		? { ...reading, [field]: field === 'impedance_ohms' ? maskRelaxmedicValue(field, value) : value }
		: reading);
}

export function bodyscanAssessmentPayload(form, clientId, sourceMetadata, confirmed) {
	const athleteMode = form.profile?.athlete_mode_at_assessment ?? '';

	return {
		...relaxmedicAssessmentPayload(form.general, clientId, sourceMetadata, false),
		relaxmedic_review_confirmed: undefined,
		bodyscan_review_confirmed: confirmed,
		athlete_mode_at_assessment: athleteMode === '' ? '' : athleteMode === '1',
		measurement_application: form.profile?.measurement_application ?? '',
		measurement_protocol: form.protocol ?? emptyBodyscanProtocol(),
		segmental_measurements: form.segments.map(({ type, region, side, mass_kg, reference_percentage, classification, reference_min_percentage, reference_max_percentage }) => ({
			type, region, side, classification,
			mass_kg: normalizeDecimal(mass_kg),
			reference_percentage: normalizeDecimal(reference_percentage),
			reference_min_percentage: normalizeDecimal(reference_min_percentage),
			reference_max_percentage: normalizeDecimal(reference_max_percentage),
		})),
		impedance_readings: (form.impedanceReadings ?? []).map(({ frequency_khz, region, side, impedance_ohms, reading_state }) => ({
			frequency_khz, region, side, reading_state,
			impedance_ohms: String(impedance_ohms ?? '').trim() === '' ? null : normalizeDecimal(impedance_ohms),
		})),
	};
}

function displayNumber(value) {
	return value === null || value === undefined ? '' : String(value).replace('.', ',');
}

function normalizeDecimal(value) {
	return String(value ?? '').replace(',', '.').replace(/[^\d.]/g, '');
}
