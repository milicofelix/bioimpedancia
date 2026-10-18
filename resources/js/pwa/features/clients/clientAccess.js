const WRITE_ROLES = ['admin', 'professional', 'reception'];
const CLINICAL_ROLES = ['admin', 'professional'];

export function canCreateClient(user) {
	return WRITE_ROLES.includes(user?.role);
}

export function assessmentAccess(client, user, scaleModel) {
	if (!client?.is_active) return { allowed: false, reason: 'Cliente inativo não pode receber novas avaliações.' };
	if (client?.is_anonymized) return { allowed: false, reason: 'Cliente anonimizado não pode receber novas avaliações.' };
	if (!WRITE_ROLES.includes(user?.role)) return { allowed: false, reason: 'Seu perfil possui permissão somente para consulta.' };
	if (scaleModel === 'relaxmedic' && !CLINICAL_ROLES.includes(user?.role)) {
		return { allowed: false, reason: 'Avaliações Relaxmedic exigem perfil profissional ou administrador.' };
	}

	return { allowed: true, reason: null };
}
