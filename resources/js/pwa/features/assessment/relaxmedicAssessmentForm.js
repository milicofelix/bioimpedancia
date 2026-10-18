import { relaxmedicMetricLabels } from '../../../domain/bioimpedance/relaxmedic';
import { localDatetimeNow } from '../../../shared/formatters';
import { normalizeInteger } from '../../../shared/inputMasks';

export const relaxmedicTextFields = ['obesity_level', 'body_type'];
export const relaxmedicIntegerFields = ['resting_metabolism_kcal', 'body_age'];

export function emptyRelaxmedicAssessment() {
	return {
		evaluated_at: localDatetimeNow(),
		...Object.fromEntries(Object.keys(relaxmedicMetricLabels).map((field) => [field, ''])),
		notes: '',
	};
}

export function assessmentFromExtraction(extraction) {
	return {
		...emptyRelaxmedicAssessment(),
		evaluated_at: extraction?.measured_at ?? localDatetimeNow(),
		...Object.fromEntries(Object.keys(relaxmedicMetricLabels).map((field) => [field, extraction?.metrics?.[field] ?? ''])),
	};
}

export function maskRelaxmedicValue(field, value) {
	if (relaxmedicTextFields.includes(field)) return value;
	if (relaxmedicIntegerFields.includes(field)) return normalizeInteger(value).slice(0, 5);

	const cleaned = String(value ?? '').replace(/[^\d,.]/g, '').replace('.', ',');
	const [integer = '', decimal = ''] = cleaned.split(',');

	return decimal.length || cleaned.includes(',')
		? `${integer.slice(0, 4)},${decimal.slice(0, 2)}`
		: integer.slice(0, 4);
}

export function relaxmedicAssessmentPayload(form, clientId, sourceMetadata, confirmed) {
	const numericValues = Object.fromEntries(
		Object.keys(relaxmedicMetricLabels)
			.filter((field) => !relaxmedicTextFields.includes(field))
			.map((field) => [field, normalizeDecimal(form[field])]),
	);

	return {
		...form,
		...numericValues,
		bioimpedance_client_id: clientId,
		relaxmedic_review_confirmed: confirmed,
		source_metadata: sourceMetadata,
	};
}

function normalizeDecimal(value) {
	if (value === null || value === undefined || value === '') return '';

	return String(value).replace(',', '.').replace(/[^\d.]/g, '');
}
