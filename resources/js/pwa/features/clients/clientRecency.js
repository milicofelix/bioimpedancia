export function clientActivityDate(client) {
	return [client.last_activity_at, client.updated_at, client.created_at, client.last_assessment_at]
		.find((value) => value && Number.isFinite(new Date(value).getTime())) ?? null;
}

export function compareClientRecency(first, second) {
	const firstDate = clientActivityDate(first);
	const secondDate = clientActivityDate(second);
	return (secondDate ? new Date(secondDate).getTime() : 0)
		- (firstDate ? new Date(firstDate).getTime() : 0)
		|| String(first.full_name ?? '').localeCompare(String(second.full_name ?? ''), 'pt-BR')
		|| Number(first.id) - Number(second.id);
}
