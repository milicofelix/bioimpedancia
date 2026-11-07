import { describe, expect, it } from 'vitest';
import { clientActivityDate, compareClientRecency } from './clientRecency';

describe('clientes recentes por última atividade', () => {
	it('prioriza atividade recente mesmo quando a avaliação é antiga', () => {
		const clients = [
			{ id: 1, full_name: 'Camila', last_activity_at: '2026-11-03T10:00:00Z', last_assessment_at: '2026-11-03T10:00:00Z' },
			{ id: 2, full_name: 'Adriano', last_activity_at: '2026-11-07T10:00:00Z', last_assessment_at: '2026-10-31T10:00:00Z' },
		];
		expect([...clients].sort(compareClientRecency).map(({ id }) => id)).toEqual([2, 1]);
		expect(clients[0].id).toBe(1);
	});

	it('inclui cadastro e triagem sem exigir avaliação de bioimpedância', () => {
		const clients = [
			{ id: 1, full_name: 'Novo cadastro', last_activity_at: '2026-11-07T10:00:00Z' },
			{ id: 2, full_name: 'Triagem recente', last_activity_at: '2026-11-07T11:00:00Z' },
		];
		expect(clients.sort(compareClientRecency).map(({ id }) => id)).toEqual([2, 1]);
	});

	it('desempata por nome e identificador e deixa datas ausentes ao final', () => {
		const clients = [
			{ id: 3, full_name: 'Sem data' },
			{ id: 2, full_name: 'Ana', last_activity_at: '2026-11-07T10:00:00Z' },
			{ id: 1, full_name: 'Ana', last_activity_at: '2026-11-07T10:00:00Z' },
		];
		expect(clients.sort(compareClientRecency).map(({ id }) => id)).toEqual([1, 2, 3]);
	});

	it('ignora datas inválidas e preserva compatibilidade com respostas antigas', () => {
		expect(clientActivityDate({ last_activity_at: 'inválida', last_assessment_at: '2026-11-03' })).toBe('2026-11-03');
		expect(clientActivityDate({ last_activity_at: 'inválida' })).toBeNull();
	});
});
