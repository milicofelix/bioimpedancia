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

export async function logout() {
	await window.axios.post(endpoints.logout, {}, {
		headers: { Accept: 'application/json' },
	});
}

export function apiErrorMessage(error, fallback = 'Não foi possível concluir a operação.') {
	return error?.response?.data?.message ?? fallback;
}
