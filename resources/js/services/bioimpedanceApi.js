const endpoints = {
	workspace: '/bioimpedance',
	operationalWorkspace: '/bioimpedance/operational',
	logout: '/logout',
};

export async function loadBioimpedanceWorkspace() {
	const { data } = await window.axios.get(endpoints.workspace);

	return data;
}

export async function loadOperationalWorkspace() {
	const { data } = await window.axios.get(endpoints.operationalWorkspace);

	return data;
}

export async function createBioimpedanceClient(payload) {
	const { data } = await window.axios.post('/bioimpedance/clients', payload);

	return data.client;
}

export async function updateBioimpedanceClientBirthDate(clientId, birthDate) {
	const { data } = await window.axios.patch(`/bioimpedance/clients/${clientId}/birth-date`, { birth_date: birthDate });

	return data.client;
}

export async function processRelaxmedicImage(image) {
	const payload = new FormData();
	payload.append('image', image);

	const { data } = await window.axios.post('/bioimpedance/relaxmedic/process-image', payload);

	return data.extraction;
}

export async function processBodyscanProImage(image) {
	const payload = new FormData();
	payload.append('image', image);

	const { data } = await window.axios.post('/bioimpedance/bodyscan-pro/process-image', payload);

	return data.extraction;
}

export async function createBioimpedanceAssessment(payload) {
	const { data } = await window.axios.post('/bioimpedance/assessments', payload);

	return data;
}

export async function generateAssessmentObservation(assessmentId) {
	const { data } = await window.axios.get(`/bioimpedance/assessments/${assessmentId}/observation-suggestion`);

	return data.assistant;
}

export async function approveAssessmentObservation(assessmentId, payload) {
	const { data } = await window.axios.patch(`/bioimpedance/assessments/${assessmentId}/observation`, payload);

	return data;
}

export async function generateAssessmentCareGuidance(assessmentId) {
	const { data } = await window.axios.get(`/bioimpedance/assessments/${assessmentId}/care-guidance-suggestion`);

	return data.assistant;
}

export async function approveAssessmentCareGuidance(assessmentId, payload) {
	const { data } = await window.axios.patch(`/bioimpedance/assessments/${assessmentId}/care-guidance`, payload);

	return data;
}

export async function createAssessmentShare(assessmentId, payload) {
	const { data } = await window.axios.post(`/bioimpedance/assessments/${assessmentId}/shares`, payload);

	return data;
}

export async function revokeAssessmentShare(shareId) {
	const { data } = await window.axios.patch(`/bioimpedance/report-shares/${shareId}/revoke`);

	return data;
}

export async function logout() {
	await window.axios.post(endpoints.logout, {}, {
		headers: { Accept: 'application/json' },
	});
}

export function apiErrorMessage(error, fallback = 'Não foi possível concluir a operação.') {
	if (error?.response?.status === 429) return 'Limite de tentativas atingido. Aguarde um minuto antes de tentar novamente.';
	if (typeof navigator !== 'undefined' && !navigator.onLine) return 'A conexão caiu. Nenhum novo envio será feito até a internet voltar.';
	if (!error?.response) return 'Não foi possível confirmar a resposta do servidor. Verifique a conexão e o histórico antes de repetir a operação.';

	return error?.response?.data?.message ?? fallback;
}

export function apiValidationErrors(error) {
	return error?.response?.data?.errors ?? {};
}
